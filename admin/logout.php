<?php
/**
 * EVENTO - Admin Logout
 */
require_once __DIR__ . '/../includes/config.php';

session_unset();
session_destroy();
header('Location: ' . SITE_URL . 'admin/login.php');
exit();
