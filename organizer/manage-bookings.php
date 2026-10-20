<?php
/**
 * EVENTO - Organizer Manage Bookings
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_organizer();

$page_title = 'Event Bookings';
$user_id = (int)$_SESSION['user_id'];
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$date_filter = $_GET['date'] ?? '';
$exclude_demo = ($_GET['exclude_demo'] ?? '') === '1';
$allowed_statuses = ['confirmed', 'pending', 'cancelled', 'refunded', 'completed'];
$allowed_payments = ['completed', 'pending', 'failed', 'refunded'];

if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = '';
}
if (!in_array($payment_filter, $allowed_payments, true)) {
    $payment_filter = '';
}

$where = 'WHERE e.user_id = ?';
$types = 'i';
$params = [$user_id];

if ($status_filter !== '') {
    $where .= ' AND b.status = ?';
    $types .= 's';
    $params[] = $status_filter;
}
if ($payment_filter !== '') {
    $where .= ' AND b.payment_status = ?';
    $types .= 's';
    $params[] = $payment_filter;
}
if ($exclude_demo) {
    $where .= " AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%'";
}
if ($date_filter !== '') {
    $date = DateTime::createFromFormat('!Y-m-d', $date_filter);
    if ($date && $date->format('Y-m-d') === $date_filter) {
        $where .= ' AND DATE(b.created_at) = ?';
        $types .= 's';
        $params[] = $date_filter;
    } else {
        $date_filter = '';
    }
}

$bookings = fetch_all(
    "SELECT b.*, e.title AS event_title, e.event_date, u.full_name AS customer_name, u.email AS customer_email
     FROM bookings b
     JOIN events e ON e.id = b.event_id
     JOIN users u ON u.id = b.user_id
     $where
     ORDER BY b.created_at DESC
     LIMIT 500",
    $types,
    $params
);
$totals = fetch_single(
    "SELECT COUNT(*) AS booking_count,
            COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%' THEN b.final_amount ELSE 0 END), 0) AS collected
     FROM bookings b
     JOIN events e ON e.id = b.event_id
     $where",
    $types,
    $params
);
$has_demo_booking = fetch_single(
    "SELECT b.id
     FROM bookings b
     JOIN events e ON e.id = b.event_id
     WHERE e.user_id = ? AND b.payment_method LIKE 'DEMO%'
     LIMIT 1",
    'i',
    [$user_id]
);

include __DIR__ . '/../includes/header.php';
?>

<main class="container py-5" style="padding-top: 100px !important;">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-ticket-alt me-2 text-primary"></i>Event Bookings</h2>
            <p class="text-muted mb-0">Bookings for your events. Revenue includes real confirmed payments only; DEMO bookings are excluded.</p>
        </div>
        <a href="<?= SITE_URL ?>organizer/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <?php if ($has_demo_booking): ?>
        <div class="alert alert-info" role="note">
            <i class="fas fa-info-circle me-2"></i>DEMO booking shown: no payment was processed and no money was collected.
        </div>
    <?php endif; ?>

    <div class="glass-card mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="bookingStatus">Booking status</label>
                <select id="bookingStatus" name="status" class="form-select">
                    <option value="">All statuses</option>
                    <?php foreach ($allowed_statuses as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $status_filter === $status ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="paymentStatus">Payment status</label>
                <select id="paymentStatus" name="payment" class="form-select">
                    <option value="">All payments</option>
                    <?php foreach ($allowed_payments as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $payment_filter === $status ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="bookingDate">Booking date</label>
                <input id="bookingDate" type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
            </div>
            <?php if ($exclude_demo): ?><input type="hidden" name="exclude_demo" value="1"><?php endif; ?>
            <div class="col-12 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-primary" aria-label="Apply filters"><i class="fas fa-filter"></i></button>
                <a href="<?= SITE_URL ?>organizer/manage-bookings.php" class="btn btn-ghost" aria-label="Reset filters"><i class="fas fa-undo"></i></a>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-6">
            <div class="stat-card p-3">
                <div class="stat-icon bg-primary-soft"><i class="fas fa-receipt text-primary"></i></div>
                <div class="stat-value"><?= number_format((int)($totals['booking_count'] ?? 0)) ?></div>
                <div class="stat-label">Bookings (filtered)</div>
            </div>
        </div>
        <div class="col-6 col-md-6">
            <div class="stat-card p-3">
                <div class="stat-icon bg-success-soft"><i class="fas fa-rupee-sign text-success"></i></div>
                <div class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format((float)($totals['collected'] ?? 0)) ?></div>
                <div class="stat-label">Paid Revenue (filtered)</div>
            </div>
        </div>
    </div>

    <section class="glass-card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th>Booking</th><th>Customer</th><th>Event</th><th>Booked</th><th>Tickets</th><th>Amount</th><th>Payment</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if (!$bookings): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">No bookings match these filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <?php $is_demo = strpos((string)$booking['payment_method'], 'DEMO') === 0; ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($booking['booking_id']) ?></strong>
                                    <?php if ($is_demo): ?><span class="badge bg-info-soft ms-1">DEMO</span><?php endif; ?>
                                </td>
                                <td><small><?= htmlspecialchars($booking['customer_name']) ?><br><span class="text-muted"><?= htmlspecialchars($booking['customer_email']) ?></span></small></td>
                                <td><small><?= htmlspecialchars($booking['event_title']) ?><br><span class="text-muted"><?= format_date($booking['event_date']) ?></span></small></td>
                                <td><small><?= format_date($booking['created_at']) ?></small></td>
                                <td><?= (int)$booking['quantity'] ?></td>
                                <td><?= CURRENCY_SYMBOL ?><?= number_format((float)$booking['final_amount'], 2) ?><?php if ($is_demo): ?><small class="d-block text-muted">Not collected</small><?php endif; ?></td>
                                <td>
                                    <span class="badge <?= $booking['payment_status'] === 'completed' ? 'bg-success-soft' : ($booking['payment_status'] === 'failed' || $booking['payment_status'] === 'refunded' ? 'bg-danger-soft' : 'bg-warning-soft') ?>">
                                        <?= htmlspecialchars(ucfirst($booking['payment_status'])) ?>
                                    </span>
                                    <?php if ($is_demo): ?><small class="d-block text-muted">No real payment</small><?php endif; ?>
                                </td>
                                <td><span class="badge <?= $booking['status'] === 'confirmed' || $booking['status'] === 'completed' ? 'bg-success-soft' : ($booking['status'] === 'cancelled' || $booking['status'] === 'refunded' ? 'bg-danger-soft' : 'bg-warning-soft') ?>"><?= htmlspecialchars(ucfirst($booking['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
