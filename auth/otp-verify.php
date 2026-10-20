<?php
/**
 * EVENTO - OTP Verification
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Verify OTP';
$error = '';
$success = '';
$delivery_warning = $_SESSION['otp_delivery_warning'] ?? '';
unset($_SESSION['otp_delivery_warning']);

if (!isset($_SESSION['otp_user_id']) || !isset($_SESSION['otp_email'])) {
    header('Location: ' . SITE_URL . 'auth/register.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Your session expired. Please refresh the page and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    $user_id = (int)$_SESSION['otp_user_id'];
    $email = $_SESSION['otp_email'];
    $otp = generate_otp();
    $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = mysqli_prepare($conn, "UPDATE users SET otp = ?, otp_expiry = ? WHERE id = ? AND is_verified = 0");
    mysqli_stmt_bind_param($stmt, "ssi", $otp, $otp_expiry, $user_id);
    $updated = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($updated) {
        $subject = 'Verify your Evento account';
        $message = "Your new OTP for registration is: <strong>$otp</strong><br>This OTP is valid for 10 minutes.<br><br>Regards,<br>" . SITE_NAME;
        if (send_email($email, $subject, $message)) {
            $success = 'A new OTP was sent to ' . $email;
        } else {
            $error = 'We could not send the verification email. Check the mail server configuration and try again.';
        }
    } else {
        $error = 'Could not generate a new OTP. Please try again.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = sanitize_input($_POST['otp'] ?? '');
    $user_id = (int)$_SESSION['otp_user_id'];
    
    if (empty($otp)) {
        $error = 'Please enter OTP';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? AND otp = ? AND otp_expiry > NOW() LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $user_id, $otp);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($user) {
            $stmt = mysqli_prepare($conn, "UPDATE users SET is_verified = 1, otp = NULL, otp_expiry = NULL WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            unset($_SESSION['otp_user_id'], $_SESSION['otp_email']);
            $_SESSION['success'] = 'Email verified successfully! You can now login.';
            header('Location: ' . SITE_URL . 'auth/login.php');
            exit();
        } else {
            $error = 'Invalid or expired OTP. Please try again.';
        }
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
                <h2 class="auth-title">Verify Email</h2>
                <p class="auth-subtitle">Enter OTP sent to <?= htmlspecialchars($_SESSION['otp_email']) ?></p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($delivery_warning): ?>
                <div class="alert alert-warning"><?= htmlspecialchars($delivery_warning) ?></div>
            <?php endif; ?>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label class="form-label">OTP Code</label>
                    <input type="text" name="otp" class="form-control text-center" placeholder="Enter 6-digit OTP" maxlength="6" style="font-size:1.5rem;letter-spacing:8px;text-align:center" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Verify OTP</button>
            </form>
            <form method="POST" class="auth-footer mt-3">
                <?= csrf_field() ?>
                <input type="hidden" name="resend_otp" value="1">
                Didn't receive OTP? <button type="submit" class="btn btn-link p-0 align-baseline">Resend OTP</button>
            </form>
        </div>
    </div>
    <script src="<?= SITE_URL ?>js/bootstrap.bundle.min.js"></script>
    <script src="<?= SITE_URL ?>js/main.js"></script>
</body>
</html>
