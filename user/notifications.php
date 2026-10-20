<?php
/**
 * EVENTO - User Notifications
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_auth();

$page_title = 'Notifications';
$user_id = $_SESSION['user_id'];

// Mark all as read
if (isset($_GET['mark_read'])) {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    header('Location: notifications.php');
    exit();
}

// Mark single as read
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $nid = (int)$_GET['read'];
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE id = $nid AND user_id = $user_id");
    header('Location: notifications.php');
    exit();
}

$notifications = fetch_all("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50", "i", [$user_id]);

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="padding-top: 100px !important;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-bell me-2 text-warning"></i>Notifications</h2>
        <a href="?mark_read=1" class="btn btn-ghost btn-sm"><i class="fas fa-check-double me-2"></i>Mark All Read</a>
    </div>
    
    <?php if (count($notifications) > 0): ?>
    <div class="list-group">
        <?php foreach ($notifications as $n): ?>
        <a href="<?= $n['link'] ?: '#' ?>" class="list-group-item list-group-item-action d-flex align-items-start gap-3 <?= $n['is_read'] ? '' : 'bg-primary-soft' ?> border-bottom">
            <div class="flex-grow-1">
                <strong><?= htmlspecialchars($n['title']) ?></strong>
                <p class="mb-0 text-muted"><?= htmlspecialchars($n['message']) ?></p>
                <small class="text-muted"><?= time_elapsed($n['created_at']) ?></small>
            </div>
            <?php if (!$n['is_read']): ?>
            <a href="?read=<?= $n['id'] ?>" class="text-primary small">Mark Read</a>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
        <h4>No notifications</h4>
        <p class="text-muted">You're all caught up!</p>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
