<?php
/**
 * EVENTO - Header Template
 * Includes navigation bar with dark/light mode toggle
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
$user = is_logged_in() ? get_logged_in_user() : null;
$is_authenticated = (bool)$user || is_admin();

// If session says "logged in" but the user no longer exists (or is disabled),
// clear the stale session to avoid null/undefined errors.
if (is_logged_in() && !$user) {
    unset($_SESSION['user_id']);
    unset($_SESSION['user_role']);
    unset($_SESSION['organizer_approved']);
}

$unread_notifications = $user ? get_unread_notification_count($user['id']) : 0;
$coupon_notice = '';
if ($is_authenticated && !empty($_SESSION['welcome_coupon_notice'])) {
    $coupon_notice = $_SESSION['welcome_coupon_notice'];
    unset($_SESSION['welcome_coupon_notice']);
    $_SESSION['welcome_coupon_seen'] = true;
} elseif ($user && empty($_SESSION['welcome_coupon_seen'])) {
    $coupon_available = fetch_single(
        "SELECT c.id FROM coupons c WHERE c.code = 'EVENTO26' AND c.status = 1 AND c.valid_from <= NOW() AND c.valid_to >= NOW() AND NOT EXISTS (SELECT 1 FROM coupon_usage cu WHERE cu.coupon_id = c.id AND cu.user_id = ?)",
        "i",
        [$user['id']]
    );
    if ($coupon_available) {
        $coupon_notice = 'First booking rewards: EVENTO26 gives 10% off, or EGROUP26 gives 20% off on 3+ tickets.';
        $_SESSION['welcome_coupon'] = 'EVENTO26';
    }
    $_SESSION['welcome_coupon_seen'] = true;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Evento - Discover amazing events happening near you. Book tickets for concerts, workshops, meetups and more.">
    <meta name="keywords" content="events, tickets, concerts, workshops, meetups, entertainment">
    <meta name="author" content="Evento">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= SITE_NAME ?> - <?= SITE_TAGLINE ?>">
    <meta property="og:description" content="Discover amazing events happening near you">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= SITE_NAME ?>">
    
    <title><?= isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME . ' - ' . SITE_TAGLINE ?></title>
    
<!-- Bootstrap 5 CSS (local) -->
    <link rel="stylesheet" href="<?= SITE_URL ?>css/bootstrap.min.css">
    <!-- Font Awesome 6 (local) -->
    <link rel="stylesheet" href="<?= SITE_URL ?>css/all.min.css?v=2">
<!-- AOS Animation Library (local) -->
    <link rel="stylesheet" href="<?= SITE_URL ?>css/aos.css">
    <!-- Custom Theme CSS -->
    <link rel="stylesheet" href="<?= SITE_URL ?>css/style.css?v=20261009-admin-coupons-4">
    <!-- City Autocomplete CSS -->
    <link rel="stylesheet" href="<?= SITE_URL ?>css/autocomplete.css">
    <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>images/favicon.ico">
</head>
<body class="<?= !$is_authenticated ? 'auth-gated' : '' ?>">

<?php if (!$is_authenticated): ?>
<div class="auth-gate" role="dialog" aria-modal="true" aria-labelledby="authGateTitle">
    <div class="auth-gate-card">
        <a href="<?= SITE_URL ?>index.php" class="auth-logo"><i class="fas fa-calendar-star me-2"></i>EVENTO</a>
        <h1 id="authGateTitle">Sign in to continue</h1>
        <p>Sign in before exploring events, bookings, and your personal recommendations.</p>
        <a href="<?= SITE_URL ?>auth/google-login.php" class="btn btn-light border google-signin-button">
            <span class="google-g-mark" aria-hidden="true">G</span>
            Continue with Google
        </a>
        <div class="auth-gate-divider"><span>or</span></div>
        <a href="<?= SITE_URL ?>auth/login.php" class="auth-gate-link">Sign in with email</a>
        <a href="<?= SITE_URL ?>auth/register.php" class="auth-gate-register">Create an account</a>
    </div>
</div>
<?php endif; ?>

<!-- =====================================================
     NAVIGATION BAR
     ===================================================== -->
<nav class="navbar navbar-expand-lg fixed-top" id="mainNavbar">
    <div class="container">
        <a class="navbar-brand" href="<?= SITE_URL ?>index.php">
            <i class="fas fa-calendar-star me-2"></i>EVENTO
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="<?= SITE_URL ?>index.php">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= SITE_URL ?>events.php">
                        <i class="fas fa-calendar"></i> Events
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-layer-group"></i> Categories
                    </a>
                    <ul class="dropdown-menu">
                        <?php
                        $cat_result = mysqli_query($conn, "SELECT * FROM categories WHERE status = 1 LIMIT 8");
                        while ($cat = mysqli_fetch_assoc($cat_result)):
                        ?>
<li>
                            <a class="dropdown-item" href="<?= SITE_URL ?>events.php?category=<?= $cat['category_slug'] ?>">
                                <?= category_icon($cat['category_icon']) ?> <span class="ms-1"><?= $cat['category_name'] ?></span>
                            </a>
                        </li>
                        <?php endwhile; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= SITE_URL ?>events.php">View All</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= SITE_URL ?>contact.php">
                        <i class="fas fa-envelope"></i> Contact
                    </a>
                </li>
            </ul>
            
            <div class="d-flex align-items-center gap-3 nav-account-actions">
                <!-- Dark/Light Mode Toggle -->
                <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>
                
                <?php if ($user): ?>
                    <!-- Notifications -->
                    <div class="dropdown">
                        <button class="btn btn-ghost position-relative" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_notifications > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $unread_notifications ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                            <div class="dropdown-header d-flex justify-content-between align-items-center">
                                <strong>Notifications</strong>
                                <?php if ($unread_notifications > 0): ?>
                                <a href="<?= SITE_URL ?>user/notifications.php" class="text-primary small">View All</a>
                                <?php endif; ?>
                            </div>
                            <?php
                            $notifications = get_notifications($user['id'], 20);
                            if (count($notifications) > 0):
                                foreach ($notifications as $notif):
                            ?>
                            <a class="dropdown-item notification-item <?= $notif['is_read'] ? '' : 'bg-primary-soft' ?>" href="<?= htmlspecialchars($notif['link'] ?: '#') ?>">
                                <div class="notification-content">
                                    <strong class="notification-title"><?= htmlspecialchars($notif['title']) ?></strong>
                                    <p class="notification-message mb-0 small text-muted"><?= htmlspecialchars(truncate_text($notif['message'], 70)) ?></p>
                                    <small class="text-muted"><?= time_elapsed($notif['created_at']) ?></small>
                                </div>
                            </a>
                            <?php endforeach; else: ?>
                            <div class="dropdown-item text-center text-muted py-3">
                                <i class="fas fa-bell-slash fa-2x mb-2"></i>
                                <p class="mb-0">No notifications</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- User Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-ghost dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                            <?php $profile_photo = $user['profile_photo'] ?? ''; if ($profile_photo === '' || $profile_photo === 'default-user.png') $profile_photo = 'default-user.svg'; ?>
                            <img src="<?= SITE_URL ?>uploads/users/<?= htmlspecialchars($profile_photo) ?>" 
                                 alt="Profile" class="rounded-circle" width="32" height="32" style="object-fit: cover;">
                            <span class="d-none d-md-inline"><?= htmlspecialchars(explode(' ', (string)($user['full_name'] ?? ''))[0]) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?= SITE_URL ?>user/dashboard.php">
                                    <i class="fas fa-columns me-2"></i>Dashboard
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= SITE_URL ?>user/profile.php">
                                    <i class="fas fa-user me-2"></i>My Profile
                                </a>
                            </li>
<li>
                                <a class="dropdown-item" href="<?= SITE_URL ?>user/bookings.php">
                                    <i class="fas fa-ticket me-2"></i>My Bookings
                                </a>
                            </li>
                            <?php if ($user['role'] == 'organizer' && $user['is_organizer_approved'] == 1): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?= SITE_URL ?>organizer/dashboard.php">
                                    <i class="fas fa-chart-line me-2"></i>Organizer Panel
                                </a>
                            </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= SITE_URL ?>auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php elseif (is_admin()): ?>
                    <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-ghost">
                        <i class="fas fa-shield-alt"></i> Admin Dashboard
                    </a>
                    <a href="<?= SITE_URL ?>admin/logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php elseif (is_admin()): ?>
                    <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-ghost">
                        <i class="fas fa-shield-alt"></i> Admin Dashboard
                    </a>
                    <a href="<?= SITE_URL ?>admin/logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>auth/login.php" class="btn btn-ghost">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="<?= SITE_URL ?>auth/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- =====================================================
     MAIN CONTENT WRAPPER
     ===================================================== -->
<div class="main-wrapper">
<?php echo display_flash_message(); ?>
<?php if ($coupon_notice): ?>
<div class="welcome-coupon-alert" role="status">
    <i class="fas fa-gift me-2"></i><?= htmlspecialchars($coupon_notice) ?>
    <button type="button" class="welcome-coupon-close" aria-label="Dismiss coupon message" onclick="this.closest('.welcome-coupon-alert').remove()">&times;</button>
</div>
<?php endif; ?>
</div>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTop" title="Go to top">
    <i class="fas fa-arrow-up"></i>
</button>
