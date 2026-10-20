<?php
/**
 * EVENTO - Organizer Coupons
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_organizer();

$page_title = 'Manage Coupons';
$user_id = (int)$_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $type = $_POST['discount_type'] ?? 'percentage';
    $value = (float)($_POST['discount_value'] ?? 0);
    $event_id = (int)($_POST['event_id'] ?? 0);
    $min_quantity = max(1, (int)($_POST['min_quantity'] ?? 3));
    $valid_to = $_POST['valid_to'] ?? date('Y-m-d', strtotime('+30 days'));

    $event = fetch_single("SELECT id FROM events WHERE id = ? AND user_id = ?", "ii", [$event_id, $user_id]);
    if (!preg_match('/^[A-Z0-9_-]{4,50}$/', $code) || $value <= 0 || !$event) {
        $error = 'Enter a valid code, discount, and one of your events.';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, min_quantity, usage_limit, event_id, user_id, valid_from, valid_to, status) VALUES (?, ?, ?, 0, ?, 0, ?, ?, NOW(), CONCAT(?, ' 23:59:59'), 1)");
        mysqli_stmt_bind_param($stmt, "ssdiiis", $code, $type, $value, $min_quantity, $event_id, $user_id, $valid_to);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = 'Coupon created and linked to your event.';
            header('Location: manage-coupons.php');
            exit();
        }
        mysqli_stmt_close($stmt);
        $error = 'Coupon code already exists or could not be created.';
    }
}

$events = fetch_all("SELECT id, title FROM events WHERE user_id = ? ORDER BY created_at DESC", "i", [$user_id]);
$coupons = fetch_all("SELECT c.*, e.title FROM coupons c JOIN events e ON c.event_id = e.id WHERE c.user_id = ? ORDER BY c.created_at DESC", "i", [$user_id]);
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5" style="padding-top: 100px !important;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold"><i class="fas fa-gift me-2 text-primary"></i>Event Coupons</h2>
            <p class="text-muted mb-0">Share coupons for your events. Customers need the minimum ticket count.</p>
        </div>
        <a href="<?= SITE_URL ?>organizer/dashboard.php" class="btn btn-ghost">Back</a>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card">
                <h5 class="fw-bold mb-3">Create Coupon</h5>
                <form method="POST">
                    <label class="form-label">Coupon Code</label>
                    <input name="code" class="form-control mb-3" placeholder="KOTA10" required>
                    <label class="form-label">Event</label>
                    <select name="event_id" class="form-select mb-3" required>
                        <option value="">Choose your event</option>
                        <?php foreach ($events as $event): ?><option value="<?= $event['id'] ?>"><?= htmlspecialchars($event['title']) ?></option><?php endforeach; ?>
                    </select>
                    <label class="form-label">Discount Type</label>
                    <select name="discount_type" class="form-select mb-3"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed (<?= CURRENCY_SYMBOL ?>)</option></select>
                    <label class="form-label">Discount Value</label>
                    <input type="number" name="discount_value" class="form-control mb-3" min="1" step="0.01" required>
                    <label class="form-label">Minimum Tickets</label>
                    <input type="number" name="min_quantity" class="form-control mb-3" min="3" value="3" required>
                    <label class="form-label">Valid Until</label>
                    <input type="date" name="valid_to" class="form-control mb-3" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    <button class="btn btn-primary w-100"><i class="fas fa-plus me-2"></i>Create Coupon</button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="glass-card">
                <h5 class="fw-bold mb-3">Your Coupons</h5>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Code</th><th>Event</th><th>Discount</th><th>Min tickets</th><th>Used</th></tr></thead><tbody>
                <?php foreach ($coupons as $coupon): ?><tr><td><strong><?= htmlspecialchars($coupon['code']) ?></strong></td><td><?= htmlspecialchars(truncate_text($coupon['title'], 24)) ?></td><td><?= $coupon['discount_type'] === 'percentage' ? $coupon['discount_value'] . '%' : CURRENCY_SYMBOL . number_format($coupon['discount_value']) ?></td><td><?= (int)$coupon['min_quantity'] ?></td><td><?= (int)$coupon['used_count'] ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
