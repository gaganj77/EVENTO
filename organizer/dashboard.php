<?php
/**
 * EVENTO - Organizer Dashboard
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_organizer();

$page_title = 'Organizer Dashboard';
$user_id = (int)$_SESSION['user_id'];

// Stats
$total_events = fetch_single("SELECT COUNT(*) as total FROM events WHERE user_id = ?", "i", [$user_id])['total'];
$approved_events = fetch_single("SELECT COUNT(*) as total FROM events WHERE user_id = ? AND status = 'approved'", "i", [$user_id])['total'];
$pending_events = fetch_single("SELECT COUNT(*) as total FROM events WHERE user_id = ? AND status = 'pending'", "i", [$user_id])['total'];
$total_bookings = fetch_single("SELECT COUNT(*) as total FROM bookings b JOIN events e ON b.event_id = e.id WHERE e.user_id = ? AND b.status = 'confirmed'", "i", [$user_id])['total'];
$financial_totals = fetch_single("SELECT COUNT(*) AS paid_bookings, COALESCE(SUM(b.final_amount), 0) AS collected FROM bookings b JOIN events e ON b.event_id = e.id WHERE e.user_id = ? AND b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%'", "i", [$user_id]);
$paid_bookings = $financial_totals['paid_bookings'];
$total_revenue = $financial_totals['collected'];
$has_demo_booking = fetch_single("SELECT b.id FROM bookings b JOIN events e ON e.id = b.event_id WHERE e.user_id = ? AND b.payment_method LIKE 'DEMO%' LIMIT 1", "i", [$user_id]);

$recent_events = fetch_all("SELECT * FROM events WHERE user_id = ? ORDER BY created_at DESC LIMIT 5", "i", [$user_id]);
$recent_bookings = fetch_all("SELECT b.*, e.title, e.event_date, e.slug, u.full_name as user_name FROM bookings b JOIN events e ON b.event_id = e.id JOIN users u ON b.user_id = u.id WHERE e.user_id = ? ORDER BY b.created_at DESC LIMIT 10", "i", [$user_id]);

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="padding-top: 100px !important;">
    <!-- Dashboard Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-chart-line me-2 text-primary"></i>Organizer Dashboard</h2>
            <p class="text-muted mb-0">Manage your events and track performance</p>
        </div>
        <a href="<?= SITE_URL ?>organizer/create-event.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create Event
        </a>
        <a href="<?= SITE_URL ?>organizer/manage-coupons.php" class="btn btn-warning">
            <i class="fas fa-gift me-2"></i>Coupons
        </a>
    </div>
    <?php if ($has_demo_booking): ?>
        <div class="alert alert-info" role="note">
            <i class="fas fa-info-circle me-2"></i>Booking counts can include DEMO presentation bookings; demo entries are excluded from paid-booking and revenue totals.
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="<?= SITE_URL ?>organizer/my-events.php" class="stat-card p-3 d-block text-decoration-none text-reset" title="View all your events">
                <div class="stat-icon bg-primary-soft"><i class="fas fa-calendar text-primary"></i></div>
                <div class="stat-value"><?= $total_events ?></div>
                <div class="stat-label">Total Events <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?= SITE_URL ?>organizer/my-events.php?status=approved" class="stat-card p-3 d-block text-decoration-none text-reset" title="View your approved events">
                <div class="stat-icon bg-success-soft"><i class="fas fa-check-circle text-success"></i></div>
                <div class="stat-value"><?= $approved_events ?></div>
                <div class="stat-label">Approved <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?= SITE_URL ?>organizer/my-events.php?status=pending" class="stat-card p-3 d-block text-decoration-none text-reset" title="View events waiting for review">
                <div class="stat-icon bg-warning-soft"><i class="fas fa-hourglass-half text-warning"></i></div>
                <div class="stat-value"><?= $pending_events ?></div>
                <div class="stat-label">Pending Review <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?= SITE_URL ?>organizer/manage-bookings.php?status=confirmed" class="stat-card p-3 d-block text-decoration-none text-reset" title="View your confirmed bookings">
                <div class="stat-icon bg-info-soft"><i class="fas fa-ticket-alt text-info"></i></div>
                <div class="stat-value"><?= $total_bookings ?></div>
                <div class="stat-label">Confirmed Bookings (paid or due) <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?= SITE_URL ?>organizer/manage-bookings.php?status=confirmed&amp;payment=completed&amp;exclude_demo=1" class="stat-card p-3 d-block text-decoration-none text-reset" title="View real paid bookings counted in revenue">
                <div class="stat-icon bg-warning-soft"><i class="fas fa-rupee-sign text-warning"></i></div>
                <div class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($total_revenue) ?></div>
                <div class="stat-label">Collected Revenue · <?= number_format((int)$paid_bookings) ?> paid <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
    </div>

    <!-- Content Row: Your Events + Recent Bookings -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="glass-card h-100">
                <div class="card-header-custom d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-calendar me-2 text-primary"></i>Your Events</h5>
                    <a href="<?= SITE_URL ?>organizer/my-events.php" class="btn btn-ghost btn-sm">View All</a>
                </div>
                <?php if (count($recent_events) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_events as $ev): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent border-bottom">
                        <div>
                            <strong><?= htmlspecialchars($ev['title']) ?></strong>
                            <small class="d-block text-muted"><?= format_date($ev['event_date']) ?></small>
                        </div>
                        <span class="badge <?= $ev['status'] == 'approved' ? 'bg-success-soft' : ($ev['status'] == 'pending' ? 'bg-warning-soft' : 'bg-danger-soft') ?>"><?= ucfirst($ev['status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?= SITE_URL ?>organizer/my-events.php" class="btn btn-ghost btn-sm mt-3">View All Events <i class="fas fa-arrow-right ms-2"></i></a>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-3">No events yet. Create your first event!</p>
                    <a href="<?= SITE_URL ?>organizer/create-event.php" class="btn btn-primary btn-sm">Create Event</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="glass-card h-100">
                <div class="card-header-custom d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-shopping-cart me-2 text-success"></i>Recent Bookings</h5>
                    <a href="<?= SITE_URL ?>organizer/manage-bookings.php" class="btn btn-ghost btn-sm">View All</a>
                </div>
                <?php if (count($recent_bookings) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Event</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $rb): ?>
                            <tr>
                                <td><small><?= htmlspecialchars($rb['booking_id']) ?></small></td>
                                <td><small><?= htmlspecialchars(truncate_text($rb['title'], 20)) ?></small></td>
                                <td><small><?= CURRENCY_SYMBOL ?><?= number_format($rb['final_amount']) ?></small></td>
                                <td><span class="badge <?= $rb['status'] === 'confirmed' ? 'bg-success-soft' : ($rb['status'] === 'cancelled' || $rb['status'] === 'refunded' ? 'bg-danger-soft' : 'bg-warning-soft') ?>"><?= htmlspecialchars(ucfirst($rb['status'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No bookings yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
