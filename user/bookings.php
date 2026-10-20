<?php
/**
 * EVENTO - User Booking History
 * Complete booking history with all filters, download tickets, cancel
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_auth();

$page_title = 'My Bookings';
$user_id = (int)$_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'all';

if (!in_array($filter, ['all', 'upcoming', 'past', 'cancelled', 'paid'], true)) {
    $filter = 'all';
}

$where = 'WHERE b.user_id = ?';
$types = 'i';
$params = [$user_id];
if ($filter === 'upcoming') {
    $where .= " AND e.event_date >= CURDATE() AND b.status = 'confirmed'";
} elseif ($filter === 'past') {
    $where .= " AND (e.event_date < CURDATE() OR b.status IN ('completed','cancelled','refunded'))";
} elseif ($filter === 'cancelled') {
    $where .= " AND b.status = 'cancelled'";
} elseif ($filter === 'paid') {
    $where .= " AND b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%'";
}

$bookings = fetch_all("SELECT b.*, e.title, e.slug, e.event_date, e.event_time, e.venue, e.banner, ci.city_name
    FROM bookings b JOIN events e ON b.event_id = e.id JOIN cities ci ON e.city_id = ci.id
    $where ORDER BY b.created_at DESC", $types, $params);
$has_demo_booking = fetch_single("SELECT id FROM bookings WHERE user_id = ? AND payment_method LIKE 'DEMO%' LIMIT 1", "i", [$user_id]);

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="padding-top: 100px !important;">
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <h2 class="fw-bold mb-0"><i class="fas fa-ticket-alt me-2 text-primary"></i>My Bookings</h2>
            <a href="<?= SITE_URL ?>user/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="?filter=all" class="btn btn-sm <?= $filter == 'all' ? 'btn-primary' : 'btn-ghost' ?>">All</a>
            <a href="?filter=upcoming" class="btn btn-sm <?= $filter == 'upcoming' ? 'btn-primary' : 'btn-ghost' ?>">Upcoming</a>
            <a href="?filter=past" class="btn btn-sm <?= $filter == 'past' ? 'btn-primary' : 'btn-ghost' ?>">Past</a>
            <a href="?filter=cancelled" class="btn btn-sm <?= $filter == 'cancelled' ? 'btn-primary' : 'btn-ghost' ?>">Cancelled</a>
            <a href="?filter=paid" class="btn btn-sm <?= $filter == 'paid' ? 'btn-primary' : 'btn-ghost' ?>">Paid</a>
        </div>
    </div>

    <?php if ($has_demo_booking): ?>
        <div class="alert alert-info" role="note">
            <i class="fas fa-info-circle me-2"></i>DEMO booking data is for presentation only. No payment was processed and no money was collected.
        </div>
    <?php endif; ?>

    <?php if (count($bookings) > 0): ?>
    <div class="row g-4">
        <?php foreach ($bookings as $b): ?>
        <?php $is_demo_booking = strpos((string)$b['payment_method'], 'DEMO') === 0; ?>
        <div class="col-12">
            <div class="glass-card p-3">
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <img src="<?= event_banner($b['banner']) ?>" 
                         class="rounded-3" width="120" height="90" style="object-fit:cover;"
                         onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=200&q=80'">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($b['title']) ?></h5>
                        <small class="text-muted d-block"><i class="fas fa-calendar me-1"></i><?= format_date($b['event_date']) ?> | <?= date('h:i A', strtotime($b['event_time'])) ?></small>
                        <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($b['venue']) ?>, <?= htmlspecialchars($b['city_name']) ?></small>
                        <small class="text-muted d-block">Booking ID: <strong><?= htmlspecialchars($b['booking_id']) ?></strong>
                            <?php if ($is_demo_booking): ?><span class="badge bg-info-soft">DEMO</span><?php endif; ?>
                        </small>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <div class="fw-bold fs-5 text-gradient mb-2"><?= CURRENCY_SYMBOL ?><?= number_format($b['final_amount']) ?></div>
                        <span class="badge <?= $b['payment_status'] === 'completed' && !$is_demo_booking ? 'bg-success-soft' : ($b['payment_status'] === 'failed' || $b['payment_status'] === 'refunded' ? 'bg-danger-soft' : 'bg-warning-soft') ?> d-block mb-2">
                            <?= $is_demo_booking ? 'DEMO — not paid' : 'Payment ' . htmlspecialchars(ucfirst($b['payment_status'])) ?>
                        </span>
                        <?php
                        $badge_class = 'bg-success-soft';
                        if ($b['status'] == 'cancelled') $badge_class = 'bg-danger-soft';
                        elseif ($b['status'] == 'pending') $badge_class = 'bg-warning-soft';
                        elseif ($b['status'] == 'refunded') $badge_class = 'bg-info-soft';
                        ?>
                        <span class="badge <?= $badge_class ?> d-block mb-2"><?= ucfirst($b['status']) ?></span>
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= SITE_URL ?>booking/confirmation.php?booking_id=<?= $b['booking_id'] ?>" class="btn btn-sm btn-ghost" title="View"><i class="fas fa-eye"></i></a>
                            <a href="<?= SITE_URL ?>booking/download-ticket.php?booking_id=<?= $b['booking_id'] ?>" class="btn btn-sm btn-ghost" title="Download Ticket"><i class="fas fa-download"></i></a>
                            <?php if ($b['status'] == 'confirmed' && strtotime($b['event_date']) > strtotime('today')): ?>
                            <a href="<?= SITE_URL ?>booking/cancel.php?booking_id=<?= $b['booking_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this booking?')" title="Cancel"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-ticket-alt fa-4x text-muted mb-3"></i>
        <h4>No bookings found</h4>
        <p class="text-muted">Browse events and book your first ticket!</p>
        <a href="<?= SITE_URL ?>events.php" class="btn btn-primary"><i class="fas fa-search me-2"></i>Browse Events</a>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
