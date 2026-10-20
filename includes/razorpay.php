<?php
/**
 * Minimal Razorpay API client for order creation and verified payment capture.
 */

function razorpay_credentials() {
    $key_id = getenv('RAZORPAY_KEY_ID') ?: '';
    $key_secret = getenv('RAZORPAY_KEY_SECRET') ?: '';

    if ($key_id === '' || $key_secret === '') {
        return null;
    }

    return ['key_id' => $key_id, 'key_secret' => $key_secret];
}

function razorpay_request($method, $path, array $payload = []) {
    $credentials = razorpay_credentials();
    if (!$credentials) {
        throw new RuntimeException('Online payment is not configured.');
    }

    $curl = curl_init('https://api.razorpay.com/v1/' . ltrim($path, '/'));
    if ($curl === false) {
        throw new RuntimeException('Could not initialize the payment gateway connection.');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_USERPWD => $credentials['key_id'] . ':' . $credentials['key_secret'],
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ];
    if ($payload) {
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($body === false) {
        throw new RuntimeException('Payment gateway connection failed: ' . $curl_error);
    }

    $response = json_decode($body, true);
    if (!is_array($response)) {
        throw new RuntimeException('Payment gateway returned an invalid response.');
    }
    if ($status < 200 || $status >= 300) {
        $description = $response['error']['description'] ?? 'Payment gateway request failed.';
        throw new RuntimeException($description);
    }

    return $response;
}

function razorpay_verify_signature($order_id, $payment_id, $signature) {
    $credentials = razorpay_credentials();
    if (!$credentials || $order_id === '' || $payment_id === '' || $signature === '') {
        return false;
    }

    $expected = hash_hmac('sha256', $order_id . '|' . $payment_id, $credentials['key_secret']);
    return hash_equals($expected, $signature);
}

function razorpay_expire_stale_bookings($conn) {
    mysqli_begin_transaction($conn);
    try {
        $result = mysqli_query($conn, "SELECT p.id AS payment_row_id, p.payment_id AS order_id,
                p.amount, p.currency, b.id AS booking_db_id, b.event_id, b.quantity
            FROM payments p JOIN bookings b ON b.id = p.booking_id
            WHERE p.gateway = 'razorpay' AND p.status = 'pending' AND p.created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
              AND b.status = 'pending' AND b.payment_status = 'pending'
            FOR UPDATE");
        if (!$result) {
            throw new RuntimeException('Could not inspect expired payment reservations.');
        }

        while ($booking = mysqli_fetch_assoc($result)) {
            if (!razorpay_credentials()) {
                error_log('Evento kept an expired Razorpay reservation because gateway credentials are unavailable.');
                continue;
            }
            $order_payments = razorpay_request('GET', 'orders/' . rawurlencode($booking['order_id']) . '/payments');
            $settled_payment = null;
            foreach (($order_payments['items'] ?? []) as $gateway_payment) {
                if (in_array($gateway_payment['status'] ?? '', ['authorized', 'captured'], true)) {
                    $settled_payment = $gateway_payment;
                    break;
                }
            }
            if ($settled_payment) {
                if (($settled_payment['order_id'] ?? '') !== $booking['order_id']
                    || (int)($settled_payment['amount'] ?? 0) !== (int)round((float)$booking['amount'] * 100)
                    || strtoupper((string)($settled_payment['currency'] ?? '')) !== strtoupper($booking['currency'])) {
                    throw new RuntimeException('Expired reservation payment did not match its order.');
                }
                if (($settled_payment['status'] ?? '') === 'authorized') {
                    $settled_payment = razorpay_request(
                        'POST',
                        'payments/' . rawurlencode((string)$settled_payment['id']) . '/capture',
                        [
                            'amount' => (int)round((float)$booking['amount'] * 100),
                            'currency' => $booking['currency'],
                        ]
                    );
                }
                if (($settled_payment['status'] ?? '') === 'captured' && !empty($settled_payment['captured'])) {
                    $response = json_encode([
                        'order_id' => $booking['order_id'],
                        'payment_id' => $settled_payment['id'],
                        'status' => 'captured',
                        'amount' => $settled_payment['amount'],
                        'currency' => $settled_payment['currency'],
                        'verified_at' => date('c'),
                    ], JSON_THROW_ON_ERROR);
                    $payment_id = (string)$settled_payment['id'];
                    $payment_row_id = (int)$booking['payment_row_id'];
                    $stmt = mysqli_prepare($conn, "UPDATE payments SET payment_id = ?, status = 'success', gateway_response = ?, transaction_time = NOW()
                        WHERE id = ? AND status = 'pending'");
                    mysqli_stmt_bind_param($stmt, 'ssi', $payment_id, $response, $payment_row_id);
                    if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
                        throw new RuntimeException('Could not reconcile a captured payment.');
                    }
                    mysqli_stmt_close($stmt);
                    $booking_db_id = (int)$booking['booking_db_id'];
                    $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = 'confirmed', payment_status = 'completed'
                        WHERE id = ? AND status = 'pending' AND payment_status = 'pending'");
                    mysqli_stmt_bind_param($stmt, 'i', $booking_db_id);
                    if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
                        throw new RuntimeException('Could not confirm a captured payment booking.');
                    }
                    mysqli_stmt_close($stmt);
                    continue;
                }
                error_log('Evento kept an expired Razorpay reservation because payment needs gateway reconciliation.');
                continue;
            }

            $payment_row_id = (int)$booking['payment_row_id'];
            $booking_db_id = (int)$booking['booking_db_id'];
            $event_id = (int)$booking['event_id'];
            $quantity = (int)$booking['quantity'];

            $stmt = mysqli_prepare($conn, "UPDATE payments SET status = 'failed', gateway_response = ?
                WHERE id = ? AND status = 'pending'");
            $expired_response = json_encode(['expired' => true, 'expired_at' => date('c')]);
            mysqli_stmt_bind_param($stmt, 'si', $expired_response, $payment_row_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Could not expire a payment order.');
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = 'cancelled', payment_status = 'failed'
                WHERE id = ? AND status = 'pending' AND payment_status = 'pending'");
            mysqli_stmt_bind_param($stmt, 'i', $booking_db_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Could not expire a booking reservation.');
            }
            $was_cancelled = mysqli_stmt_affected_rows($stmt) === 1;
            mysqli_stmt_close($stmt);
            if (!$was_cancelled) {
                continue;
            }

            $stmt = mysqli_prepare($conn, 'UPDATE events SET booked_seats = GREATEST(0, booked_seats - ?) WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'ii', $quantity, $event_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Could not release expired tickets.');
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, 'SELECT coupon_id FROM coupon_usage WHERE booking_id = ? FOR UPDATE');
            mysqli_stmt_bind_param($stmt, 'i', $booking_db_id);
            mysqli_stmt_execute($stmt);
            $usage = mysqli_stmt_get_result($stmt)->fetch_assoc();
            mysqli_stmt_close($stmt);
            if ($usage) {
                $coupon_id = (int)$usage['coupon_id'];
                $stmt = mysqli_prepare($conn, 'DELETE FROM coupon_usage WHERE booking_id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $booking_db_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('Could not release expired coupon usage.');
                }
                mysqli_stmt_close($stmt);
                $stmt = mysqli_prepare($conn, 'UPDATE coupons SET used_count = GREATEST(0, used_count - 1) WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $coupon_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('Could not restore coupon availability.');
                }
                mysqli_stmt_close($stmt);
            }
        }
        mysqli_free_result($result);
        mysqli_commit($conn);
    } catch (Throwable $exception) {
        mysqli_rollback($conn);
        error_log('Evento expired Razorpay reservation cleanup failed: ' . $exception->getMessage());
        throw $exception;
    }
}
