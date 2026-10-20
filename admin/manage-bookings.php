<?php
/**
 * EVENTO - Admin: Manage Bookings
 * Full booking ledger with filters, collection totals, CSV export and
 * payment/refund actions (each action is written to the admin audit log).
 */
require_once __DIR__ . '/admin_auth.php';
$page_title = 'Manage Bookings';

// ---------------------------------------------------------------
// Actions
// ---------------------------------------------------------------
$action = $_GET['action'] ?? '';
$booking_db_id = (int)($_GET['id'] ?? 0);

if ($action !== '' && $booking_db_id > 0) {
    $booking = fetch_single("SELECT b.*, e.title FROM bookings b JOIN events e ON e.id = b.event_id WHERE b.id = ?", "i", [$booking_db_id]);
    if ($booking) {
        if ($action === 'mark_paid') {
            execute_query("UPDATE bookings SET payment_status = 'completed' WHERE id = ?", "i", [$booking_db_id]);
            execute_query("UPDATE payments SET status = 'success' WHERE booking_id = ?", "i", [$booking_db_id]);
            log_audit($admin_id, 'mark_booking_paid', 'bookings', $booking_db_id);
            $_SESSION['success'] = 'Booking ' . $booking['booking_id'] . ' marked as paid.';
        } elseif ($action === 'refund') {
            execute_query("UPDATE bookings SET status = 'refunded', payment_status = 'refunded', refund_requested = 0, cancelled_at = NOW() WHERE id = ?", "i", [$booking_db_id]);
            execute_query("UPDATE payments SET status = 'refunded' WHERE booking_id = ?", "i", [$booking_db_id]);
            if ($booking['status'] === 'confirmed') {
                execute_query("UPDATE events SET booked_seats = GREATEST(booked_seats - ?, 0) WHERE id = ?", "ii", [(int)$booking['quantity'], (int)$booking['event_id']]);
            }
            log_audit($admin_id, 'refund_booking', 'bookings', $booking_db_id);
            create_notification($booking['user_id'], 'refund', 'Refund Processed', 'Refund for booking ' . $booking['booking_id'] . ' has been processed.', SITE_URL . 'user/bookings.php');
            $_SESSION['success'] = 'Booking ' . $booking['booking_id'] . ' marked as refunded.';
        } elseif ($action === 'cancel') {
            execute_query("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?", "i", [$booking_db_id]);
            if ($booking['status'] === 'confirmed') {
                execute_query("UPDATE events SET booked_seats = GREATEST(booked_seats - ?, 0) WHERE id = ?", "ii", [(int)$booking['quantity'], (int)$booking['event_id']]);
            }
            log_audit($admin_id, 'cancel_booking', 'bookings', $booking_db_id);
            create_notification($booking['user_id'], 'cancellation', 'Booking Cancelled By Admin', 'Booking ' . $booking['booking_id'] . ' was cancelled by the admin team.', SITE_URL . 'user/bookings.php');
            $_SESSION['success'] = 'Booking ' . $booking['booking_id'] . ' cancelled.';
        }
    }
    header('Location: manage-bookings.php');
    exit();
}

// ---------------------------------------------------------------
// Filters
// ---------------------------------------------------------------
$f_month   = $_GET['month'] ?? '';              // YYYY-MM
$f_date    = $_GET['date'] ?? '';               // YYYY-MM-DD
$f_status  = $_GET['status'] ?? '';
$f_payment = $_GET['payment'] ?? '';
$f_exclude_demo = ($_GET['exclude_demo'] ?? '') === '1';
$f_org     = (int)($_GET['organizer'] ?? 0);
$f_event   = (int)($_GET['event'] ?? 0);
$f_q       = trim($_GET['q'] ?? '');
$export    = ($_GET['export'] ?? '') === 'csv';

$where = "WHERE 1=1";
if ($f_month !== '' && preg_match('/^\d{4}-\d{2}$/', $f_month)) {
    $where .= " AND DATE_FORMAT(b.created_at, '%Y-%m') = '" . mysqli_real_escape_string($conn, $f_month) . "'";
}
if ($f_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_date)) {
    $date_value = DateTime::createFromFormat('!Y-m-d', $f_date);
    if ($date_value && $date_value->format('Y-m-d') === $f_date) {
        $where .= " AND DATE(b.created_at) = '" . mysqli_real_escape_string($conn, $f_date) . "'";
    } else {
        $f_date = '';
    }
} else {
    $f_date = '';
}
if ($f_status !== '') {
    $where .= " AND b.status = '" . mysqli_real_escape_string($conn, $f_status) . "'";
}
if ($f_payment !== '') {
    $where .= " AND b.payment_status = '" . mysqli_real_escape_string($conn, $f_payment) . "'";
}
if ($f_exclude_demo) {
    $where .= " AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%'";
}
if ($f_org > 0)   { $where .= " AND e.user_id = $f_org"; }
if ($f_event > 0) { $where .= " AND b.event_id = $f_event"; }
if ($f_q !== '') {
    $qq = mysqli_real_escape_string($conn, $f_q);
    $where .= " AND (b.booking_id LIKE '%$qq%' OR u.full_name LIKE '%$qq%' OR u.email LIKE '%$qq%' OR e.title LIKE '%$qq%')";
}
$joins = "FROM bookings b
          JOIN events e ON e.id = b.event_id
          JOIN users u ON u.id = b.user_id
          JOIN users o ON o.id = e.user_id";
$base_from = "$joins $where";

$totals = fetch_single("SELECT COUNT(*) AS bookings_count,
        COUNT(DISTINCT b.user_id) AS customers,
        COALESCE(SUM(b.quantity), 0) AS tickets,
        COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END), 0) AS confirmed_count,
        COALESCE(SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count,
        COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%' THEN b.total_amount ELSE 0 END), 0) AS gross,
        COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%' THEN b.discount_amount ELSE 0 END), 0) AS discount,
        COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%' THEN b.final_amount ELSE 0 END), 0) AS collected
    $base_from");

$rows = fetch_all("SELECT b.*, e.title, e.event_date, e.slug, u.full_name AS customer_name, u.email AS customer_email,
                          o.full_name AS organizer_name, o.id AS organizer_id,
                          p.payment_id, p.status AS gateway_status
                   $joins
                   LEFT JOIN payments p ON p.booking_id = b.id
                   $where
                   ORDER BY b.created_at DESC
                   LIMIT 500");

$organizers  = fetch_all("SELECT id, full_name FROM users WHERE role = 'organizer' ORDER BY full_name");
$events_list = fetch_all("SELECT id, title FROM events ORDER BY title");

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=evento-bookings-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Booking ID', 'Date', 'Customer', 'Email', 'Event', 'Organizer', 'Qty', 'Total', 'Discount', 'Paid', 'Coupon', 'Status', 'Payment', 'Method']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['booking_id'], $r['created_at'], $r['customer_name'], $r['customer_email'], $r['title'], $r['organizer_name'],
            $r['quantity'], $r['total_amount'], $r['discount_amount'], $r['final_amount'], $r['coupon_code'],
            $r['status'], $r['payment_status'], $r['payment_method'],
        ]);
    }
    fclose($out);
    log_audit($admin_id, 'export_bookings', 'bookings', null);
    exit();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4" style="padding-top: 90px !important;">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-receipt me-2 text-primary"></i>Bookings &amp; Collection</h2>
            <p class="text-muted mb-0">Every booking with its coupon, payment status and collected amount. DEMO bookings are excluded from collected totals.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="?export=csv&amp;month=<?= urlencode($f_month) ?>&amp;date=<?= urlencode($f_date) ?>&amp;status=<?= urlencode($f_status) ?>&amp;payment=<?= urlencode($f_payment) ?>&amp;exclude_demo=<?= $f_exclude_demo ? '1' : '' ?>&amp;organizer=<?= $f_org ?>&amp;event=<?= $f_event ?>&amp;q=<?= urlencode($f_q) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
            <a href="<?= SITE_URL ?>admin/reports.php" class="btn btn-warning btn-sm"><i class="fas fa-chart-pie me-1"></i>Reports &amp; Payouts</a>
            <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
        </div>
    </div>
    <div class="glass-card mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">Month</label>
                <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($f_month) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Booking Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($f_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Booking Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['confirmed', 'pending', 'cancelled', 'refunded', 'completed'] as $st): ?>
                    <option value="<?= $st ?>" <?= $f_status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Payment</label>
                <select name="payment" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['completed', 'pending', 'failed', 'refunded'] as $ps): ?>
                    <option value="<?= $ps ?>" <?= $f_payment === $ps ? 'selected' : '' ?>><?= ucfirst($ps) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Organizer</label>
                <select name="organizer" class="form-select form-select-sm">
                    <option value="0">All</option>
                    <?php foreach ($organizers as $o): ?>
                    <option value="<?= (int)$o['id'] ?>" <?= $f_org === (int)$o['id'] ? 'selected' : '' ?>><?= htmlspecialchars($o['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Event</label>
                <select name="event" class="form-select form-select-sm">
                    <option value="0">All</option>
                    <?php foreach ($events_list as $ev): ?>
                    <option value="<?= (int)$ev['id'] ?>" <?= $f_event === (int)$ev['id'] ? 'selected' : '' ?>><?= htmlspecialchars(truncate_text($ev['title'], 28)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Booking / name / email" value="<?= htmlspecialchars($f_q) ?>">
            </div>
            <div class="col-12">
                <button class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>Apply Filters</button>
                <a href="manage-bookings.php" class="btn btn-ghost btn-sm">Reset</a>
                <?php if ($f_exclude_demo): ?><input type="hidden" name="exclude_demo" value="1"><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card p-3"><div class="stat-value"><?= number_format((int)$totals['bookings_count']) ?></div><div class="stat-label">Bookings (filtered)</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3"><div class="stat-value"><?= number_format((int)$totals['tickets']) ?></div><div class="stat-label">Tickets</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3"><div class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format((float)$totals['collected']) ?></div><div class="stat-label">Collected (paid)</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3"><div class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format((float)$totals['discount']) ?></div><div class="stat-label">Discounts given</div></div>
        </div>
    </div>
    <div class="glass-card">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Booking</th><th>Date</th><th>Customer</th><th>Event</th><th>Organizer</th>
                        <th>Qty</th><th>Total</th><th>Disc.</th><th>Paid</th><th>Coupon</th>
                        <th>Status</th><th>Payment</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="13" class="text-center text-muted py-4">No bookings found for these filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['booking_id']) ?></strong></td>
                        <td><small><?= format_date($r['created_at']) ?></small></td>
                        <td><small><?= htmlspecialchars($r['customer_name']) ?><br><span class="text-muted"><?= htmlspecialchars($r['customer_email']) ?></span></small></td>
                        <td><small><?= htmlspecialchars(truncate_text($r['title'], 26)) ?></small></td>
                        <td><small><?= htmlspecialchars($r['organizer_name']) ?></small></td>
                        <td><?= (int)$r['quantity'] ?></td>
                        <td><small><?= CURRENCY_SYMBOL ?><?= number_format((float)$r['total_amount']) ?></small></td>
                        <td><small class="text-success">-<?= CURRENCY_SYMBOL ?><?= number_format((float)$r['discount_amount']) ?></small></td>
                        <td><strong><?= $r['status'] === 'confirmed' && $r['payment_status'] === 'completed' && strpos((string)$r['payment_method'], 'DEMO') !== 0 ? CURRENCY_SYMBOL . number_format((float)$r['final_amount']) : CURRENCY_SYMBOL . '0' ?></strong></td>
                        <td><small><?= $r['coupon_code'] && $r['coupon_code'] !== '0' ? htmlspecialchars($r['coupon_code']) : '<span class="text-muted">-</span>' ?></small></td>
                        <td><span class="badge <?= $r['status'] === 'confirmed' ? 'bg-success-soft' : ($r['status'] === 'cancelled' ? 'bg-danger-soft' : 'bg-warning-soft') ?>"><?= ucfirst($r['status']) ?></span></td>
                        <td><span class="badge <?= $r['payment_status'] === 'completed' ? 'bg-success-soft' : 'bg-warning-soft' ?>"><?= ucfirst($r['payment_status']) ?></span><br><small class="text-muted"><?= htmlspecialchars($r['payment_method'] ?? '') ?></small><?php if (strpos((string)$r['payment_method'], 'DEMO') === 0): ?><span class="badge bg-info-soft">DEMO — not collected</span><?php endif; ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($r['payment_status'] !== 'completed' && $r['status'] === 'confirmed'): ?>
                                <a href="?action=mark_paid&amp;id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-success" title="Mark payment received" onclick="return confirm('Mark this booking as paid?')"><i class="fas fa-check"></i></a>
                                <?php endif; ?>
                                <?php if ($r['status'] === 'confirmed'): ?>
                                <a href="?action=refund&amp;id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-warning" title="Refund" onclick="return confirm('Refund this booking?')"><i class="fas fa-undo"></i></a>
                                <a href="?action=cancel&amp;id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-danger" title="Cancel" onclick="return confirm('Cancel this booking?')"><i class="fas fa-times"></i></a>
                                <?php endif; ?>
                                <a href="<?= SITE_URL ?>booking/verify.php?booking_id=<?= urlencode($r['booking_id']) ?>" target="_blank" class="btn btn-sm btn-info" title="Verify QR"><i class="fas fa-qrcode"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>