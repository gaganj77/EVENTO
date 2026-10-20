<?php
/**
 * EVENTO - QR Code Verify Page (for organizers)
 * Scans and verifies booking QR codes at event entrance
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$booking_id = sanitize_input($_GET['booking_id'] ?? '');
$message = '';

if (!empty($booking_id)) {
    $booking = fetch_single(
        "SELECT b.*, e.title, e.event_date, u.full_name as user_name 
         FROM bookings b 
         JOIN events e ON b.event_id = e.id 
         JOIN users u ON b.user_id = u.id 
         WHERE b.booking_id = ?",
        "s", [$booking_id]
    );
    
    if ($booking) {
        if ($booking['status'] === 'confirmed') {
            // Mark as checked in
            if (isset($_GET['checkin'])) {
                mysqli_query($conn, "UPDATE bookings SET checked_in = 1 WHERE booking_id = '$booking_id'");
                $message = '<div class="alert alert-success">✅ Check-in successful! Welcome ' . htmlspecialchars($booking['user_name']) . '!</div>';
            }
            
            $message .= '<div class="alert alert-success">✅ Valid Booking - ' . htmlspecialchars($booking['booking_id']) . '</div>';
            $message .= '<div class="glass-card mt-3"><h5>' . htmlspecialchars($booking['title']) . '</h5>';
            $message .= '<p>Attendee: <strong>' . htmlspecialchars($booking['user_name']) . '</strong></p>';
            $message .= '<p>Tickets: ' . $booking['quantity'] . '</p>';
            $message .= '<p>Date: ' . format_date($booking['event_date']) . '</p>';
            $message .= '<p>Checked In: ' . ($booking['checked_in'] ? 'Yes ✅' : 'No ❌') . '</p>';
            if (!$booking['checked_in']) {
                $message .= '<a href="?booking_id=' . $booking_id . '&checkin=1" class="btn btn-success"><i class="fas fa-check me-2"></i>Check In</a>';
            }
            $message .= '</div>';
        } else {
            $message = '<div class="alert alert-danger">❌ Invalid or Cancelled Booking</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">❌ Booking not found</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Booking - <?= SITE_NAME ?></title>
    <link href="<?= SITE_URL ?>css/bootstrap.min.css" rel="stylesheet">
    <link href="" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>css/style.css">
</head>
<body>
    <div class="container py-5 text-center" style="padding-top: 100px !important;">
        <h2 class="fw-bold mb-4"><i class="fas fa-qrcode me-2 text-primary"></i>Booking Verification</h2>
        <?= $message ?: '<p class="text-muted">Scan a QR code to verify a booking</p>' ?>
        <a href="<?= SITE_URL ?>" class="btn btn-ghost mt-3"><i class="fas fa-arrow-left me-2"></i>Back to Home</a>
    </div>
    <script src="<?= SITE_URL ?>js/bootstrap.bundle.min.js"></script>
</body>
</html>
