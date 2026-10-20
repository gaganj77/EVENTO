<?php
/**
 * EVENTO - User/Organizer Login
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Login';
$booking_mode = isset($_GET['booking']) && $_GET['booking'] === '1';

// If a user is already logged in, redirect them. But if the session points
// to a user that no longer exists (stale from a deleted/reset account),
// clear it so the login form can be shown again.
if (is_logged_in() && !($booking_mode && ($_SESSION['auth_provider'] ?? '') === 'google')) {
    $logged_user = get_logged_in_user();
    if ($logged_user) {
        header('Location: ' . SITE_URL . 'index.php');
        exit();
    }
    // Stale session — clear it and let the user log in fresh.
    unset($_SESSION['user_id']);
    unset($_SESSION['user_role']);
    unset($_SESSION['organizer_approved']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_email']);
}

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email format';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ? AND status = 1 LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['organizer_approved'] = $user['is_organizer_approved'];
                        $_SESSION['auth_provider'] = 'email';
                        $_SESSION['booking_authenticated'] = true;
                        ensure_welcome_reward($user['id']);
                        $_SESSION['welcome_coupon'] = 'EVENTO26';
                        $_SESSION['success'] = 'Welcome back, ' . $user['full_name'] . '!';
                        $_SESSION['welcome_coupon_notice'] = 'First booking rewards: EVENTO26 gives 10% off, or EGROUP26 gives 20% off on 3+ tickets.';
            
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + 86400 * 30, '/');
                $hashed_token = password_hash($token, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE users SET remember_token = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $hashed_token, $user['id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            
            mysqli_query($conn, "UPDATE users SET last_login = NOW() WHERE id = " . $user['id']);
            log_login($user['id'], $user['role'], 'success');
            log_activity($user['id'], 'login', 'User logged in');
            
            $_SESSION['success'] = 'Welcome back, ' . $user['full_name'] . '!';
            
            if ($user['role'] === 'admin') {
                header('Location: ' . SITE_URL . 'admin/dashboard.php');
            } elseif ($user['role'] === 'organizer' && $user['is_organizer_approved'] == 1) {
                header('Location: ' . SITE_URL . 'organizer/dashboard.php');
            } elseif ($user['role'] === 'organizer') {
                $_SESSION['info'] = 'Your organizer account is waiting for admin approval.';
                header('Location: ' . SITE_URL . 'user/dashboard.php');
            } else {
                header('Location: ' . SITE_URL . 'index.php');
            }
            exit();
        }

        // Also try the admins table so the Admin can log in from the SAME login page
        $stmt = mysqli_prepare($conn, "SELECT * FROM admins WHERE email = ? AND status = 1 LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($admin && password_verify($password, $admin['password'])) {
            // Clear any stale user session so admin login is clean and header.php resolves correctly
            unset($_SESSION['user_id']);
            unset($_SESSION['user_name']);
            unset($_SESSION['user_role']);
            unset($_SESSION['user_email']);
            unset($_SESSION['organizer_approved']);
            // Reset with a new session id to avoid session fixation
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            mysqli_query($conn, "UPDATE admins SET last_login = NOW() WHERE id = " . $admin['id']);
            $_SESSION['success'] = 'Welcome back, ' . $admin['full_name'] . '!';
            header('Location: ' . SITE_URL . 'admin/dashboard.php');
            exit();
        }

        log_login(0, 'user', 'failed', 'Invalid credentials');
        $error = 'Invalid email or password';
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
        <div class="auth-card animate-scale-in">
            <div class="auth-header">
                <a href="<?= SITE_URL ?>index.php" class="auth-logo"><i class="fas fa-calendar-star me-2"></i>EVENTO</a>
                <h2 class="auth-title">Welcome Back</h2>
                <p class="auth-subtitle">Sign in to your account</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="password-input-group">
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" tabindex="-1"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input type="checkbox" name="remember" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Remember Me</label>
                    </div>
                    <a href="<?= SITE_URL ?>auth/forgot-password.php" class="text-primary small">Forgot Password?</a>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In</button>
            </form>
            <div class="auth-divider"><span>OR</span></div>
            <a id="google-signin" href="<?= SITE_URL ?>auth/google-login.php" class="btn btn-light border w-100 google-signin-button" aria-label="Continue with Google">
                <span class="google-g-mark" aria-hidden="true">G</span>
                Continue with Google
            </a>
            <div class="auth-footer">
                Don't have an account? <a href="<?= SITE_URL ?>auth/register.php<?= $booking_mode ? '?booking=1' : '' ?>">Create Account</a>
            </div>
        </div>
    </div>
    <script src="<?= SITE_URL ?>js/bootstrap.bundle.min.js"></script>
    <script src="<?= SITE_URL ?>js/main.js"></script>
</body>
</html>
