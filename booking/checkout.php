<?php
/**
 * EVENTO - Checkout Page
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/razorpay.php';
require_booking_auth();

$page_title = 'Checkout';
$error = '';
$razorpay_enabled = razorpay_credentials() !== null;
try {
    razorpay_expire_stale_bookings($conn);
} catch (Throwable $exception) {
    error_log('Evento checkout reservation cleanup failed: ' . $exception->getMessage());
    $error = 'Could not refresh ticket reservations. Please reload checkout in a moment.';
}
$event_id = (int)($_POST['event_id'] ?? $_GET['event_id'] ?? 0);
$quantity = max(1, (int)($_POST['quantity'] ?? 1));

if ($event_id <= 0) {
    header('Location: ' . SITE_URL . 'events.php');
    exit();
}

$event = fetch_single("SELECT e.*, c.category_name, ci.city_name FROM events e JOIN categories c ON e.category_id = c.id JOIN cities ci ON e.city_id = ci.id WHERE e.id = ? AND e.status = 'approved'", "i", [$event_id]);

if (isset($_SESSION['coupon_event_id']) && (int)$_SESSION['coupon_event_id'] !== $event_id) {
    unset($_SESSION['coupon'], $_SESSION['coupon_event_id']);
}

if (!$event || $event['available_seats'] < $quantity) {
    $_SESSION['error'] = 'Event not available or insufficient seats';
    header('Location: ' . SITE_URL . 'events.php');
    exit();
}

$price = $event['discount_price'] > 0 ? $event['discount_price'] : $event['price'];
$total = $price * $quantity;
$discount = 0;
$coupon_msg = '';

// Coupon selection is explicit: normal event booking/open/refresh must not reuse it.
$is_coupon_action = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coupon']) && trim((string)$_POST['coupon']) !== '';
$is_order_submission = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Your session expired. Refresh checkout and try again.';
    $is_coupon_action = false;
    $is_order_submission = false;
}
if (!$is_coupon_action && !$is_order_submission && isset($_SESSION['coupon'])) {
    unset($_SESSION['coupon'], $_SESSION['coupon_event_id']);
}

// Apply coupon
if ($is_coupon_action && !$is_order_submission) {
    unset($_SESSION['coupon'], $_SESSION['coupon_event_id']);
    $coupon_code = sanitize_input($_POST['coupon']);
    $coupon = fetch_single("SELECT c.* FROM coupons c WHERE c.code = ? AND c.status = 1 AND c.valid_from <= NOW() AND c.valid_to >= NOW() AND (c.event_id IS NULL OR c.event_id = ?) AND (c.user_id IS NULL OR c.user_id = ? OR EXISTS (SELECT 1 FROM users coupon_owner WHERE coupon_owner.id = c.user_id AND coupon_owner.role = 'organizer')) AND NOT EXISTS (SELECT 1 FROM coupon_usage cu WHERE cu.coupon_id = c.id AND cu.user_id = ?)", "siii", [$coupon_code, $event_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    
    if ($coupon) {
        $has_previous_booking = fetch_single("SELECT id FROM bookings WHERE user_id = ? AND status <> 'cancelled' LIMIT 1", "i", [$_SESSION['user_id']]);
        if (in_array($coupon_code, ['EVENTO26', 'EGROUP26'], true) && $has_previous_booking) {
            $error = 'This welcome coupon is only valid on your first booking.';
        } elseif ($quantity < (int)$coupon['min_quantity']) {
            $error = 'This coupon requires at least ' . (int)$coupon['min_quantity'] . ' tickets.';
        } elseif ($total >= $coupon['min_order_amount']) {
            if ($coupon['discount_type'] == 'percentage') {
                $discount = ($total * $coupon['discount_value']) / 100;
                if ($coupon['max_discount_amount'] > 0 && $discount > $coupon['max_discount_amount']) {
                    $discount = $coupon['max_discount_amount'];
                }
            } else {
                $discount = $coupon['discount_value'];
            }
            $_SESSION['coupon'] = $coupon;
            $_SESSION['coupon_event_id'] = $event_id;
            $coupon_msg = 'Coupon applied! You saved ' . CURRENCY_SYMBOL . number_format($discount);
        } else {
            $error = 'Minimum order amount is ' . CURRENCY_SYMBOL . number_format($coupon['min_order_amount']);
        }
    } else {
        $error = 'Invalid or expired coupon';
    }
}

$final_total = $total - $discount;

if (isset($_SESSION['coupon'], $_SESSION['coupon_event_id']) && (int)$_SESSION['coupon_event_id'] === $event_id && $discount == 0) {
    $coupon = $_SESSION['coupon'];
    if ($quantity >= (int)$coupon['min_quantity'] && $total >= $coupon['min_order_amount']) {
        $discount = $coupon['discount_type'] === 'percentage' ? ($total * $coupon['discount_value']) / 100 : $coupon['discount_value'];
        if ($coupon['max_discount_amount'] > 0) {
            $discount = min($discount, $coupon['max_discount_amount']);
        }
        $coupon_msg = 'Welcome coupon applied! You saved ' . CURRENCY_SYMBOL . number_format($discount);
        $final_total = $total - $discount;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $payment_method = sanitize_input($_POST['payment_method'] ?? 'razorpay');
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Refresh checkout and try again.';
    } elseif ($payment_method !== 'cash') {
        $error = 'Online checkout is handled securely in the payment window. Please retry from the checkout button.';
    } else {
        $booking_id = generate_booking_id();
        
        $stmt = mysqli_prepare($conn, "INSERT INTO bookings (booking_id, user_id, event_id, total_amount, discount_amount, coupon_code, final_amount, quantity, status, payment_status, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'pending', ?)");
        $coupon_code_used = isset($_SESSION['coupon']) ? $_SESSION['coupon']['code'] : '';
        // NOTE: types must match the column order exactly:
        // booking_id(s) user_id(i) event_id(i) total(d) discount(d) coupon_code(s) final(d) quantity(i) payment_method(s)
        mysqli_stmt_bind_param($stmt, "siiddsdis", $booking_id, $_SESSION['user_id'], $event_id, $total, $discount, $coupon_code_used, $final_total, $quantity, $payment_method);
        
        if (mysqli_stmt_execute($stmt)) {
            $booking_db_id = mysqli_insert_id($conn);
        
            // Update booked seats
            mysqli_query($conn, "UPDATE events SET booked_seats = booked_seats + $quantity WHERE id = $event_id");
        
            // Update coupon usage
            if (isset($_SESSION['coupon'])) {
                mysqli_query($conn, "UPDATE coupons SET used_count = used_count + 1 WHERE id = " . $_SESSION['coupon']['id']);
                $stmt2 = mysqli_prepare($conn, "INSERT INTO coupon_usage (coupon_id, user_id, booking_id) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt2, "iii", $_SESSION['coupon']['id'], $_SESSION['user_id'], $booking_db_id);
                mysqli_stmt_execute($stmt2);
                unset($_SESSION['coupon']);
                unset($_SESSION['coupon_event_id']);
                unset($_SESSION['welcome_coupon'], $_SESSION['welcome_coupon_notice']);
                $_SESSION['welcome_coupon_seen'] = true;
            }

            // Cash-on-arrival bookings remain unpaid until the admin records receipt.
            $payment_id = 'PAY' . strtoupper(uniqid());
            $payment_gateway_status = 'pending';
            $stmt3 = mysqli_prepare($conn, "INSERT INTO payments (booking_id, payment_id, gateway, amount, status, transaction_time) VALUES (?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt3, "issds", $booking_db_id, $payment_id, $payment_method, $final_total, $payment_gateway_status);
            mysqli_stmt_execute($stmt3);
        
            $_SESSION['success'] = 'Booking confirmed! Your booking ID is ' . $booking_id;
            header('Location: ' . SITE_URL . 'booking/confirmation.php?booking_id=' . $booking_id);
            exit();
        } else {
            $error = 'Booking failed. Please try again.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="padding-top: 100px !important;">
    <h2 class="fw-bold mb-4"><i class="fas fa-shopping-cart me-2 text-primary"></i>Checkout</h2>
    
    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="row g-4">
        <!-- LEFT: Order Summary & Payment -->
        <div class="col-lg-8">
            <div class="glass-card">
                <h5 class="fw-bold mb-3">Order Summary</h5>
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <img src="<?= event_banner($event['banner']) ?>" 
                         class="rounded-3" width="100" height="75" style="object-fit:cover;"
                         onerror="this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=200&q=80'">
                    <div>
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($event['title']) ?></h5>
                        <small class="text-muted d-block"><i class="fas fa-calendar me-1"></i><?= format_date($event['event_date']) ?></small>
                        <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($event['venue']) ?>, <?= htmlspecialchars($event['city_name']) ?></small>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mb-2">
                    <span>Price per ticket</span>
                    <strong><?= CURRENCY_SYMBOL ?><?= number_format($price) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Quantity</span>
                    <strong><?= $quantity ?></strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal</span>
                    <strong><?= CURRENCY_SYMBOL ?><?= number_format($total) ?></strong>
                </div>
                <?php if ($discount > 0): ?>
                <div class="d-flex justify-content-between mb-2 text-success">
                    <span>Discount</span>
                    <strong>-<?= CURRENCY_SYMBOL ?><?= number_format($discount) ?></strong>
                </div>
                <?php endif; ?>
                <hr>
                <div class="d-flex justify-content-between fs-5 fw-bold">
                    <span>Total</span>
                    <span class="text-gradient"><?= CURRENCY_SYMBOL ?><?= number_format($final_total) ?></span>
                </div>
            </div>

            <!-- Coupon -->
            <div class="glass-card mt-3">
                <h5 class="fw-bold mb-3"><i class="fas fa-tag me-2 text-warning"></i>Apply Coupon</h5>
                <?php if ($coupon_msg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($coupon_msg) ?></div>
                <?php endif; ?>
                <form method="POST" id="couponForm" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <input type="hidden" name="quantity" value="<?= $quantity ?>">
                    <input type="text" name="coupon" id="couponCode" class="form-control" placeholder="Select a coupon or enter code" autocomplete="off">
                    <button type="submit" class="btn btn-ghost">Apply</button>
                </form>
                <div class="coupon-choices mt-3">
                    <button type="button" class="coupon-choice btn btn-warning w-100" data-coupon="EVENTO26">
                        <i class="fas fa-gift me-2"></i>Select EVENTO26: 10% off
                        <small class="d-block">For your first booking</small>
                    </button>
                    <button type="button" class="coupon-choice btn btn-success w-100 mt-2" data-coupon="EGROUP26">
                        <i class="fas fa-users me-2"></i>Select EGROUP26: 20% off
                        <small class="d-block">For 3 or more tickets on first booking</small>
                    </button>
                </div>
                <div id="couponSelectionMessage" class="alert alert-warning mt-2 d-none mb-0"></div>
            </div>

            <!-- Payment Method -->
            <div class="glass-card mt-3">
                <h5 class="fw-bold mb-3"><i class="fas fa-credit-card me-2 text-info"></i>Payment Method</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="d-block p-3 border rounded-3 cursor-pointer" style="border-color: var(--primary) !important;">
                            <input type="radio" name="payment_method" value="razorpay" <?= $razorpay_enabled ? '' : 'disabled' ?> class="me-2">
                            <i class="fas fa-bolt me-1"></i> Razorpay<?= $razorpay_enabled ? '' : ' (temporarily unavailable)' ?>
                        </label>
                        <small class="text-muted"><?= $razorpay_enabled ? 'Secure card, UPI and netbanking checkout.' : 'Online payment is disabled until a verified payment gateway is configured.' ?></small>
                    </div>
                    <div class="col-md-4">
                        <label class="d-block p-3 border rounded-3 cursor-pointer">
                            <input type="radio" name="payment_method" value="cash" checked class="me-2">
                            <i class="fas fa-money-bill me-1"></i> Cash
                        </label>
                    </div>
                </div>
                
                <form method="POST" id="checkoutForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <input type="hidden" name="quantity" value="<?= $quantity ?>">
                    <?php if (isset($_SESSION['coupon']) && (int)($_SESSION['coupon_event_id'] ?? 0) === $event_id): ?>
                    <input type="hidden" name="coupon" value="<?= $_SESSION['coupon']['code'] ?>">
                    <?php endif; ?>
                    <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="cash">
                    
                    <button type="submit" name="place_order" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-check me-2"></i><span id="checkoutButtonLabel">Confirm Cash Booking</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- RIGHT: Order Details -->
        <div class="col-lg-4">
            <div class="glass-card sticky-top" style="top: 90px;">
                <h5 class="fw-bold mb-3">Order Details</h5>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Event</span>
                    <strong><?= htmlspecialchars(truncate_text($event['title'], 30)) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Date</span>
                    <strong><?= format_date($event['event_date']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Tickets</span>
                    <strong><?= $quantity ?></strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal</span>
                    <strong><?= CURRENCY_SYMBOL ?><?= number_format($total) ?></strong>
                </div>
                <?php if ($discount > 0): ?>
                <div class="d-flex justify-content-between mb-2 text-success">
                    <span class="text-muted">Discount</span>
                    <strong>-<?= CURRENCY_SYMBOL ?><?= number_format($discount) ?></strong>
                </div>
                <?php endif; ?>
                <hr>
                <div class="d-flex justify-content-between fs-5 fw-bold">
                    <span>Total</span>
                    <span class="text-gradient"><?= CURRENCY_SYMBOL ?><?= number_format($final_total) ?></span>
                </div>
                <a href="<?= SITE_URL ?>events.php" class="btn btn-ghost btn-sm w-100 mt-3">
                    <i class="fas fa-arrow-left me-1"></i>Continue Browsing
                </a>
                <a href="<?= SITE_URL ?>event.php?slug=<?= urlencode($event['slug'] ?? '') ?>&quantity=<?= $quantity ?>" class="btn btn-outline-secondary btn-sm w-100 mt-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to This Event
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($razorpay_enabled): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var selectionMessage = document.getElementById('couponSelectionMessage');
    var couponCode = document.getElementById('couponCode');
    document.querySelectorAll('.coupon-choice').forEach(function(choice) {
        choice.addEventListener('click', function() {
            if (this.dataset.coupon === 'EGROUP26' && <?= $quantity ?> < 3) {
                if (selectionMessage) {
                    selectionMessage.textContent = 'Please select 3 persons/tickets first to use EGROUP26.';
                    selectionMessage.classList.remove('d-none');
                }
                return;
            }
            if (selectionMessage) selectionMessage.classList.add('d-none');
            if (couponCode) couponCode.value = this.dataset.coupon;
            document.querySelectorAll('.coupon-choice').forEach(function(item) {
                item.classList.remove('coupon-selected');
            });
            this.classList.add('coupon-selected');
        });
    });

    document.querySelectorAll('input[name="payment_method"]').forEach(function(input) {
        input.addEventListener('change', function() {
            document.getElementById('selectedPaymentMethod').value = this.value;
            var label = document.getElementById('checkoutButtonLabel');
            if (label) label.textContent = this.value === 'razorpay' ? 'Pay Securely' : 'Confirm Cash Booking';
        });
    });

    var checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(event) {
            if (document.getElementById('selectedPaymentMethod').value !== 'razorpay') return;
            event.preventDefault();
            var submitButton = checkoutForm.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;
            var formData = new FormData(checkoutForm);
            if (typeof Razorpay === 'undefined') {
                alert('The secure payment window did not load. Please try again or choose Cash.');
                if (submitButton) submitButton.disabled = false;
                return;
            }

            fetch('<?= SITE_URL ?>booking/create-payment-order.php', { method: 'POST', body: formData })
                .then(function(response) { return response.json().then(function(data) { return { ok: response.ok, data: data }; }); })
                .then(function(result) {
                    if (!result.ok || result.data.status !== 'ok') throw new Error(result.data.message || 'Could not start payment.');
                    var order = result.data;
                    var csrfToken = formData.get('csrf_token');
                    var checkout = new Razorpay({
                        key: order.key,
                        amount: order.amount,
                        currency: order.currency,
                        name: order.name,
                        description: order.description,
                        order_id: order.order_id,
                        handler: function(response) {
                            var verifyData = new FormData();
                            verifyData.append('csrf_token', csrfToken);
                            verifyData.append('razorpay_order_id', response.razorpay_order_id);
                            verifyData.append('razorpay_payment_id', response.razorpay_payment_id);
                            verifyData.append('razorpay_signature', response.razorpay_signature);
                            fetch('<?= SITE_URL ?>booking/verify-payment.php', { method: 'POST', body: verifyData })
                                .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
                                .then(function(verified) {
                                    if (!verified.ok || verified.data.status !== 'ok') throw new Error(verified.data.message || 'Payment verification failed.');
                                    window.location.href = '<?= SITE_URL ?>booking/confirmation.php?booking_id=' + encodeURIComponent(verified.data.booking_id);
                                })
                                .catch(function(error) { alert(error.message); if (submitButton) submitButton.disabled = false; });
                        },
                        modal: {
                            ondismiss: function() {
                                if (submitButton) submitButton.disabled = false;
                            }
                        }
                    });
                    checkout.on('payment.failed', function(response) {
                        var message = response.error && response.error.description
                            ? response.error.description
                            : 'Payment failed. Your ticket reservation will remain pending while payment status is checked.';
                        alert(message);
                        if (submitButton) submitButton.disabled = false;
                    });
                    checkout.open();
                })
                .catch(function(error) {
                    alert(error.message);
                    if (submitButton) submitButton.disabled = false;
                });
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
