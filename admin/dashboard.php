<?php
/**
 * EVENTO - Admin Dashboard
 */
require_once __DIR__ . '/admin_auth.php';
$page_title = 'Admin Dashboard';

// Stats
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'user'"))['total'];
$total_organizers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'organizer'"))['total'];
$total_events = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM events"))['total'];
$total_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE status = 'confirmed'"))['total'];
$financial_totals = fetch_single("SELECT COUNT(*) AS paid_bookings, COALESCE(SUM(final_amount), 0) AS collected FROM bookings WHERE status = 'confirmed' AND payment_status = 'completed' AND COALESCE(payment_method, '') NOT LIKE 'DEMO%'");
$paid_bookings = $financial_totals['paid_bookings'];
$pending_events = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM events WHERE status = 'pending'"))['total'];
$pending_organizers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users u LEFT JOIN organizer_requests r ON r.id = (SELECT MAX(id) FROM organizer_requests WHERE user_id = u.id) WHERE u.role = 'organizer' AND u.is_organizer_approved = 0 AND (r.status IS NULL OR r.status = 'pending')"))['total'];
$total_revenue = $financial_totals['collected'];
$today_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE DATE(created_at) = CURDATE()"))['total'];
$has_demo_payment = fetch_single("SELECT id FROM payments WHERE gateway = 'demo' AND gateway_response LIKE 'DEMO ONLY:%' LIMIT 1");

// Recent activities
$recent_activities = mysqli_query($conn, "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10");
$recent_bookings = mysqli_query($conn, "SELECT b.*, e.title, u.full_name FROM bookings b JOIN events e ON b.event_id = e.id JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC LIMIT 10");

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4" style="padding-top: 90px !important;">
    <!-- Dashboard Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-shield-alt me-2 text-primary"></i>Admin Dashboard</h2>
            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($admin['full_name'] ?? 'Admin') ?>! Here's what's happening on Evento.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= SITE_URL ?>admin/manage-events.php" class="btn btn-ghost btn-sm"><i class="fas fa-calendar me-1"></i>Events</a>
            <a href="<?= SITE_URL ?>admin/manage-users.php" class="btn btn-ghost btn-sm"><i class="fas fa-users me-1"></i>Users</a>
            <a href="<?= SITE_URL ?>admin/manage-coupons.php" class="btn btn-warning btn-sm"><i class="fas fa-gift me-1"></i>Coupons</a>
            <a href="<?= SITE_URL ?>admin/settings.php" class="btn btn-primary btn-sm"><i class="fas fa-cog me-1"></i>Settings</a>
        </div>
    </div>
    <?php if ($has_demo_payment): ?>
        <div class="alert alert-info" role="note">
            <i class="fas fa-info-circle me-2"></i>Booking counts can include DEMO presentation bookings; demo entries are excluded from paid-booking and collected-revenue totals.
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a class="stat-card p-3 d-block text-decoration-none text-reset" href="<?= SITE_URL ?>admin/manage-users.php?role=user" title="View all users">
                <div class="stat-icon bg-primary-soft"><i class="fas fa-users text-primary"></i></div>
                <div class="stat-value"><?= number_format($total_users) ?></div>
                <div class="stat-label">Total Users <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a class="stat-card p-3 d-block text-decoration-none text-reset" href="<?= SITE_URL ?>admin/manage-users.php?role=organizer" title="View all organizers">
                <div class="stat-icon bg-info-soft"><i class="fas fa-user-tie text-info"></i></div>
                <div class="stat-value"><?= number_format($total_organizers) ?></div>
                <div class="stat-label">Organizers <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a class="stat-card p-3 d-block text-decoration-none text-reset" href="<?= SITE_URL ?>admin/manage-events.php" title="View all events">
                <div class="stat-icon bg-success-soft"><i class="fas fa-calendar-check text-success"></i></div>
                <div class="stat-value"><?= number_format($total_events) ?></div>
                <div class="stat-label">Total Events <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a class="stat-card p-3 d-block text-decoration-none text-reset" href="<?= SITE_URL ?>admin/manage-bookings.php?status=confirmed" title="View confirmed bookings">
                <div class="stat-icon bg-warning-soft"><i class="fas fa-ticket-alt text-warning"></i></div>
                <div class="stat-value"><?= number_format($total_bookings) ?></div>
                <div class="stat-label">Confirmed Bookings (paid or due) <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
    </div>

    <!-- Second Row: Revenue + Pending -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a class="stat-card p-3 d-block text-decoration-none text-reset" href="<?= SITE_URL ?>admin/manage-bookings.php?status=confirmed&amp;payment=completed&amp;exclude_demo=1" title="View real paid confirmed bookings">
                <div class="stat-icon bg-danger-soft"><i class="fas fa-rupee-sign text-danger"></i></div>
                <div class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($total_revenue) ?></div>
                <div class="stat-label">Collected Revenue · <?= number_format((int)$paid_bookings) ?> paid <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a class="stat-card p-3 d-block text-decoration-none text-reset" href="<?= SITE_URL ?>admin/manage-bookings.php?date=<?= urlencode(date('Y-m-d')) ?>" title="View today's bookings">
                <div class="stat-icon bg-primary-soft"><i class="fas fa-clock text-primary"></i></div>
                <div class="stat-value"><?= number_format($today_bookings) ?></div>
                <div class="stat-label">Bookings Today <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a class="stat-card p-3 d-block text-decoration-none text-reset" href="<?= SITE_URL ?>admin/manage-events.php?status=pending" title="Review pending events">
                <div class="stat-icon bg-warning-soft"><i class="fas fa-hourglass-half text-warning"></i></div>
                <div class="stat-value"><?= number_format($pending_events) ?></div>
                <div class="stat-label">Pending Events <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a class="stat-card p-3 d-block text-decoration-none text-reset" href="<?= SITE_URL ?>admin/manage-users.php?role=organizer&amp;verification=pending" title="Review organizer applications">
                <div class="stat-icon bg-info-soft"><i class="fas fa-user-check text-info"></i></div>
                <div class="stat-value"><?= number_format($pending_organizers) ?></div>
                <div class="stat-label">Pending Organizers <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
    </div>

    <!-- Content Row: Recent Bookings + Recent Activities -->
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="glass-card h-100">
                <div class="card-header-custom d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-shopping-cart me-2 text-success"></i>Recent Bookings</h5>
                    <a href="<?= SITE_URL ?>admin/manage-bookings.php" class="btn btn-ghost btn-sm">View All</a>
                </div>
                <?php if (mysqli_num_rows($recent_bookings) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Event</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($rb = mysqli_fetch_assoc($recent_bookings)): ?>
                            <tr>
                                <td><small class="text-muted"><?= $rb['booking_id'] ?></small></td>
                                <td><small><?= htmlspecialchars(truncate_text($rb['title'], 25)) ?></small></td>
                                <td><small><?= htmlspecialchars($rb['full_name']) ?></small></td>
                                <td><small><?= CURRENCY_SYMBOL ?><?= number_format($rb['final_amount']) ?></small></td>
                                <td><span class="badge bg-success-soft"><?= $rb['status'] ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No bookings yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="glass-card h-100">
                <div class="card-header-custom d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-history me-2 text-primary"></i>Recent Activity</h5>
                </div>
                <?php if (mysqli_num_rows($recent_activities) > 0): ?>
                <div class="activity-list">
                    <?php while ($act = mysqli_fetch_assoc($recent_activities)): ?>
                    <div class="d-flex align-items-start gap-2 mb-3">
                        <div class="activity-dot mt-1"></div>
                        <div class="flex-grow-1">
                            <strong class="d-block small"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $act['action']))) ?></strong>
                            <small class="text-muted"><?= htmlspecialchars(truncate_text($act['description'], 60)) ?></small>
                            <small class="text-muted d-block"><?= time_elapsed($act['created_at']) ?></small>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No recent activity</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
