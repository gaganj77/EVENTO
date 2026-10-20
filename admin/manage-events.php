<?php
/**
 * EVENTO - Admin Manage Events
 */
require_once __DIR__ . '/admin_auth.php';
$page_title = 'Manage Events';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($action && $id > 0) {
    if ($action === 'approve') {
        mysqli_query($conn, "UPDATE events SET status = 'approved' WHERE id = $id");
        $ev = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM events WHERE id = $id"));
        create_notification($ev['user_id'], 'event', 'Event Approved', "Your event '{$ev['title']}' has been approved!", SITE_URL . 'event.php?slug=' . $ev['slug']);
        log_audit($admin_id, 'approve_event', 'events', $id);
    } elseif ($action === 'reject') {
        mysqli_query($conn, "UPDATE events SET status = 'rejected' WHERE id = $id");
        log_audit($admin_id, 'reject_event', 'events', $id);
    } elseif ($action === 'feature') {
        mysqli_query($conn, "UPDATE events SET is_featured = 1 WHERE id = $id");
    } elseif ($action === 'unfeature') {
        mysqli_query($conn, "UPDATE events SET is_featured = 0 WHERE id = $id");
    } elseif ($action === 'delete') {
        mysqli_query($conn, "DELETE FROM events WHERE id = $id");
    }
    header('Location: manage-events.php');
    exit();
}

$status_filter = mysqli_real_escape_string($conn, $_GET['status'] ?? '');
$where = "WHERE 1=1";
if ($status_filter) $where .= " AND e.status = '$status_filter'";

$events = mysqli_query($conn, "SELECT e.*, c.category_name, ci.city_name, u.full_name as organizer_name FROM events e JOIN categories c ON e.category_id = c.id JOIN cities ci ON e.city_id = ci.id JOIN users u ON e.user_id = u.id $where ORDER BY e.created_at DESC");

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4" style="padding-top: 90px !important;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-calendar me-2 text-primary"></i>Manage Events</h2>
        <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
    
    <div class="glass-card">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-2"></i>Filter</button>
            </div>
        </form>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Event</th><th>Organizer</th><th>Category</th><th>City</th><th>Date</th><th>Price</th><th>Seats</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php while ($ev = mysqli_fetch_assoc($events)): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars(truncate_text($ev['title'], 30)) ?></strong></td>
                        <td><small><?= htmlspecialchars($ev['organizer_name']) ?></small></td>
                        <td><small><?= htmlspecialchars($ev['category_name']) ?></small></td>
                        <td><small><?= htmlspecialchars($ev['city_name']) ?></small></td>
                        <td><small><?= format_date($ev['event_date']) ?></small></td>
                        <td><small><?= CURRENCY_SYMBOL ?><?= number_format((float)$ev['discount_price'] > 0 ? $ev['discount_price'] : $ev['price']) ?></small></td>
                        <td><small><?= $ev['available_seats'] ?> / <?= $ev['total_seats'] ?></small></td>
                        <td>
                            <span class="badge <?= $ev['status'] == 'approved' ? 'bg-success-soft' : ($ev['status'] == 'pending' ? 'bg-warning-soft' : 'bg-danger-soft') ?>">
                                <?= ucfirst($ev['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($ev['status'] == 'pending'): ?>
                                    <a href="?action=approve&id=<?= $ev['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this event?')"><i class="fas fa-check"></i></a>
                                    <a href="?action=reject&id=<?= $ev['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this event?')"><i class="fas fa-times"></i></a>
                                <?php endif; ?>
                                <?php if ($ev['is_featured']): ?>
                                    <a href="?action=unfeature&id=<?= $ev['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-star"></i></a>
                                <?php else: ?>
                                    <a href="?action=feature&id=<?= $ev['id'] ?>" class="btn btn-sm btn-ghost"><i class="far fa-star"></i></a>
                                <?php endif; ?>
                                <a href="<?= SITE_URL ?>event.php?slug=<?= $ev['slug'] ?>" class="btn btn-sm btn-info" target="_blank"><i class="fas fa-eye"></i></a>
                                <a href="?action=delete&id=<?= $ev['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
