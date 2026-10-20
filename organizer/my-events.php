<?php
/**
 * EVENTO - My Events (Organizer)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_organizer();

$page_title = 'My Events';
$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? '';
$event_id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $event_id > 0) {
    mysqli_query($conn, "DELETE FROM events WHERE id = $event_id AND user_id = $user_id");
    $_SESSION['success'] = 'Event deleted';
    header('Location: my-events.php');
    exit();
}

$status_filter = $_GET['status'] ?? '';
$allowed_statuses = ['pending', 'approved', 'rejected', 'cancelled', 'completed'];
if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = '';
}
$status_sql = $status_filter === '' ? '' : " AND e.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";

$events = mysqli_query($conn, "SELECT e.*, c.category_name, ci.city_name, 
    (SELECT COUNT(*) FROM bookings WHERE event_id = e.id AND status = 'confirmed') as total_bookings 
    FROM events e 
    JOIN categories c ON e.category_id = c.id 
    JOIN cities ci ON e.city_id = ci.id 
    WHERE e.user_id = $user_id $status_sql
    ORDER BY e.created_at DESC");

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="padding-top: 100px !important;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-calendar me-2 text-primary"></i>My Events</h2>
        <div class="d-flex gap-2">
            <a href="<?= SITE_URL ?>organizer/dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
            <a href="<?= SITE_URL ?>organizer/create-event.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create Event</a>
        </div>
    </div>

    <div class="glass-card mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="eventStatus">Filter by status</label>
                <select id="eventStatus" name="status" class="form-select">
                    <option value="">All events</option>
                    <?php foreach ($allowed_statuses as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $status_filter === $status ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-2"></i>Filter</button>
                <a href="<?= SITE_URL ?>organizer/my-events.php" class="btn btn-ghost">Reset</a>
            </div>
        </form>
    </div>
    
    <?php if ($events && mysqli_num_rows($events) > 0): ?>
    <div class="glass-card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Event</th><th>Category</th><th>City</th><th>Date</th><th>Price</th><th>Bookings</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php while ($ev = mysqli_fetch_assoc($events)): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars(truncate_text($ev['title'], 30)) ?></strong></td>
                        <td><?= htmlspecialchars($ev['category_name']) ?></td>
                        <td><?= htmlspecialchars($ev['city_name']) ?></td>
                        <td><small><?= format_date($ev['event_date']) ?></small></td>
                        <td><?= CURRENCY_SYMBOL ?><?= number_format((float)$ev['discount_price'] > 0 ? $ev['discount_price'] : $ev['price']) ?></td>
                        <td><span class="badge bg-info-soft"><?= $ev['total_bookings'] ?></span></td>
                        <td>
                            <span class="badge <?= $ev['status'] == 'approved' ? 'bg-success-soft' : ($ev['status'] == 'pending' ? 'bg-warning-soft' : 'bg-danger-soft') ?>">
                                <?= ucfirst($ev['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= SITE_URL ?>event.php?slug=<?= $ev['slug'] ?>" class="btn btn-sm btn-ghost" target="_blank"><i class="fas fa-eye"></i></a>
                                <a href="?action=delete&id=<?= $ev['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this event?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-calendar-plus fa-4x text-muted mb-3"></i>
        <h4>No events yet</h4>
        <p class="text-muted">Create your first event and start selling tickets!</p>
        <a href="<?= SITE_URL ?>organizer/create-event.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create Event</a>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
