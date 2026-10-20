<?php
/**
 * EVENTO - Booking Confirmation Page
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

$booking = fetch_single(
    "SELECT b.*, e.title, e.slug, e.event_date, e.event_time, e.venue, e.banner, e.description, 
     c.category_name, ci.city_name, u.full_name as organizer_name 
     FROM bookings b 
     JOIN events e ON b.event_id = e.id 
     JOIN categories c ON e.category_id = c.id 
     JOIN cities ci ON e.city_id = ci.id 
     JOIN users u ON e.user_id = u.id 
     WHERE b.booking_id = ? AND b.user_id = ?",
    "si", [$booking_id, $_SESSION['user_id']]
);

if (!$booking) {
    $_SESSION['error'] = 'Booking not found';
    header('Location: ' . SITE_URL . 'user/bookings.php');
    exit();
}

$amount_label = $booking['payment_status'] === 'completed'
    ? 'Total Paid'
    : ($booking['payment_status'] === 'pending' ? 'Amount Due at Venue' : 'Total Amount');

$page_title = 'Booking Confirmation';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="padding-top: 100px !important;">
    <div class="text-center mb-5" data-aos="fade-up">
        <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;border-radius:50%;background:rgba(16,185,129,0.15);">
            <i class="fas fa-check-circle text-success" style="font-size:2.5rem;"></i>
        </div>
        <h2 class="fw-bold">Booking Confirmed!</h2>
        <p class="text-muted">Your tickets have been booked successfully</p>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="glass-card" data-aos="fade-up">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Booking Details</h5>
                    <span class="badge bg-success-soft fs-6"><?= ucfirst($booking['status']) ?></span>
                </div>
                
<div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block">Booking ID</small>
                        <strong><?= $booking['booking_id'] ?></strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block">Date</small>
                        <strong><?= format_date($booking['created_at']) ?></strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block">Quantity</small>
                        <strong><?= $booking['quantity'] ?> ticket(s)</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block"><?= htmlspecialchars($amount_label) ?></small>
                        <strong class="text-gradient"><?= CURRENCY_SYMBOL ?><?= number_format($booking['final_amount']) ?></strong>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex align-items-start gap-3 mb-3">
                    <img src="<?= event_banner($booking['banner']) ?>" 
                         class="rounded-3" width="120" height="90" style="object-fit:cover;"
                         onerror="this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=200&q=80'">
                    <div>
                        <h5 class="fw-bold"><?= htmlspecialchars($booking['title']) ?></h5>
                        <small class="text-muted d-block"><i class="fas fa-calendar me-1"></i><?= format_date($booking['event_date'], 'l, d F Y') ?></small>
                        <small class="text-muted d-block"><i class="fas fa-clock me-1"></i><?= date('h:i A', strtotime($booking['event_time'])) ?></small>
                        <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($booking['venue']) ?>, <?= htmlspecialchars($booking['city_name']) ?></small>
                    </div>
                </div>
            </div>
            
            <!-- QR Code -->
            <div class="glass-card text-center mt-3" data-aos="fade-up">
                <h5 class="fw-bold mb-3">Your Ticket QR Code</h5>
                <div id="qrcode" class="d-inline-block p-3 bg-white rounded-3"></div>
                <p class="text-muted small mt-2">Show this QR code at the event entrance</p>
            </div>
            
            <div class="d-flex gap-3 mt-4" data-aos="fade-up">
                <a href="<?= SITE_URL ?>booking/download-ticket.php?booking_id=<?= $booking['booking_id'] ?>" class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>Download Ticket (PDF)
                </a>
                <a href="<?= SITE_URL ?>user/bookings.php" class="btn btn-ghost">
                    <i class="fas fa-arrow-left me-2"></i>Back to Bookings
                </a>
            </div>
        </div>
    </div>

<script src="<?= SITE_URL ?>js/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById("qrcode"), {
    text: "<?= SITE_URL ?>booking/verify.php?booking_id=<?= $booking['booking_id'] ?>",
    width: 200,
    height: 200,
    colorDark: "#6C3CE1",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
