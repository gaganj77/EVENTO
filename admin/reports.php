<?php
/**
 * EVENTO - Admin: Reports, Collection & Organizer Payouts
 *
 * Shows how much the platform collected, how many bookings happened month by
 * month and per event, and how much each organizer must be paid (after the
 * platform commission). Payouts can be recorded and a payout coupon can be
 * generated for the organizer.
 */
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../includes/payouts.php';
$page_title = 'Reports & Payouts';

$notice = '';
$notice_type = 'success';

// ---------------------------------------------------------------
// Save commission percentage
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_commission'])) {
    $percent = (float)($_POST['commission_percent'] ?? 0);
    if ($percent < 0) { $percent = 0; }
    if ($percent > 100) { $percent = 100; }
    execute_query("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES ('commission_percent', ?, 'payment')
                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        "s", [(string)$percent]);
    log_audit($admin_id, 'update_commission', 'settings', null, null, ['commission_percent' => $percent]);
    $notice = 'Platform commission updated to ' . $percent . '%.';
}

// ---------------------------------------------------------------
// Record a payout (+ optional payout coupon)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payout'])) {
    $result = record_payout(
        (int)($_POST['organizer_id'] ?? 0),
        (float)($_POST['amount'] ?? 0),
        $_POST['method'] ?? 'bank',
        $_POST['reference'] ?? '',
        $_POST['note'] ?? '',
        isset($_POST['create_coupon']),
        $admin_id
    );
    $notice = $result['message'];
    $notice_type = $result['success'] ? 'success' : 'danger';
    if ($result['success']) {
        log_audit($admin_id, 'record_payout', 'payouts', $result['payout_id'], null, ['amount' => (float)($_POST['amount'] ?? 0), 'organizer_id' => (int)($_POST['organizer_id'] ?? 0)]);
        if (!empty($result['coupon'])) {
            $notice .= ' Payout coupon generated: ' . $result['coupon'];
        }
    }
}

// ---------------------------------------------------------------
// Delete a payout entry
// ---------------------------------------------------------------
$delete_payout = (int)($_GET['delete_payout'] ?? 0);
if ($delete_payout > 0) {
    execute_query("DELETE FROM payouts WHERE id = ?", "i", [$delete_payout]);
    log_audit($admin_id, 'delete_payout', 'payouts', $delete_payout);
    $notice = 'Payout entry deleted.';
    $notice_type = 'warning';
}

// ---------------------------------------------------------------
// Data
// ---------------------------------------------------------------
$commission = payout_commission_percent();

$overall = fetch_single("SELECT
        COUNT(*) AS bookings_all,
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END), 0) AS confirmed_count,
        COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count,
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN quantity ELSE 0 END), 0) AS tickets,
        COALESCE(SUM(CASE WHEN status = 'confirmed' AND payment_status = 'completed' AND COALESCE(payment_method, '') NOT LIKE 'DEMO%' THEN total_amount ELSE 0 END), 0) AS gross,
        COALESCE(SUM(CASE WHEN status = 'confirmed' AND payment_status = 'completed' AND COALESCE(payment_method, '') NOT LIKE 'DEMO%' THEN discount_amount ELSE 0 END), 0) AS discount,
        COALESCE(SUM(CASE WHEN status = 'confirmed' AND payment_status = 'completed' AND COALESCE(payment_method, '') NOT LIKE 'DEMO%' THEN final_amount ELSE 0 END), 0) AS collected
    FROM bookings");

$this_month = fetch_single("SELECT
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END), 0) AS bookings,
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN quantity ELSE 0 END), 0) AS tickets,
        COALESCE(SUM(CASE WHEN status = 'confirmed' AND payment_status = 'completed' AND COALESCE(payment_method, '') NOT LIKE 'DEMO%' THEN final_amount ELSE 0 END), 0) AS collected
    FROM bookings WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");

$monthly = fetch_all("SELECT DATE_FORMAT(b.created_at, '%Y-%m') AS ym,
        DATE_FORMAT(b.created_at, '%b %Y') AS month_label,
        COUNT(*) AS bookings,
        COALESCE(SUM(b.quantity), 0) AS tickets,
        COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END), 0) AS confirmed_count,
        COALESCE(SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count,
        COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%' THEN b.discount_amount ELSE 0 END), 0) AS discount,
        COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%' THEN b.final_amount ELSE 0 END), 0) AS collected
    FROM bookings b
    GROUP BY ym
    ORDER BY ym DESC
    LIMIT 12");

$per_event = fetch_all("SELECT e.id, e.title, e.event_date, e.status, o.full_name AS organizer_name,
        COUNT(b.id) AS bookings,
        COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN b.quantity ELSE 0 END), 0) AS tickets,
        COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%' THEN b.final_amount ELSE 0 END), 0) AS collected
    FROM events e
    JOIN users o ON o.id = e.user_id
    LEFT JOIN bookings b ON b.event_id = e.id
    GROUP BY e.id
    ORDER BY collected DESC, e.event_date DESC
    LIMIT 50");

$organizer_rows = organizer_payout_rows();
$payouts = fetch_all("SELECT p.*, u.full_name AS organizer_name FROM payouts p JOIN users u ON u.id = p.organizer_id ORDER BY p.id DESC LIMIT 25");

$total_payable = 0; $total_paid = 0; $total_balance = 0; $total_commission = 0;
foreach ($organizer_rows as $orow) {
    $total_payable += $orow['organizer_payable'];
    $total_paid += (float)$orow['total_paid'];
    $total_balance += $orow['balance'];
    $total_commission += $orow['platform_commission'];
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4" style="padding-top: 90px !important;">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-chart-pie me-2 text-primary"></i>Reports, Collection &amp; Payouts</h2>
            <p class="text-muted mb-0">Collection, monthly bookings, per-event sales and how much to pay each organizer</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= SITE_URL ?>admin/manage-bookings.php" class="btn btn-ghost btn-sm"><i class="fas fa-receipt me-1"></i>All Bookings</a>
            <a href="<?= SITE_URL ?>admin/activity-logs.php" class="btn btn-ghost btn-sm"><i class="fas fa-history me-1"></i>Activity Logs</a>
            <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
        </div>
    </div>

    <?php if ($notice): ?><div class="alert alert-<?= $notice_type ?>"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card p-3"><div class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format((float)$overall['collected']) ?></div><div class="stat-label">Total Collection (paid)</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3"><div class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format((float)$this_month['collected']) ?></div><div class="stat-label">This Month (<?= date('M Y') ?>)</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3"><div class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($total_commission) ?></div><div class="stat-label">Platform Commission (<?= $commission ?>%)</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3"><div class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($total_balance) ?></div><div class="stat-label">Pending Payout to Organizers</div></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="glass-card">
            <h6 class="fw-bold mb-2">Bookings</h6>
            <p class="mb-1">Total: <strong><?= (int)$overall['bookings_all'] ?></strong> | Confirmed: <strong><?= (int)$overall['confirmed_count'] ?></strong> | Cancelled: <strong><?= (int)$overall['cancelled_count'] ?></strong></p>
            <p class="mb-1">Tickets sold (confirmed): <strong><?= number_format((int)$overall['tickets']) ?></strong></p>
            <p class="mb-0 text-muted small">This month: <?= (int)$this_month['bookings'] ?> bookings / <?= (int)$this_month['tickets'] ?> tickets</p>
        </div></div>
        <div class="col-md-4"><div class="glass-card">
            <h6 class="fw-bold mb-2">Money</h6>
            <p class="mb-1">Gross: <strong><?= CURRENCY_SYMBOL ?><?= number_format((float)$overall['gross']) ?></strong></p>
            <p class="mb-1">Discounts given: <strong class="text-success">-<?= CURRENCY_SYMBOL ?><?= number_format((float)$overall['discount']) ?></strong></p>
            <p class="mb-0">Net collected: <strong class="text-gradient"><?= CURRENCY_SYMBOL ?><?= number_format((float)$overall['collected']) ?></strong></p>
        </div></div>
        <div class="col-md-4"><div class="glass-card">
            <h6 class="fw-bold mb-2">Organizer Settlement</h6>
            <p class="mb-1">Payable (after commission): <strong><?= CURRENCY_SYMBOL ?><?= number_format($total_payable) ?></strong></p>
            <p class="mb-1">Already paid: <strong><?= CURRENCY_SYMBOL ?><?= number_format($total_paid) ?></strong></p>
            <p class="mb-2">Balance: <strong class="text-danger"><?= CURRENCY_SYMBOL ?><?= number_format($total_balance) ?></strong></p>
            <form method="POST" class="d-flex gap-2 align-items-end">
                <div>
                    <label class="form-label small mb-0">Commission %</label>
                    <input type="number" name="commission_percent" class="form-control form-control-sm" step="0.01" min="0" max="100" value="<?= htmlspecialchars((string)$commission) ?>">
                </div>
                <button type="submit" name="save_commission" value="1" class="btn btn-primary btn-sm">Save</button>
            </form>
        </div></div>
    </div>
<div class="glass-card mb-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-calendar-alt me-2 text-primary"></i>Monthly Bookings &amp; Collection (last 12 months)</h5>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Month</th><th>Bookings</th><th>Tickets</th><th>Confirmed</th><th>Cancelled</th><th>Discounts</th><th>Collected</th></tr></thead>
                <tbody>
                <?php if (!$monthly): ?><tr><td colspan="7" class="text-center text-muted py-4">No bookings yet.</td></tr><?php endif; ?>
                <?php foreach ($monthly as $m): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($m['month_label']) ?></strong> <small class="text-muted">(<?= htmlspecialchars($m['ym']) ?>)</small></td>
                        <td><?= (int)$m['bookings'] ?></td>
                        <td><?= (int)$m['tickets'] ?></td>
                        <td><span class="badge bg-success-soft"><?= (int)$m['confirmed_count'] ?></span></td>
                        <td><span class="badge bg-danger-soft"><?= (int)$m['cancelled_count'] ?></span></td>
                        <td><small class="text-success">-<?= CURRENCY_SYMBOL ?><?= number_format((float)$m['discount']) ?></small></td>
                        <td><strong><?= CURRENCY_SYMBOL ?><?= number_format((float)$m['collected']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass-card mb-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-users me-2 text-primary"></i>Organizer Earnings &amp; Payout (owner-wise)</h5>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Organizer</th><th>Events</th><th>Bookings</th><th>Tickets</th><th>Gross</th><th>Discounts</th><th>Net Revenue</th><th>Commission (<?= $commission ?>%)</th><th>Payable</th><th>Paid</th><th>Balance</th></tr></thead>
                <tbody>
                <?php if (!$organizer_rows): ?><tr><td colspan="11" class="text-center text-muted py-4">No organizers yet.</td></tr><?php endif; ?>
                <?php foreach ($organizer_rows as $orow): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($orow['full_name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($orow['email']) ?></small></td>
                        <td><?= (int)$orow['events_count'] ?></td>
                        <td><?= (int)$orow['bookings_count'] ?></td>
                        <td><?= (int)$orow['tickets_sold'] ?></td>
                        <td><small><?= CURRENCY_SYMBOL ?><?= number_format((float)$orow['gross_collection']) ?></small></td>
                        <td><small class="text-success">-<?= CURRENCY_SYMBOL ?><?= number_format((float)$orow['total_discount']) ?></small></td>
                        <td><strong><?= CURRENCY_SYMBOL ?><?= number_format((float)$orow['net_collection']) ?></strong></td>
                        <td><small class="text-danger">-<?= CURRENCY_SYMBOL ?><?= number_format($orow['platform_commission']) ?></small></td>
                        <td><strong class="text-gradient"><?= CURRENCY_SYMBOL ?><?= number_format($orow['organizer_payable']) ?></strong></td>
                        <td><small><?= CURRENCY_SYMBOL ?><?= number_format((float)$orow['total_paid']) ?></small></td>
                        <td><strong class="<?= $orow['balance'] > 0 ? 'text-danger' : 'text-success' ?>"><?= CURRENCY_SYMBOL ?><?= number_format($orow['balance']) ?></strong></td>
                    </tr>
<div class="glass-card mb-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-calendar-check me-2 text-primary"></i>Per-Event Bookings &amp; Revenue</h5>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Event</th><th>Organizer</th><th>Date</th><th>Status</th><th>Bookings</th><th>Tickets</th><th>Collected</th><th>Organizer Payable</th></tr></thead>
                <tbody>
                <?php if (!$per_event): ?><tr><td colspan="8" class="text-center text-muted py-4">No events yet.</td></tr><?php endif; ?>
                <?php foreach ($per_event as $pe): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars(truncate_text($pe['title'], 34)) ?></strong></td>
                        <td><small><?= htmlspecialchars($pe['organizer_name']) ?></small></td>
                        <td><small><?= format_date($pe['event_date']) ?></small></td>
                        <td><span class="badge <?= $pe['status'] === 'approved' ? 'bg-success-soft' : 'bg-warning-soft' ?>"><?= ucfirst($pe['status']) ?></span></td>
                        <td><?= (int)$pe['bookings'] ?></td>
                        <td><?= (int)$pe['tickets'] ?></td>
                        <td><strong><?= CURRENCY_SYMBOL ?><?= number_format((float)$pe['collected']) ?></strong></td>
                        <td><?= CURRENCY_SYMBOL ?><?= number_format(round((float)$pe['collected'] * (100 - $commission) / 100, 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
<div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="glass-card h-100">
                <h5 class="fw-bold mb-3"><i class="fas fa-money-check-alt me-2 text-success"></i>Pay an Organizer</h5>
                <form method="POST" class="row g-2">
                    <div class="col-12">
                        <label class="form-label">Organizer *</label>
                        <select name="organizer_id" id="payoutOrganizer" class="form-select" required>
                            <option value="">Select organizer</option>
                            <?php foreach ($organizer_rows as $orow): ?>
                            <option value="<?= (int)$orow['organizer_id'] ?>" data-balance="<?= number_format($orow['balance'], 2, '.', '') ?>">
                                <?= htmlspecialchars($orow['full_name']) ?> - balance <?= CURRENCY_SYMBOL ?><?= number_format($orow['balance']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Amount (<?= CURRENCY_SYMBOL ?>) *</label>
                        <input type="number" name="amount" id="payoutAmount" class="form-control" step="0.01" min="1" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Method</label>
                        <select name="method" class="form-select">
                            <option value="bank">Bank transfer</option>
                            <option value="upi">UPI</option>
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reference / UTR (optional)</label>
                        <input type="text" name="reference" class="form-control" placeholder="TXN123456">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Note (optional)</label>
                        <input type="text" name="note" class="form-control" placeholder="Settlement for this month's bookings">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="create_coupon" id="create_coupon" value="1" checked>
                            <label class="form-check-label" for="create_coupon">Generate a payout coupon for this organizer</label>
                        </div>
                        <small class="text-muted">Creates a one-time coupon (code <strong>PAYOUT&lt;id&gt;XXXX</strong>) worth the payout amount for this organizer.</small>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="record_payout" value="1" class="btn btn-success w-100"><i class="fas fa-check me-1"></i>Record Payout</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="glass-card h-100">
                <h5 class="fw-bold mb-3"><i class="fas fa-history me-2 text-primary"></i>Recent Payouts</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>#</th><th>Organizer</th><th>Amount</th><th>Method</th><th>Reference</th><th>Coupon</th><th>Date</th><th></th></tr></thead>
                        <tbody>
                        <?php if (!$payouts): ?><tr><td colspan="8" class="text-center text-muted py-4">No payouts recorded yet.</td></tr><?php endif; ?>
                        <?php foreach ($payouts as $p): ?>
                            <tr>
                                <td><?= (int)$p['id'] ?></td>
                                <td><small><?= htmlspecialchars($p['organizer_name']) ?></small></td>
                                <td><strong><?= CURRENCY_SYMBOL ?><?= number_format((float)$p['amount']) ?></strong></td>
                                <td><small><?= htmlspecialchars($p['method']) ?></small></td>
                                <td><small><?= htmlspecialchars($p['reference'] ?? '-') ?></small></td>
                                <td><small><?= $p['coupon_code'] ? '<strong>' . htmlspecialchars($p['coupon_code']) . '</strong>' : '<span class="text-muted">-</span>' ?></small></td>
                                <td><small><?= format_date($p['created_at']) ?></small></td>
                                <td><a href="?delete_payout=<?= (int)$p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this payout entry?')"><i class="fas fa-trash"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('payoutOrganizer').addEventListener('change', function () {
    var opt = this.options[this.selectedIndex];
    var balance = opt.getAttribute('data-balance');
    if (balance) { document.getElementById('payoutAmount').value = balance; }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
        </div>
    </div>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>