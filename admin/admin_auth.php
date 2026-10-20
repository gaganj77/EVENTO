<?php
/**
 * EVENTO - Admin Authentication Check
 */
// Session is already started in config.php - do NOT call session_start() again.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin/login.php');
    exit();
}

$admin_id = (int)$_SESSION['admin_id'];
$result = mysqli_query($conn, "SELECT * FROM admins WHERE id = $admin_id AND status = 1");
$admin = mysqli_fetch_assoc($result);

if (!$admin) {
    session_unset();
    session_destroy();
    header('Location: ' . SITE_URL . 'admin/login.php');
    exit();
}

define('IS_ADMIN', true);
?>
