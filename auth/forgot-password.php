<?php
/**
 * EVENTO - Forgot Password
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Forgot Password';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email format';
    } else {
        // Always show success to prevent email enumeration
        $success = 'Password reset link has been sent to your email if it exists in our system.';
        
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt = mysqli_prepare($conn, "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "sss", $token, $expiry, $email);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
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
                <h2 class="auth-title">Forgot Password?</h2>
                <p class="auth-subtitle">Enter your email to receive a reset link</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Send Reset Link</button>
            </form>
<div class="auth-footer mt-3">
                <a href="<?= SITE_URL ?>auth/login.php">Back to Login</a>
            </div>
        </div>
    </div>
    <script src="<?= SITE_URL ?>js/bootstrap.bundle.min.js"></script>
    <script src="<?= SITE_URL ?>js/main.js"></script>
</body>
</html>
