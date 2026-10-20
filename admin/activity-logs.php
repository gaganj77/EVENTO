<?php
/**
 * EVENTO - Admin: Activity, Login History & Audit Logs
 */
require_once __DIR__ . '/admin_auth.php';
$page_title = 'Activity Logs';

$tab = $_GET['tab'] ?? 'activity';
if (!in_array($tab, ['activity', 'logins', 'audit'], true)) { $tab = 'activity'; }
$q = trim($_GET['q'] ?? '');
$limit = 150;

$search_sql = '';
if ($q !== '') {
    $qq = mysqli_real_escape_string($conn, $q);
    if ($tab === 'activity') {
        $search_sql = " AND (a.action LIKE '%$qq%' OR a.description LIKE '%$qq%' OR u.full_name LIKE '%$qq%' OR u.email LIKE '%$qq%')";
    } elseif ($tab === 'logins') {
        $search_sql = " AND (l.role LIKE '%$qq%' OR l.ip_address LIKE '%$qq%' OR u.full_name LIKE '%$qq%' OR u.email LIKE '%$qq%')";
    } else {
        $search_sql = " AND (ad.action LIKE '%$qq%' OR ad.entity_type LIKE '%$qq%' OR adm.full_name LIKE '%$qq%')";
    }
}

if ($tab === 'activity') {
    $rows = fetch_all("SELECT a.*, u.full_name, u.email, u.role
                       FROM activity_logs a
                       LEFT JOIN users u ON u.id = a.user_id
                       WHERE 1=1 $search_sql
                       ORDER BY a.created_at DESC LIMIT $limit");
} elseif ($tab === 'logins') {
    $rows = fetch_all("SELECT l.*, u.full_name, u.email
                       FROM login_history l
                       LEFT JOIN users u ON u.id = l.user_id
                       WHERE 1=1 $search_sql
                       ORDER BY l.created_at DESC LIMIT $limit");
} else {
    $rows = fetch_all("SELECT ad.*, adm.full_name AS admin_name
                       FROM audit_logs ad
                       LEFT JOIN admins adm ON adm.id = ad.admin_id
                       WHERE 1=1 $search_sql
                       ORDER BY ad.created_at DESC LIMIT $limit");
}

$counts = [
    'activity' => (int)(fetch_single("SELECT COUNT(*) AS c FROM activity_logs")['c'] ?? 0),
    'logins'   => (int)(fetch_single("SELECT COUNT(*) AS c FROM login_history")['c'] ?? 0),
    'audit'    => (int)(fetch_single("SELECT COUNT(*) AS c FROM audit_logs")['c'] ?? 0),
];

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4" style="padding-top: 90px !important;">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-history me-2 text-primary"></i>Activity &amp; Audit Logs</h2>
            <p class="text-muted mb-0">Who did what, when - user activity, logins and admin actions</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= SITE_URL ?>admin/reports.php" class="btn btn-warning btn-sm"><i class="fas fa-chart-pie me-1"></i>Reports</a>
            <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="?tab=activity" class="btn btn-sm <?= $tab === 'activity' ? 'btn-primary' : 'btn-ghost' ?>">User Activity (<?= $counts['activity'] ?>)</a>
        <a href="?tab=logins" class="btn btn-sm <?= $tab === 'logins' ? 'btn-primary' : 'btn-ghost' ?>">Login History (<?= $counts['logins'] ?>)</a>
        <a href="?tab=audit" class="btn btn-sm <?= $tab === 'audit' ? 'btn-primary' : 'btn-ghost' ?>">Admin Audit (<?= $counts['audit'] ?>)</a>
        <form method="GET" class="d-flex gap-2 ms-auto">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search logs..." value="<?= htmlspecialchars($q) ?>">
            <button class="btn btn-sm btn-primary">Search</button>
        </form>
    </div>

    <div class="glass-card">
        <div class="table-responsive">
            <?php if ($tab === 'activity'): ?>
            <table class="table table-sm align-middle">
                <thead><tr><th>#</th><th>When</th><th>User</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No activity found.</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><small><?= format_datetime($r['created_at']) ?><br><span class="text-muted"><?= time_elapsed($r['created_at']) ?></span></small></td>
                        <td><small><?= htmlspecialchars($r['full_name'] ?? 'Guest/System') ?><br><span class="text-muted"><?= htmlspecialchars($r['email'] ?? '') ?></span></small></td>
                        <td><span class="badge bg-primary-soft"><?= htmlspecialchars(str_replace('_', ' ', $r['action'])) ?></span></td>
                        <td><small><?= htmlspecialchars(truncate_text($r['description'] ?? '', 90)) ?></small></td>
                        <td><small class="text-muted"><?= htmlspecialchars($r['ip_address'] ?? '-') ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php elseif ($tab === 'logins'): ?>
            <table class="table table-sm align-middle">
                <thead><tr><th>#</th><th>When</th><th>User</th><th>Role</th><th>Status</th><th>Reason</th><th>IP</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No login history found.</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><small><?= format_datetime($r['created_at']) ?></small></td>
                        <td><small><?= htmlspecialchars($r['full_name'] ?? ('User #' . (int)$r['user_id'])) ?><br><span class="text-muted"><?= htmlspecialchars($r['email'] ?? '') ?></span></small></td>
                        <td><small><?= htmlspecialchars($r['role']) ?></small></td>
                        <td><span class="badge <?= $r['login_status'] === 'success' ? 'bg-success-soft' : 'bg-danger-soft' ?>"><?= ucfirst($r['login_status']) ?></span></td>
                        <td><small><?= htmlspecialchars($r['fail_reason'] ?? '-') ?></small></td>
                        <td><small class="text-muted"><?= htmlspecialchars($r['ip_address'] ?? '-') ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <table class="table table-sm align-middle">
                <thead><tr><th>#</th><th>When</th><th>Admin</th><th>Action</th><th>Entity</th><th>Entity ID</th><th>IP</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No admin audit entries yet. Approvals, refunds and payouts appear here.</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><small><?= format_datetime($r['created_at']) ?></small></td>
                        <td><small><?= htmlspecialchars($r['admin_name'] ?? ('Admin #' . (int)$r['admin_id'])) ?></small></td>
                        <td><span class="badge bg-warning-soft"><?= htmlspecialchars(str_replace('_', ' ', $r['action'])) ?></span></td>
                        <td><small><?= htmlspecialchars($r['entity_type'] ?? '-') ?></small></td>
                        <td><small><?= $r['entity_id'] ? (int)$r['entity_id'] : '-' ?></small></td>
                        <td><small class="text-muted"><?= htmlspecialchars($r['ip_address'] ?? '-') ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>