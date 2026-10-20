<?php
/**
 * EVENTO - Logout
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    log_activity($_SESSION['user_id'], 'logout', 'User logged out');
}

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

session_unset();
session_destroy();

$_SESSION['success'] = 'You have been logged out successfully.';
header('Location: ' . SITE_URL . 'auth/login.php');
exit();
