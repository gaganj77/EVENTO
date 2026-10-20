<?php
/**
 * EVENTO - Admin Manage Users
 */
require_once __DIR__ . '/admin_auth.php';
$page_title = 'Manage Users';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Handle actions
if ($action && $id > 0) {
    if ($action === 'suspend') {
        mysqli_query($conn, "UPDATE users SET status = 0 WHERE id = $id");
        log_audit($admin_id, 'suspend_user', 'users', $id);
    } elseif ($action === 'activate') {
        mysqli_query($conn, "UPDATE users SET status = 1 WHERE id = $id");
        log_audit($admin_id, 'activate_user', 'users', $id);
    } elseif ($action === 'delete') {
        mysqli_query($conn, "DELETE FROM users WHERE id = $id");
        log_audit($admin_id, 'delete_user', 'users', $id);
    } elseif ($action === 'approve_organizer') {
        mysqli_query($conn, "UPDATE users SET is_organizer_approved = 1, status = 1 WHERE id = $id AND role = 'organizer'");
        mysqli_query($conn, "UPDATE organizer_requests SET status = 'approved', reviewed_by = $admin_id, reviewed_at = NOW() WHERE user_id = $id AND status = 'pending'");
        log_audit($admin_id, 'approve_organizer', 'users', $id);
        create_notification($id, 'account', 'Organizer Approved', 'Congratulations! You are now approved as an organizer. Start creating events!', SITE_URL . 'organizer/dashboard.php');
    } elseif ($action === 'reject_organizer') {
        mysqli_query($conn, "UPDATE users SET is_organizer_approved = 0 WHERE id = $id AND role = 'organizer'");
        mysqli_query($conn, "UPDATE organizer_requests SET status = 'rejected', reviewed_by = $admin_id, reviewed_at = NOW(), admin_note = 'Please update your verification details and apply again.' WHERE user_id = $id AND status = 'pending'");
        log_audit($admin_id, 'reject_organizer', 'users', $id);
        create_notification($id, 'account', 'Organizer Verification Needs Changes', 'Your organizer verification needs changes. Please update your details and apply again.', SITE_URL . 'organizer/apply.php');
    }
    header('Location: manage-users.php');
    exit();
}

$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$role_filter = mysqli_real_escape_string($conn, $_GET['role'] ?? '');
$pending_organizers_filter = ($_GET['verification'] ?? '') === 'pending';
if ($pending_organizers_filter) {
    $role_filter = 'organizer';
}

$where = "WHERE 1=1";
if ($search) $where .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%')";
if ($role_filter) $where .= " AND role = '$role_filter'";
if ($pending_organizers_filter) {
    $where .= " AND u.role = 'organizer' AND u.is_organizer_approved = 0 AND (r.status IS NULL OR r.status = 'pending')";
}

$users = mysqli_query($conn, "SELECT u.*, r.organization_name, r.organization_type, r.website, r.phone AS verification_phone, r.address AS verification_address, r.document_path, r.status AS verification_status FROM users u LEFT JOIN organizer_requests r ON r.id = (SELECT MAX(id) FROM organizer_requests WHERE user_id = u.id) $where ORDER BY u.created_at DESC");

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4" style="padding-top: 90px !important;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-users me-2 text-primary"></i>Manage Users</h2>
        <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
    
    <div class="glass-card">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    <option value="user" <?= $role_filter == 'user' ? 'selected' : '' ?>>User</option>
                    <option value="organizer" <?= $role_filter == 'organizer' ? 'selected' : '' ?>>Organizer</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Filter</button>
            </div>
            <?php if ($pending_organizers_filter): ?>
                <input type="hidden" name="verification" value="pending">
            <?php endif; ?>
        </form>
        <?php if ($pending_organizers_filter): ?>
            <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>Showing organizers with pending or not-yet-submitted applications.</span>
                <a href="<?= SITE_URL ?>admin/manage-users.php?role=organizer" class="alert-link">View all organizers</a>
            </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Verification</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($users) === 0): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No users match this filter.</td></tr>
                    <?php else: ?>
                    <?php while ($u = mysqli_fetch_assoc($users)): ?>
                    <tr>
                        <td>#<?= $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge <?= $u['role'] == 'organizer' ? 'bg-info-soft' : 'bg-primary-soft' ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td>
                            <?php if ($u['role'] === 'organizer'): ?>
                                <span class="badge <?= $u['is_organizer_approved'] ? 'bg-success-soft' : 'bg-warning-soft' ?>"><?= $u['is_organizer_approved'] ? 'Approved' : 'Pending' ?></span>
                                <?php if ($u['organization_name']): ?><small class="d-block mt-1" title="<?= htmlspecialchars($u['verification_address'] ?? '') ?>"><?= htmlspecialchars($u['organization_name']) ?></small><?php endif; ?>
                                <?php if ($u['document_path']): ?><a class="d-block small" target="_blank" href="<?= SITE_URL ?>uploads/users/<?= htmlspecialchars($u['document_path']) ?>">View document</a><?php endif; ?>
                            <?php else: ?>
                                <?= $u['is_verified'] ? '<span class="badge bg-success-soft">Email verified</span>' : '<span class="badge bg-danger-soft">Not verified</span>' ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $u['status'] ? '<span class="badge bg-success-soft">Active</span>' : '<span class="badge bg-danger-soft">Suspended</span>' ?></td>
                        <td><small><?= format_date($u['created_at']) ?></small></td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($u['status']): ?>
                                    <a href="?action=suspend&id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Suspend this user?')"><i class="fas fa-ban"></i></a>
                                <?php else: ?>
                                    <a href="?action=activate&id=<?= $u['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Activate this user?')"><i class="fas fa-check"></i></a>
                                <?php endif; ?>
                                <?php if ($u['role'] == 'organizer' && !$u['is_organizer_approved'] && $u['verification_status'] === 'pending'): ?>
                                    <a href="?action=approve_organizer&id=<?= $u['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this organizer after reviewing their details?')"><i class="fas fa-user-check"></i></a>
                                    <a href="?action=reject_organizer&id=<?= $u['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Reject this organizer verification?')"><i class="fas fa-user-times"></i></a>
                                <?php elseif ($u['role'] == 'organizer' && !$u['is_organizer_approved']): ?>
                                    <span class="badge bg-secondary">No application</span>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user permanently?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
