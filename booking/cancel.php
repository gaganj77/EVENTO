<?php
/**
 * EVENTO - Cancel Booking
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_auth();

$booking_id = sanitize_input($_GET['booking_id'] ?? '');
if (empty($booking_id)) {
    header('Location: ' . SITE_URL . 'user/bookings.php');
    exit();
}

$booking = fetch_single("SELECT b.*, e.title, e.event_date FROM bookings b JOIN events e ON b.event_id = e.id WHERE b.booking_id = ? AND b.user_id = ?", "si", [$booking_id, $_SESSION['user_id']]);

if (!$booking) {
    $_SESSION['error'] = 'Booking not found';
    header('Location: ' . SITE_URL . 'user/bookings.php');
    exit();
}

if ($booking['status'] == 'cancelled') {
    $_SESSION['error'] = 'Booking is already cancelled';
    header('Location: ' . SITE_URL . 'user/bookings.php');
    exit();
}

if (strtotime($booking['event_date']) < strtotime('today')) {
    $_SESSION['error'] = 'Cannot cancel past events';
    header('Location: ' . SITE_URL . 'user/bookings.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = sanitize_input($_POST['reason'] ?? '');
    
    mysqli_query($conn, "UPDATE bookings SET status = 'cancelled', cancelled_at = NOW(), refund_requested = 1, refund_reason = '$reason' WHERE booking_id = '$booking_id'");
    
    // Free up seats
    mysqli_query($conn, "UPDATE events SET booked_seats = booked_seats - {$booking['quantity']} WHERE id = {$booking['event_id']}");
    
    create_notification($_SESSION['user_id'], 'cancellation', 'Booking Cancelled', 'Your booking ' . $booking_id . ' for ' . $booking['title'] . ' has been cancelled.', SITE_URL . 'user/bookings.php');
    
    $_SESSION['info'] = 'Booking cancelled successfully. Refund will be processed within 5-7 business days.';
    header('Location: ' . SITE_URL . 'user/bookings.php');
    exit();
}

$page_title = 'Cancel Booking';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="padding-top: 100px !important;">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="glass-card text-center" data-aos="fade-up">
                <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                <h3 class="fw-bold">Cancel Booking?</h3>
                <p class="text-muted">Are you sure you want to cancel your booking for <strong><?= htmlspecialchars($booking['title']) ?></strong>?</p>
                <p class="text-muted">Booking ID: <strong><?= $booking_id ?></strong></p>
                
                <form method="POST">
                    <div class="mb-3">
                        <textarea name="reason" class="form-control" rows="3" placeholder="Reason for cancellation (optional)"></textarea>
                    </div>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="<?= SITE_URL ?>user/bookings.php" class="btn btn-ghost"><i class="fas fa-arrow-left me-2"></i>Go Back</a>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-times me-2"></i>Confirm Cancellation</button>
                    </div>
                </form>
            </div>
    </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
