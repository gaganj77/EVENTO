<?php
/**
 * EVENTO - Admin Login
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Session already started by config.php - do NOT call session_start() again.

// Already logged in as admin?
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

// If a normal user/organizer is already logged in, send them to the right place.
// If the session is stale (user no longer in DB), clear it so they can log in.
if (isset($_SESSION['user_id'])) {
    $u_result = mysqli_query($conn, "SELECT * FROM users WHERE id = " . (int)$_SESSION['user_id'] . " AND status = 1");
    $logged_user = mysqli_fetch_assoc($u_result);
    if ($logged_user) {
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'organizer' && $_SESSION['organizer_approved'] == 1) {
            header('Location: ' . SITE_URL . 'organizer/dashboard.php');
        } else {
            header('Location: ' . SITE_URL . 'index.php');
        }
        exit();
    }
    // Stale user session — clear it.
    unset($_SESSION['user_id']);
    unset($_SESSION['user_role']);
    unset($_SESSION['organizer_approved']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_email']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        // 1) Check admins table first
        $result = mysqli_query($conn, "SELECT * FROM admins WHERE (username = '$username' OR email = '$username') AND status = 1 LIMIT 1");
        $admin = mysqli_fetch_assoc($result);
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['auth_provider'] = 'email';
            $_SESSION['booking_authenticated'] = true;
            $_SESSION['welcome_coupon'] = 'EVENTO26';
            $_SESSION['welcome_coupon_notice'] = 'First booking rewards: EVENTO26 gives 10% off, or EGROUP26 gives 20% off on 3+ tickets.';
            mysqli_query($conn, "UPDATE admins SET last_login = NOW() WHERE id = {$admin['id']}");
            header('Location: dashboard.php');
            exit();
        }

        // 2) Also allow Organizers (from the users table) to log in here
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ? AND status = 1 LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $uresult = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($uresult);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['organizer_approved'] = $user['is_organizer_approved'];
            mysqli_query($conn, "UPDATE users SET last_login = NOW() WHERE id = " . $user['id']);
            if ($user['role'] === 'organizer' && $user['is_organizer_approved'] == 1) {
                header('Location: ' . SITE_URL . 'organizer/dashboard.php');
            } elseif ($user['role'] === 'organizer') {
                $_SESSION['info'] = 'Your organizer account is waiting for admin approval.';
                header('Location: ' . SITE_URL . 'user/dashboard.php');
            } else {
                header('Location: ' . SITE_URL . 'index.php');
            }
            exit();
        }

        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= SITE_NAME ?></title>
    <link href="<?= SITE_URL ?>css/bootstrap.min.css" rel="stylesheet">
<link href="<?= SITE_URL ?>css/all.min.css?v=2" rel="stylesheet">
    <link href="" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card animate-scale-in" style="max-width: 400px;">
            <div class="auth-header">
                <a href="<?= SITE_URL ?>index.php" class="auth-logo"><i class="fas fa-shield-alt me-2"></i>EVENTO</a>
                <h2 class="auth-title">Admin Login</h2>
                <p class="auth-subtitle">Sign in to manage the platform</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username or Email</label>
                    <input type="text" name="username" class="form-control" placeholder="admin" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="password-input-group">
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                        <button type="button" class="password-toggle" tabindex="-1"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In</button>
            </form>
            <div class="auth-footer mt-3">
                <a href="<?= SITE_URL ?>index.php"><i class="fas fa-arrow-left me-1"></i>Back to Website</a>
            </div>
        </div>
    </div>
    <script src="<?= SITE_URL ?>js/bootstrap.bundle.min.js"></script>
    <script src="<?= SITE_URL ?>js/main.js"></script>
</body>
</html>
