<?php
/**
 * EVENTO - Reset Password
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Reset Password';
$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: ' . SITE_URL . 'auth/forgot-password.php');
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['error'] = 'Invalid or expired reset token';
    header('Location: ' . SITE_URL . 'auth/forgot-password.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hashed, $user['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $_SESSION['success'] = 'Password reset successfully! You can now login.';
        header('Location: ' . SITE_URL . 'auth/login.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= SITE_NAME ?></title>
    <link href="<?= SITE_URL ?>css/bootstrap.min.css" rel="stylesheet">
<link href="<?= SITE_URL ?>css/all.min.css?v=2" rel="stylesheet">
    <link href="" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card animate-scale-in" style="max-width: 420px;">
            <div class="auth-header">
                <a href="<?= SITE_URL ?>index.php" class="auth-logo"><i class="fas fa-calendar-star me-2"></i>EVENTO</a>
                <h2 class="auth-title">Reset Password</h2>
                <p class="auth-subtitle">Enter your new password</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <div class="password-input-group">
                        <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
                        <button type="button" class="password-toggle" tabindex="-1"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm Password</label>
                    <div class="password-input-group">
                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                        <button type="button" class="password-toggle" tabindex="-1"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Reset Password</button>
            </form>
        </div>
    </div>
    <script src="<?= SITE_URL ?>js/bootstrap.bundle.min.js"></script>
    <script src="<?= SITE_URL ?>js/main.js"></script>
</body>
</html>
