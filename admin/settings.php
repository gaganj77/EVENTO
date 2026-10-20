<?php
/**
 * EVENTO - Admin Settings
 */
require_once __DIR__ . '/admin_auth.php';
$page_title = 'Site Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if ($key !== 'submit') {
            $val = mysqli_real_escape_string($conn, $value);
            $k = mysqli_real_escape_string($conn, $key);
            mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('$k', '$val') ON DUPLICATE KEY UPDATE setting_value = '$val'");
        }
    }
    $_SESSION['success'] = 'Settings saved!';
    header('Location: settings.php');
    exit();
}

$settings = mysqli_query($conn, "SELECT * FROM settings ORDER BY setting_group, setting_key");
$settings_data = [];
while ($s = mysqli_fetch_assoc($settings)) {
    $settings_data[$s['setting_group']][] = $s;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid py-4" style="padding-top: 90px !important;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-cog me-2 text-primary"></i>Site Settings</h2>
        <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
    
    <form method="POST">
        <?php foreach ($settings_data as $group => $group_settings): ?>
        <div class="glass-card mb-4">
            <h5 class="fw-bold mb-3 text-capitalize"><?= htmlspecialchars($group) ?> Settings</h5>
            <div class="row g-3">
                <?php foreach ($group_settings as $s): ?>
                <div class="col-md-6">
                    <label class="form-label"><?= ucwords(str_replace('_', ' ', $s['setting_key'])) ?></label>
                    <?php if (strpos($s['setting_key'], 'password') !== false || strpos($s['setting_key'], 'secret') !== false): ?>
                        <input type="password" name="<?= $s['setting_key'] ?>" class="form-control" value="<?= htmlspecialchars($s['setting_value']) ?>">
                    <?php elseif (strlen($s['setting_value']) > 100): ?>
                        <textarea name="<?= $s['setting_key'] ?>" class="form-control" rows="3"><?= htmlspecialchars($s['setting_value']) ?></textarea>
                    <?php else: ?>
                        <input type="text" name="<?= $s['setting_key'] ?>" class="form-control" value="<?= htmlspecialchars($s['setting_value']) ?>">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <button type="submit" name="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>Save Settings
        </button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
