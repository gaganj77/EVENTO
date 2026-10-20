<?php
/**
 * EVENTO - User/Organizer Registration
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Register';
$booking_mode = isset($_GET['booking']) && $_GET['booking'] === '1';

if (is_logged_in() && !($booking_mode && ($_SESSION['auth_provider'] ?? '') === 'google')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Your session expired. Please refresh the page and try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitize_input($_POST['role'] ?? 'user');
    
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif ($role !== 'user' && $role !== 'organizer') {
        $error = 'Invalid role selected';
    } else {
        // Check if email already exists
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            $error = 'Email already registered. Please login.';
        } else {
            mysqli_stmt_close($stmt);
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $otp = generate_otp(6);
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            // Pending organizers can sign in and see their approval status;
            // event creation remains protected until an admin approves them.
            $status = 1;
            
            $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, phone, password, role, otp, otp_expiry, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssssssi", $full_name, $email, $phone, $hashed_password, $role, $otp, $otp_expiry, $status);
            
            if (mysqli_stmt_execute($stmt)) {
                $user_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                $_SESSION['welcome_coupon'] = issue_welcome_coupon($user_id);
                
$_SESSION['otp_email'] = $email;
                $_SESSION['otp_user_id'] = $user_id;
                $_SESSION['otp_purpose'] = 'registration';
                
                // Send OTP via email
                $subject = 'Verify your Evento account';
                $message = "Hello $full_name,<br><br>Your OTP for registration is: <strong>$otp</strong><br>This OTP is valid for 10 minutes.<br><br>Regards,<br>" . SITE_NAME;
                if (!send_email($email, $subject, $message)) {
                    $_SESSION['otp_delivery_warning'] = 'We could not send the verification email. Check the mail server configuration, then use Resend OTP.';
                }
                
                log_activity($user_id, 'register', 'New account registered');
                
                header('Location: ' . SITE_URL . 'auth/otp-verify.php');
                exit();
            } else {
                mysqli_stmt_close($stmt);
                $error = 'Registration failed. Please try again.';
            }
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
        <div class="auth-card animate-scale-in">
            <div class="auth-header">
                <a href="<?= SITE_URL ?>index.php" class="auth-logo"><i class="fas fa-calendar-star me-2"></i>EVENTO</a>
                <h2 class="auth-title">Create Account</h2>
                <p class="auth-subtitle">Join Evento and discover amazing events</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" placeholder="Enter your full name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+91 9876543210" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password *</label>
                    <div class="password-input-group">
                        <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
                        <button type="button" class="password-toggle" tabindex="-1"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password *</label>
                    <div class="password-input-group">
                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                        <button type="button" class="password-toggle" tabindex="-1"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Register As</label>
                    <select name="role" class="form-select">
                        <option value="user" <?= ($_POST['role'] ?? 'user') == 'user' ? 'selected' : '' ?>>User - Book Events</option>
                        <option value="organizer" <?= ($_POST['role'] ?? '') == 'organizer' ? 'selected' : '' ?>>Organizer - Host Events</option>
                    </select>
                    <div class="form-text mt-2">
                        <strong>User:</strong> discover and book events.<br>
                        <strong>Organizer:</strong> create events after admin approval.<br>
                        <strong>Admin:</strong> manage users, organizers, events, and coupons through the separate Admin Login.
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </form>
            <div class="auth-divider"><span>OR</span></div>
            <div class="auth-footer">
                Already have an account? <a href="<?= SITE_URL ?>auth/login.php">Sign In</a>
            </div>
        </div>
    </div>
    <script src="<?= SITE_URL ?>js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>js/main.js"></script>
</body>
</html>
