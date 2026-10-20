<?php
/**
 * EVENTO - Configuration File
 * Contains all core settings and configurations
 */

// Optional per-host secrets stay out of the shared source tree.
$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

// Do not display diagnostics on a public deployment.
error_reporting(E_ALL);
$isProduction = getenv('APP_ENV') === 'production';
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Site URL (auto-detect) — always points to the project ROOT
// so CSS/JS/assets resolve correctly from any subdirectory page
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Determine the base URL path of the project root dynamically.
// We use the filesystem path of this config file to compute the correct
// web-relative base path, which is more reliable than parsing SCRIPT_NAME
// (especially when the project is nested, e.g. /evento/evento/).
$projectPath = realpath(__DIR__ . '/../'); // .../htdocs/evento/evento
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs');

$basePath = '';
if ($docRoot && strpos($projectPath, $docRoot) === 0) {
    // Convert filesystem path to URL path
    $rel = str_replace('\\', '/', substr($projectPath, strlen($docRoot)));
    $basePath = $rel;
}

$configuredSiteUrl = trim((string)(getenv('SITE_URL') ?: ''));
define('SITE_URL', $configuredSiteUrl !== ''
    ? rtrim($configuredSiteUrl, '/') . '/'
    : $protocol . $host . rtrim($basePath, '/') . '/');
define('SITE_PATH', $projectPath . '/');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'evento');

// Configure OAuth credentials in the environment and register SITE_URL/auth/google-callback.php.
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT_URI', SITE_URL . 'auth/google-callback.php');

// Site Configuration
define('SITE_NAME', 'Evento');
define('SITE_TAGLINE', 'Discover Amazing Events');
define('ADMIN_EMAIL', 'jaiswalgagan67@gmail.com');
define('CONTACT_EMAIL', 'evento.helpdesk@gmail.com');
define('CONTACT_PHONE', '+91 7415087072');
define('CURRENCY_SYMBOL', '₹');
define('CURRENCY_CODE', 'INR');

// Pagination
define('PER_PAGE', 12);
define('ADMIN_PER_PAGE', 20);

// File Upload
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_PATH', SITE_PATH . 'uploads/');

// Date Formats
define('DATE_FORMAT', 'd M, Y');
define('TIME_FORMAT', 'h:i A');
define('DATETIME_FORMAT', 'd M, Y h:i A');
?>
