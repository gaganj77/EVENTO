<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/razorpay.php';
require_booking_auth();

header('Content-Type: application/json; charset=utf-8');

function payment_verify_error($status, $message) {
    http_response_code($status);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    payment_verify_error(405, 'Method not allowed.');
}
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    payment_verify_error(403, 'Your session expired. Refresh checkout and try again.');
}

$order_id = trim((string)($_POST['razorpay_order_id'] ?? ''));
$payment_id = trim((string)($_POST['razorpay_payment_id'] ?? ''));
$signature = trim((string)($_POST['razorpay_signature'] ?? ''));
if (!razorpay_verify_signature($order_id, $payment_id, $signature)) {
    payment_verify_error(400, 'Payment signature could not be verified.');
}

$stmt = mysqli_prepare($conn, "SELECT p.id AS payment_row_id, p.booking_id AS booking_db_id, p.amount,
        p.currency, p.status AS payment_status, b.booking_id, b.user_id, b.event_id, b.quantity,
        b.status AS booking_status, b.payment_status AS booking_payment_status
    FROM payments p JOIN bookings b ON b.id = p.booking_id
    WHERE p.gateway = 'razorpay'
      AND (p.payment_id = ? OR JSON_UNQUOTE(JSON_EXTRACT(p.gateway_response, '$.order_id')) = ?)
      AND b.user_id = ? LIMIT 1");
$user_id = (int)$_SESSION['user_id'];
mysqli_stmt_bind_param($stmt, 'ssi', $order_id, $order_id, $user_id);
mysqli_stmt_execute($stmt);
$payment = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
if (!$payment) {
    payment_verify_error(404, 'This payment order was not found for your account.');
}
if ($payment['payment_status'] === 'success' && $payment['booking_payment_status'] === 'completed') {
    echo json_encode(['status' => 'ok', 'booking_id' => $payment['booking_id']]);
    exit();
}
if ($payment['payment_status'] !== 'pending' || $payment['booking_status'] !== 'pending') {
    payment_verify_error(409, 'This booking is no longer awaiting payment.');
}

try {
    $gateway_payment = razorpay_request('GET', 'payments/' . rawurlencode($payment_id));
    if (($gateway_payment['order_id'] ?? '') !== $order_id
        || (int)($gateway_payment['amount'] ?? 0) !== (int)round((float)$payment['amount'] * 100)
        || strtoupper((string)($gateway_payment['currency'] ?? '')) !== strtoupper($payment['currency'])) {
        throw new RuntimeException('The payment does not match this booking order.');
    }
    if (($gateway_payment['status'] ?? '') === 'authorized') {
        $gateway_payment = razorpay_request('POST', 'payments/' . rawurlencode($payment_id) . '/capture', [
            'amount' => (int)round((float)$payment['amount'] * 100),
            'currency' => $payment['currency'],
        ]);
    }
    if (($gateway_payment['status'] ?? '') !== 'captured' || empty($gateway_payment['captured'])) {
        throw new RuntimeException('The payment has not been captured.');
    }
} catch (Throwable $exception) {
    error_log('Evento Razorpay payment verification failed: ' . $exception->getMessage());
    payment_verify_error(402, 'Payment could not be verified or captured. Contact support if your bank shows a debit.');
}

mysqli_begin_transaction($conn);
try {
    $stmt = mysqli_prepare($conn, "SELECT p.status AS payment_status, b.status AS booking_status, b.payment_status AS booking_payment_status
        FROM payments p JOIN bookings b ON b.id = p.booking_id
        WHERE p.id = ? AND b.user_id = ? FOR UPDATE");
    $payment_row_id = (int)$payment['payment_row_id'];
    mysqli_stmt_bind_param($stmt, 'ii', $payment_row_id, $user_id);
    mysqli_stmt_execute($stmt);
    $locked = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if (!$locked) {
        throw new RuntimeException('Booking payment record was not found.');
    }
    if ($locked['payment_status'] === 'success' && $locked['booking_payment_status'] === 'completed') {
        mysqli_commit($conn);
        echo json_encode(['status' => 'ok', 'booking_id' => $payment['booking_id']]);
        exit();
    }
    if ($locked['payment_status'] !== 'pending' || $locked['booking_status'] !== 'pending') {
        throw new RuntimeException('This booking is no longer awaiting payment.');
    }

    $gateway_response = json_encode([
        'order_id' => $order_id,
        'payment_id' => $payment_id,
        'status' => $gateway_payment['status'],
        'amount' => $gateway_payment['amount'],
        'currency' => $gateway_payment['currency'],
        'verified_at' => date('c'),
    ], JSON_THROW_ON_ERROR);
    $stmt = mysqli_prepare($conn, "UPDATE payments SET payment_id = ?, status = 'success', gateway_response = ?, transaction_time = NOW()
        WHERE id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt, 'ssi', $payment_id, $gateway_response, $payment_row_id);
    if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
        throw new RuntimeException('Payment status could not be saved.');
    }
    mysqli_stmt_close($stmt);

    $booking_db_id = (int)$payment['booking_db_id'];
    $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = 'confirmed', payment_status = 'completed'
        WHERE id = ? AND status = 'pending' AND payment_status = 'pending'");
    mysqli_stmt_bind_param($stmt, 'i', $booking_db_id);
    if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
        throw new RuntimeException('Booking confirmation could not be saved.');
    }
    mysqli_stmt_close($stmt);
    mysqli_commit($conn);
} catch (Throwable $exception) {
    mysqli_rollback($conn);
    error_log('Evento payment finalization failed: ' . $exception->getMessage());
    payment_verify_error(500, 'Payment was received but booking confirmation needs support assistance.');
}

echo json_encode(['status' => 'ok', 'booking_id' => $payment['booking_id']]);
