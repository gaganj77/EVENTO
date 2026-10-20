<?php
/**
 * EVENTO - Admin Manage Coupons
 */
require_once __DIR__ . '/admin_auth.php';
$page_title = 'Manage Coupons';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (isset($_POST['delete_id'])) {
        $delete_id = (int)$_POST['delete_id'];
        $stmt = mysqli_prepare($conn, 'DELETE FROM coupons WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $delete_id);
        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
            $_SESSION['success'] = 'Coupon deleted.';
        } else {
            error_log('Evento admin coupon delete failed: ' . mysqli_stmt_error($stmt));
            $_SESSION['error'] = 'The coupon could not be deleted. Please try again.';
        }
        mysqli_stmt_close($stmt);
        header('Location: manage-coupons.php');
        exit();
    } else {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = $_POST['discount_type'] ?? '';
        $value = (float)($_POST['discount_value'] ?? 0);
        $min_amount = (float)($_POST['min_order_amount'] ?? 0);
        $min_quantity = (int)($_POST['min_quantity'] ?? 1);
        $usage_limit = (int)($_POST['usage_limit'] ?? 0);
        $valid_from_input = $_POST['valid_from'] ?? '';
        $valid_to_input = $_POST['valid_to'] ?? '';
        $valid_from_date = DateTime::createFromFormat('!Y-m-d', $valid_from_input);
        $valid_to_date = DateTime::createFromFormat('!Y-m-d', $valid_to_input);
        $dates_valid = $valid_from_date instanceof DateTime
            && $valid_from_date->format('Y-m-d') === $valid_from_input
            && $valid_to_date instanceof DateTime
            && $valid_to_date->format('Y-m-d') === $valid_to_input;

        if (!preg_match('/^[A-Z0-9_-]{1,50}$/', $code)) {
            $error = 'Enter a coupon code using only letters, numbers, hyphens, or underscores.';
        } elseif (!in_array($type, ['percentage', 'fixed'], true)) {
            $error = 'Select a valid discount type.';
        } elseif ($value <= 0 || ($type === 'percentage' && $value > 100)) {
            $error = $type === 'percentage'
                ? 'Percentage discounts must be greater than 0 and no more than 100.'
                : 'The discount value must be greater than 0.';
        } elseif ($min_amount < 0 || $min_quantity < 1 || $usage_limit < 0) {
            $error = 'Minimum order and usage limit cannot be negative, and minimum tickets must be at least one.';
        } elseif (!$dates_valid || $valid_to_date < $valid_from_date) {
            $error = 'Choose valid dates, with the end date on or after the start date.';
        } else {
            $valid_from = $valid_from_date->format('Y-m-d') . ' 00:00:00';
            $valid_to = $valid_to_date->format('Y-m-d') . ' 23:59:59';
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, min_quantity, usage_limit, valid_from, valid_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            mysqli_stmt_bind_param($stmt, 'ssddiiss', $code, $type, $value, $min_amount, $min_quantity, $usage_limit, $valid_from, $valid_to);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = 'Coupon added successfully.';
                mysqli_stmt_close($stmt);
                header('Location: manage-coupons.php');
                exit();
            }
            error_log('Evento admin coupon insert failed: ' . mysqli_stmt_error($stmt));
            $error = mysqli_stmt_errno($stmt) === 1062
                ? 'That coupon code already exists.'
                : 'The coupon could not be saved. Please check the values and try again.';
            mysqli_stmt_close($stmt);
        }
    }
}

$coupons = mysqli_query($conn, 'SELECT * FROM coupons ORDER BY valid_to DESC, id DESC');
if (!$coupons) {
    error_log('Evento admin coupon list query failed: ' . mysqli_error($conn));
    http_response_code(500);
    exit('Unable to load coupons right now.');
}

include __DIR__ . '/../includes/header.php';
?>

<main class="container-fluid py-4 admin-coupons-page" style="padding-top: 90px !important;">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-gift me-2 text-primary"></i>Manage Coupons</h2>
            <p class="text-muted mb-0">Create and manage discounts for event bookings.</p>
        </div>
        <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <div class="col-12 col-xl-4">
            <section class="glass-card h-100">
                <h5 class="fw-bold mb-3">Add Coupon</h5>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="couponCode">Code</label>
                            <input id="couponCode" type="text" name="code" class="form-control" required maxlength="50" pattern="[A-Za-z0-9_-]+" placeholder="SAVE20" value="<?= htmlspecialchars($_POST['code'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="discountType">Type</label>
                            <select id="discountType" name="discount_type" class="form-select" required>
                                <option value="percentage" <?= ($_POST['discount_type'] ?? 'percentage') === 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                                <option value="fixed" <?= ($_POST['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed (<?= CURRENCY_SYMBOL ?>)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="discountValue">Discount Value</label>
                            <input id="discountValue" type="number" name="discount_value" class="form-control" min="0.01" step="0.01" required value="<?= htmlspecialchars($_POST['discount_value'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="minOrder">Minimum Order (<?= CURRENCY_SYMBOL ?>)</label>
                            <input id="minOrder" type="number" name="min_order_amount" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars($_POST['min_order_amount'] ?? '0') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="usageLimit">Usage Limit <span class="text-muted fw-normal">(0 = unlimited)</span></label>
                            <input id="usageLimit" type="number" name="usage_limit" class="form-control" min="0" step="1" value="<?= htmlspecialchars($_POST['usage_limit'] ?? '0') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="minQuantity">Minimum Tickets</label>
                            <input id="minQuantity" type="number" name="min_quantity" class="form-control" min="1" step="1" value="<?= htmlspecialchars($_POST['min_quantity'] ?? '1') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="validFrom">Valid From</label>
                            <input id="validFrom" type="date" name="valid_from" class="form-control" required value="<?= htmlspecialchars($_POST['valid_from'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="validTo">Valid Until</label>
                            <input id="validTo" type="date" name="valid_to" class="form-control" required value="<?= htmlspecialchars($_POST['valid_to'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-plus me-2"></i>Add Coupon</button>
                </form>
            </section>
        </div>

        <div class="col-12 col-xl-8">
            <section class="glass-card admin-coupon-list overflow-hidden">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 p-md-4 pb-3">
                    <h5 class="fw-bold mb-0">Existing Coupons</h5>
                    <span class="badge bg-primary-soft"><?= number_format(mysqli_num_rows($coupons)) ?> total</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle admin-coupons-table">
                        <thead>
                            <tr><th>Code</th><th>Discount</th><th>Minimum</th><th>Used</th><th>Valid Until</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($coupons) === 0): ?>
                                <tr><td colspan="7" class="text-center text-muted py-5">No coupons yet. Add one using the form.</td></tr>
                            <?php else: ?>
                                <?php while ($coupon = mysqli_fetch_assoc($coupons)): ?>
                                    <?php
                                    $now = time();
                                    if (!(bool)$coupon['status']) {
                                        $status_label = 'Disabled';
                                        $status_class = 'bg-secondary';
                                    } elseif (strtotime($coupon['valid_from']) > $now) {
                                        $status_label = 'Scheduled';
                                        $status_class = 'bg-warning-soft';
                                    } elseif (strtotime($coupon['valid_to']) < $now) {
                                        $status_label = 'Expired';
                                        $status_class = 'bg-danger-soft';
                                    } elseif ((int)$coupon['usage_limit'] > 0 && (int)$coupon['used_count'] >= (int)$coupon['usage_limit']) {
                                        $status_label = 'Limit reached';
                                        $status_class = 'bg-danger-soft';
                                    } else {
                                        $status_label = 'Active';
                                        $status_class = 'bg-success-soft';
                                    }
                                    ?>
                                    <tr>
                                        <td><strong class="text-primary"><?= htmlspecialchars($coupon['code']) ?></strong></td>
                                        <td><?= $coupon['discount_type'] === 'percentage' ? number_format((float)$coupon['discount_value'], 2) . '%' : CURRENCY_SYMBOL . number_format((float)$coupon['discount_value'], 2) ?></td>
                                        <td><small><?= CURRENCY_SYMBOL ?><?= number_format((float)$coupon['min_order_amount'], 2) ?><br><?= (int)$coupon['min_quantity'] ?> ticket(s)</small></td>
                                        <td><?= (int)$coupon['used_count'] ?> / <?= (int)$coupon['usage_limit'] ?: 'Unlimited' ?></td>
                                        <td><small><?= htmlspecialchars(format_date($coupon['valid_to'])) ?></small></td>
                                        <td><span class="badge <?= $status_class ?>"><?= htmlspecialchars($status_label) ?></span></td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Delete this coupon?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="delete_id" value="<?= (int)$coupon['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete <?= htmlspecialchars($coupon['code']) ?>"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</main>

<script>
    const couponType = document.getElementById('discountType');
    const couponValue = document.getElementById('discountValue');
    const updateCouponValueLimit = () => {
        if (couponType.value === 'percentage') {
            couponValue.max = '100';
        } else {
            couponValue.removeAttribute('max');
        }
    };
    couponType.addEventListener('change', updateCouponValueLimit);
    updateCouponValueLimit();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
