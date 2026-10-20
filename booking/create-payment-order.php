<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/razorpay.php';
require_booking_auth();

header('Content-Type: application/json; charset=utf-8');

function payment_order_error($status, $message) {
    http_response_code($status);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    payment_order_error(405, 'Method not allowed.');
}
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    payment_order_error(403, 'Your session expired. Refresh checkout and try again.');
}
if (!razorpay_credentials()) {
    payment_order_error(503, 'Online payment is not configured yet. Choose Cash or contact the site administrator.');
}
try {
    razorpay_expire_stale_bookings($conn);
} catch (Throwable $exception) {
    payment_order_error(500, 'Could not safely prepare a new payment. Please retry shortly.');
}

$event_id = (int)($_POST['event_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 0);
$coupon_code = strtoupper(trim((string)($_POST['coupon'] ?? '')));
$user_id = (int)$_SESSION['user_id'];

if ($event_id <= 0 || $quantity < 1 || $quantity > 10) {
    payment_order_error(422, 'Select between 1 and 10 tickets.');
}

$event = fetch_single(
    "SELECT e.id, e.title, e.price, e.discount_price, e.available_seats
     FROM events e WHERE e.id = ? AND e.status = 'approved'",
    'i',
    [$event_id]
);
if (!$event) {
    payment_order_error(409, 'Event is unavailable.');
}

$existing_pending = fetch_single(
    "SELECT p.payment_id AS order_id, p.amount, p.currency, b.booking_id, b.quantity,
            b.coupon_code, b.final_amount, e.title
     FROM payments p
     JOIN bookings b ON b.id = p.booking_id
     JOIN events e ON e.id = b.event_id
     WHERE p.gateway = 'razorpay' AND p.status = 'pending'
       AND b.status = 'pending' AND b.payment_status = 'pending'
       AND b.user_id = ? AND b.event_id = ?
       AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
     ORDER BY p.created_at DESC LIMIT 1",
    'ii',
    [$user_id, $event_id]
);
if ($existing_pending) {
    if ((int)$existing_pending['quantity'] !== $quantity
        || (string)($existing_pending['coupon_code'] ?? '') !== $coupon_code) {
        payment_order_error(409, 'You already have an unpaid reservation for this event. Complete that checkout or wait up to 30 minutes for its safe expiry.');
    }
    $credentials = razorpay_credentials();
    echo json_encode([
        'status' => 'ok',
        'key' => $credentials['key_id'],
        'order_id' => $existing_pending['order_id'],
        'booking_id' => $existing_pending['booking_id'],
        'amount' => (int)round((float)$existing_pending['amount'] * 100),
        'currency' => $existing_pending['currency'],
        'name' => SITE_NAME,
        'description' => $existing_pending['title'],
    ]);
    exit();
}
if ((int)$event['available_seats'] < $quantity) {
    payment_order_error(409, 'Not enough tickets remain.');
}

$unit_price = (float)$event['discount_price'] > 0 ? (float)$event['discount_price'] : (float)$event['price'];
$subtotal = round($unit_price * $quantity, 2);
$discount = 0.0;
$coupon = null;
if ($coupon_code !== '') {
    $coupon = fetch_single(
        "SELECT c.* FROM coupons c
         WHERE c.code = ? AND c.status = 1 AND c.valid_from <= NOW() AND c.valid_to >= NOW()
           AND (c.event_id IS NULL OR c.event_id = ?)
           AND (c.user_id IS NULL OR c.user_id = ?
                OR EXISTS (SELECT 1 FROM users coupon_owner WHERE coupon_owner.id = c.user_id AND coupon_owner.role = 'organizer'))
           AND NOT EXISTS (SELECT 1 FROM coupon_usage cu WHERE cu.coupon_id = c.id AND cu.user_id = ?)",
        'siii',
        [$coupon_code, $event_id, $user_id, $user_id]
    );
    if (!$coupon) {
        payment_order_error(422, 'Coupon is invalid, expired, or already used.');
    }

    if (in_array($coupon_code, ['EVENTO26', 'EGROUP26'], true)
        && fetch_single("SELECT id FROM bookings WHERE user_id = ? AND status <> 'cancelled' LIMIT 1", 'i', [$user_id])) {
        payment_order_error(422, 'This welcome coupon is only valid on your first booking.');
    }
    if ($quantity < (int)$coupon['min_quantity'] || $subtotal < (float)$coupon['min_order_amount']) {
        payment_order_error(422, 'This coupon does not meet its minimum order requirements.');
    }
    if ((int)$coupon['usage_limit'] > 0 && (int)$coupon['used_count'] >= (int)$coupon['usage_limit']) {
        payment_order_error(422, 'This coupon has reached its usage limit.');
    }
    $discount = $coupon['discount_type'] === 'percentage'
        ? $subtotal * (float)$coupon['discount_value'] / 100
        : (float)$coupon['discount_value'];
    if ((float)$coupon['max_discount_amount'] > 0) {
        $discount = min($discount, (float)$coupon['max_discount_amount']);
    }
    $discount = min(round($discount, 2), $subtotal);
}

$final_total = round($subtotal - $discount, 2);
if ($final_total <= 0) {
    payment_order_error(422, 'The payable amount must be greater than zero.');
}

try {
    $gateway_order = razorpay_request('POST', 'orders', [
        'amount' => (int)round($final_total * 100),
        'currency' => CURRENCY_CODE,
        'receipt' => 'evt_' . bin2hex(random_bytes(8)),
        'expire_by' => time() + (30 * 60),
        'notes' => ['event_id' => (string)$event_id, 'user_id' => (string)$user_id],
    ]);
} catch (Throwable $exception) {
    error_log('Evento Razorpay order creation failed: ' . $exception->getMessage());
    payment_order_error(502, 'Payment gateway could not create an order. No booking or charge was made.');
}

if (empty($gateway_order['id']) || (int)($gateway_order['amount'] ?? 0) !== (int)round($final_total * 100)) {
    error_log('Evento Razorpay returned an invalid order response.');
    payment_order_error(502, 'Payment gateway returned an invalid order. No booking or charge was made.');
}

$booking_id = generate_booking_id();
$coupon_code_db = $coupon ? $coupon['code'] : '';
$order_id = $gateway_order['id'];
mysqli_begin_transaction($conn);
try {
    $stmt = mysqli_prepare($conn, "SELECT available_seats FROM events WHERE id = ? AND status = 'approved' FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    $locked_event = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if (!$locked_event || (int)$locked_event['available_seats'] < $quantity) {
        throw new RuntimeException('Tickets were just sold out. No payment has been taken.');
    }

    if ($coupon) {
        $stmt = mysqli_prepare($conn, "SELECT id, used_count, usage_limit FROM coupons
            WHERE id = ? AND status = 1 AND valid_from <= NOW() AND valid_to >= NOW() FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'i', $coupon['id']);
        mysqli_stmt_execute($stmt);
        $locked_coupon = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        if (!$locked_coupon || ((int)$locked_coupon['usage_limit'] > 0 && (int)$locked_coupon['used_count'] >= (int)$locked_coupon['usage_limit'])) {
            throw new RuntimeException('Coupon usage limit was reached. No payment has been taken.');
        }
        $stmt = mysqli_prepare($conn, 'SELECT id FROM coupon_usage WHERE coupon_id = ? AND user_id = ? LIMIT 1 FOR UPDATE');
        mysqli_stmt_bind_param($stmt, 'ii', $coupon['id'], $user_id);
        mysqli_stmt_execute($stmt);
        $existing_coupon_use = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        if ($existing_coupon_use) {
            throw new RuntimeException('This coupon has already been used.');
        }
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO bookings
        (booking_id, user_id, event_id, total_amount, discount_amount, coupon_code, final_amount, quantity, status, payment_status, payment_method)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', 'razorpay')");
    mysqli_stmt_bind_param($stmt, 'siiddsdi', $booking_id, $user_id, $event_id, $subtotal, $discount, $coupon_code_db, $final_total, $quantity);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Could not save the pending booking.');
    }
    $booking_db_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "INSERT INTO payments (booking_id, payment_id, gateway, amount, currency, status, gateway_response)
        VALUES (?, ?, 'razorpay', ?, ?, 'pending', ?)");
    $gateway_response = json_encode(['order_id' => $order_id], JSON_THROW_ON_ERROR);
    $currency = CURRENCY_CODE;
    mysqli_stmt_bind_param($stmt, 'isdss', $booking_db_id, $order_id, $final_total, $currency, $gateway_response);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Could not save the pending payment.');
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, 'UPDATE events SET booked_seats = booked_seats + ? WHERE id = ? AND available_seats >= ?');
    mysqli_stmt_bind_param($stmt, 'iii', $quantity, $event_id, $quantity);
    if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
        throw new RuntimeException('Tickets were just sold out. No payment has been taken.');
    }
    mysqli_stmt_close($stmt);

    if ($coupon) {
        $stmt = mysqli_prepare($conn, 'INSERT INTO coupon_usage (coupon_id, user_id, booking_id) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'iii', $coupon['id'], $user_id, $booking_db_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Could not reserve this coupon for the pending booking.');
        }
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($conn, 'UPDATE coupons SET used_count = used_count + 1 WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $coupon['id']);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Could not reserve the coupon usage.');
        }
        mysqli_stmt_close($stmt);
    }

    mysqli_commit($conn);
} catch (Throwable $exception) {
    mysqli_rollback($conn);
    error_log('Evento pending payment setup failed: ' . $exception->getMessage());
    payment_order_error(409, $exception->getMessage());
}

$credentials = razorpay_credentials();
echo json_encode([
    'status' => 'ok',
    'key' => $credentials['key_id'],
    'order_id' => $order_id,
    'booking_id' => $booking_id,
    'amount' => (int)round($final_total * 100),
    'currency' => CURRENCY_CODE,
    'name' => SITE_NAME,
    'description' => $event['title'],
]);
