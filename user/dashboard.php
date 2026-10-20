<?php
/**
 * EVENTO - User Dashboard
 * Professional dashboard with proper structure, stats, bookings and wishlist
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_auth();

$page_title = 'My Dashboard';
$user = get_logged_in_user();
$user_id = (int)$user['id'];

// Get stats
$total_bookings = fetch_single("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?", "i", [$user_id])['total'];
$active_bookings = fetch_single("SELECT COUNT(*) as total FROM bookings b JOIN events e ON e.id = b.event_id WHERE b.user_id = ? AND b.status = 'confirmed' AND e.event_date >= CURDATE()", "i", [$user_id])['total'];
$past_bookings = fetch_single("SELECT COUNT(*) as total FROM bookings b JOIN events e ON e.id = b.event_id WHERE b.user_id = ? AND (e.event_date < CURDATE() OR b.status IN ('completed','cancelled','refunded'))", "i", [$user_id])['total'];
$financial_totals = fetch_single("SELECT COUNT(*) AS paid_bookings, COALESCE(SUM(final_amount), 0) AS spent FROM bookings WHERE user_id = ? AND status = 'confirmed' AND payment_status = 'completed' AND COALESCE(payment_method, '') NOT LIKE 'DEMO%'", "i", [$user_id]);
$paid_bookings = $financial_totals['paid_bookings'];
$total_spent = $financial_totals['spent'];
$has_demo_booking = fetch_single("SELECT id FROM bookings WHERE user_id = ? AND payment_method LIKE 'DEMO%' LIMIT 1", "i", [$user_id]);

// Upcoming events (booked)
$upcoming_events = fetch_all("SELECT b.*, e.title, e.slug, e.event_date, e.event_time, e.venue, e.banner, ci.city_name 
    FROM bookings b JOIN events e ON b.event_id = e.id JOIN cities ci ON e.city_id = ci.id 
    WHERE b.user_id = ? AND b.status = 'confirmed' AND e.event_date >= CURDATE() 
    ORDER BY e.event_date ASC LIMIT 5", "i", [$user_id]);

// Recent bookings
$recent_bookings = fetch_all("SELECT b.*, e.title, e.slug, e.event_date, e.venue, e.banner, ci.city_name 
    FROM bookings b JOIN events e ON b.event_id = e.id JOIN cities ci ON e.city_id = ci.id 
    WHERE b.user_id = ? ORDER BY b.created_at DESC LIMIT 5", "i", [$user_id]);

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="padding-top: 100px !important;">
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-0">Welcome, <?= htmlspecialchars($user['full_name']) ?>! 👋</h2>
            <p class="text-muted mb-0 mt-1">Here's what's happening with your events</p>
        </div>
        <a href="<?= SITE_URL ?>user/profile.php" class="btn btn-ghost">
            <i class="fas fa-user-edit me-2"></i>Edit Profile
        </a>
    </div>

    <?php if ($has_demo_booking): ?>
        <div class="alert alert-info" role="note">
            <i class="fas fa-info-circle me-2"></i>Booking counts can include DEMO presentation bookings; demo entries are excluded from paid-booking and spending totals.
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <a href="<?= SITE_URL ?>user/bookings.php?filter=all" class="glass-card stat-card text-center p-3 d-block text-decoration-none text-reset" title="View all your bookings">
                <div class="stat-icon bg-primary-soft mx-auto mb-2"><i class="fas fa-ticket-alt text-primary"></i></div>
                <div class="stat-value fs-3 fw-bold"><?= $total_bookings ?></div>
                <div class="stat-label text-muted">Total Bookings <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="<?= SITE_URL ?>user/bookings.php?filter=upcoming" class="glass-card stat-card text-center p-3 d-block text-decoration-none text-reset" title="View your upcoming events">
                <div class="stat-icon bg-success-soft mx-auto mb-2"><i class="fas fa-calendar-check text-success"></i></div>
                <div class="stat-value fs-3 fw-bold"><?= $active_bookings ?></div>
                <div class="stat-label text-muted">Upcoming Events <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="<?= SITE_URL ?>user/bookings.php?filter=past" class="glass-card stat-card text-center p-3 d-block text-decoration-none text-reset" title="View your past events and closed bookings">
                <div class="stat-icon bg-danger-soft mx-auto mb-2"><i class="fas fa-heart text-danger"></i></div>
                <div class="stat-value fs-3 fw-bold"><?= $past_bookings ?></div>
                <div class="stat-label text-muted">Past Events <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="<?= SITE_URL ?>user/bookings.php?filter=paid" class="glass-card stat-card text-center p-3 d-block text-decoration-none text-reset" title="View confirmed bookings with completed payments">
                <div class="stat-icon bg-warning-soft mx-auto mb-2"><i class="fas fa-rupee-sign text-warning"></i></div>
                <div class="stat-value fs-3 fw-bold"><?= CURRENCY_SYMBOL ?><?= number_format((float)$total_spent) ?></div>
                <div class="stat-label text-muted">Total Spent · <?= number_format((int)$paid_bookings) ?> paid <i class="fas fa-arrow-right ms-1 small"></i></div>
            </a>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row g-4">
        <!-- Left: Upcoming Events -->
        <div class="col-lg-8">
            <div class="glass-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-calendar me-2 text-primary"></i>My Upcoming Events</h5>
                    <a href="<?= SITE_URL ?>user/bookings.php" class="btn btn-sm btn-ghost">View All</a>
                </div>
                <?php if (count($upcoming_events) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcoming_events as $be): ?>
                        <a href="<?= SITE_URL ?>event.php?slug=<?= $be['slug'] ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 px-0 bg-transparent border-bottom">
                            <img src="<?= event_banner($be['banner']) ?>" 
                                 class="rounded-3" width="64" height="64" style="object-fit:cover;"
                                 onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=100&q=80'">
                            <div class="flex-grow-1 min-w-0">
                                <strong class="d-block text-truncate"><?= htmlspecialchars($be['title']) ?></strong>
                                <small class="text-muted d-block"><i class="fas fa-calendar me-1"></i><?= format_date($be['event_date']) ?> | <i class="fas fa-clock me-1"></i><?= date('h:i A', strtotime($be['event_time'])) ?></small>
                                <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($be['venue']) ?>, <?= htmlspecialchars($be['city_name']) ?></small>
                            </div>
                            <span class="badge bg-success-soft flex-shrink-0">Confirmed</span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                        <p>No upcoming events.</p>
                        <a href="<?= SITE_URL ?>events.php" class="btn btn-primary"><i class="fas fa-search me-2"></i>Browse Events</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Quick Links -->
        <div class="col-lg-4">
            <div class="glass-card mb-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-bell me-2 text-warning"></i>Quick Links</h5>
                <div class="d-grid gap-2">
                    <a href="<?= SITE_URL ?>user/bookings.php" class="btn btn-ghost text-start"><i class="fas fa-history me-2"></i>Booking History</a>
                    <a href="<?= SITE_URL ?>user/profile.php" class="btn btn-ghost text-start"><i class="fas fa-user me-2"></i>Edit Profile</a>
                    <a href="<?= SITE_URL ?>user/bookings.php?filter=past" class="btn btn-ghost text-start"><i class="fas fa-check-circle me-2"></i>Past Events</a>
                    <?php if ($user['role'] == 'organizer' && $user['is_organizer_approved'] == 0): ?>
                    <div class="alert alert-info mt-2 mb-0 py-2 small">
                        <i class="fas fa-clock me-1"></i>Organizer approval pending
                    </div>
                    <a href="<?= SITE_URL ?>organizer/apply.php" class="btn btn-primary mt-2"><i class="fas fa-file-upload me-2"></i>Submit Verification Details</a>
                    <?php elseif ($user['role'] == 'user'): ?>
                    <a href="<?= SITE_URL ?>auth/register.php?upgrade=1" class="btn btn-outline mt-2"><i class="fas fa-star me-2"></i>Become an Organizer</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<!-- Recent Bookings -->
    <?php if (count($recent_bookings) > 0): ?>
    <div class="glass-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0"><i class="fas fa-ticket-alt me-2 text-primary"></i>Recent Bookings</h5>
            <a href="<?= SITE_URL ?>user/bookings.php" class="btn btn-sm btn-ghost">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Booking ID</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bookings as $rb): ?>
                    <tr>
                        <td class="text-truncate" style="max-width:200px;"><?= htmlspecialchars($rb['title']) ?></td>
                        <td>
                            <code><?= htmlspecialchars($rb['booking_id']) ?></code>
                            <?php if (strpos((string)$rb['payment_method'], 'DEMO') === 0): ?>
                                <span class="badge bg-info-soft">DEMO</span>
                            <?php endif; ?>
                        </td>
                        <td><?= format_date($rb['event_date']) ?></td>
                        <td class="fw-bold"><?= CURRENCY_SYMBOL ?><?= number_format($rb['final_amount']) ?></td>
                        <td>
                            <?php
                            $badge_class = 'bg-success-soft';
                            if ($rb['status'] == 'cancelled') $badge_class = 'bg-danger-soft';
                            elseif ($rb['status'] == 'pending') $badge_class = 'bg-warning-soft';
                            ?>
                            <span class="badge <?= $badge_class ?>"><?= ucfirst($rb['status']) ?></span>
                        </td>
                        <td>
                            <?php $is_demo_booking = strpos((string)$rb['payment_method'], 'DEMO') === 0; ?>
                            <span class="badge <?= $is_demo_booking ? 'bg-info-soft' : ($rb['payment_status'] === 'completed' ? 'bg-success-soft' : 'bg-warning-soft') ?>">
                                <?= $is_demo_booking ? 'DEMO — unpaid' : 'Payment ' . htmlspecialchars(ucfirst($rb['payment_status'])) ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= SITE_URL ?>booking/confirmation.php?booking_id=<?= $rb['booking_id'] ?>" class="btn btn-sm btn-ghost"><i class="fas fa-eye"></i></a>
                            <a href="<?= SITE_URL ?>booking/download-ticket.php?booking_id=<?= $rb['booking_id'] ?>" class="btn btn-sm btn-ghost" title="Download Ticket"><i class="fas fa-download"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
