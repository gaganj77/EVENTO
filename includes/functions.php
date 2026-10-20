<?php
/**
 * EVENTO - Core Functions
 * Security, validation, and utility functions
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// =====================================================
// CSRF PROTECTION
// =====================================================

/**
 * Generate CSRF Token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF Token
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

/**
 * CSRF Token Hidden Field
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

// =====================================================
// INPUT VALIDATION & SANITIZATION
// =====================================================

/**
 * Sanitize input to prevent XSS
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate Email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate Phone Number (Indian)
 */
function validate_phone($phone) {
    return preg_match('/^[6-9]\d{9}$/', $phone);
}

/**
 * Validate URL
 */
function validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL);
}

/**
 * Sanitize string for database (additional protection)
 */
function sanitize_string($data) {
    global $conn;
    if (is_array($data)) {
        return array_map('sanitize_string', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

// =====================================================
// SESSION & AUTHENTICATION
// =====================================================

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function is_admin() {
    return isset($_SESSION['admin_id']);
}

/**
 * Check if user is organizer
 */
function is_organizer() {
    return isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'organizer' && $_SESSION['organizer_approved'] == 1;
}

/**
 * Get current logged-in user data
 * NOTE: Named get_logged_in_user (not get_current_user) to avoid
 * clashing with PHP's built-in get_current_user() function.
 */
function get_logged_in_user() {
    global $conn;
    if (!is_logged_in()) return null;
    
    $user_id = (int)$_SESSION['user_id'];
    $result = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id AND status = 1");
    return mysqli_fetch_assoc($result);
}

/**
 * Get current admin data
 */
function get_current_admin() {
    global $conn;
    if (!is_admin()) return null;
    
    $admin_id = (int)$_SESSION['admin_id'];
    $result = mysqli_query($conn, "SELECT * FROM admins WHERE id = $admin_id AND status = 1");
    return mysqli_fetch_assoc($result);
}

/**
 * Require authentication - redirect if not logged in
 */
function require_auth() {
    if (!is_logged_in()) {
        $_SESSION['error'] = 'Please login to access this page';
        header('Location: ' . SITE_URL . 'auth/login.php');
        exit();
    }
}

/**
 * Require the separate booking login after a Google-only viewing session.
 */
function require_booking_auth() {
    require_auth();
    if (($_SESSION['auth_provider'] ?? 'email') === 'google' && empty($_SESSION['booking_authenticated'])) {
        $_SESSION['error'] = 'Please sign in or create your Evento booking account before booking a ticket.';
        header('Location: ' . SITE_URL . 'auth/login.php?booking=1');
        exit();
    }
}

/**
 * Require admin authentication
 */
function require_admin() {
    if (!is_admin()) {
        $_SESSION['error'] = 'Access denied. Admin only.';
        header('Location: ' . SITE_URL . 'admin/login.php');
        exit();
    }
}

/**
 * Require organizer authentication
 */
function require_organizer() {
    require_auth();
    if (!is_organizer()) {
        $_SESSION['error'] = 'Access denied. Organizer only.';
        header('Location: ' . SITE_URL . 'index.php');
        exit();
    }
}

/**
 * Issue one welcome coupon for a newly-created user.
 */
function issue_welcome_coupon($user_id) {
    global $conn;

    $coupons = [
        ['EVENTO26', 10, 1, 500],
        ['EGROUP26', 20, 3, 1000],
    ];
    foreach ($coupons as [$code, $discount, $min_quantity, $max_discount]) {
        $stmt = mysqli_prepare($conn, "INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, min_quantity, max_discount_amount, usage_limit, valid_from, valid_to, status) VALUES (?, 'percentage', ?, 0, ?, ?, 0, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 1) ON DUPLICATE KEY UPDATE discount_value = VALUES(discount_value), min_quantity = VALUES(min_quantity), max_discount_amount = VALUES(max_discount_amount), valid_to = VALUES(valid_to), status = 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sddd", $code, $discount, $min_quantity, $max_discount);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    return 'EVENTO26';
}

/**
 * Ensure the welcome reward appears once in the user's notification bell.
 */
function ensure_welcome_reward($user_id) {
    $coupon_code = issue_welcome_coupon($user_id);
    $existing = fetch_single("SELECT id FROM notifications WHERE user_id = ? AND type = 'reward' AND title = 'Welcome Reward' LIMIT 1", "i", [(int)$user_id]);
    if (!$existing) {
        create_notification(
            $user_id,
            'reward',
            'Welcome Reward',
            'First booking rewards: EVENTO26 gives 10% off, or EGROUP26 gives 20% off on 3+ tickets.',
            SITE_URL . 'user/bookings.php'
        );
    }
    return $coupon_code;
}

/**
 * Return the current booking status for an event card.
 */
function event_availability($event) {
    if ((int)($event['available_seats'] ?? 0) <= 0) {
        return ['label' => 'Tickets unavailable', 'class' => 'bg-danger-soft', 'icon' => 'fa-ban'];
    }

    $end_time = $event['end_time'] ?: '23:59:59';
    if (strtotime($event['event_date'] . ' ' . $end_time) < time()) {
        return ['label' => 'Closed', 'class' => 'bg-secondary-soft', 'icon' => 'fa-lock'];
    }

    return ['label' => 'Available', 'class' => 'bg-success-soft', 'icon' => 'fa-check-circle'];
}

/**
 * Log activity
 * Note: Uses NULL for user_id when the id is 0/invalid
 * to satisfy the foreign key constraint (ON DELETE SET NULL).
 */
function log_activity($user_id, $action, $description = '') {
    global $conn;
    
    $user_id = (int)$user_id;
    $user_id_val = $user_id > 0 ? $user_id : 'NULL';
    $action = sanitize_string($action);
    $description = sanitize_string($description);
    $ip = sanitize_string($_SERVER['REMOTE_ADDR'] ?? '');
    $user_agent = sanitize_string($_SERVER['HTTP_USER_AGENT'] ?? '');
    
    $query = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
              VALUES ($user_id_val, '$action', '$description', '$ip', '$user_agent')";
    mysqli_query($conn, $query);
}

/**
 * Log login history
 * Note: Uses NULL for user_id when the id is 0/invalid (e.g. failed login
 * with unknown user) to satisfy the foreign key constraint.
 */
function log_login($user_id, $role, $status, $reason = '') {
    global $conn;
    
    $user_id = (int)$user_id;
    $user_id_val = $user_id > 0 ? $user_id : 'NULL';
    $role = sanitize_string($role);
    $status = sanitize_string($status);
    $reason = sanitize_string($reason);
    $ip = sanitize_string($_SERVER['REMOTE_ADDR'] ?? '');
    $user_agent = sanitize_string($_SERVER['HTTP_USER_AGENT'] ?? '');
    
    $query = "INSERT INTO login_history (user_id, role, ip_address, user_agent, login_status, fail_reason) 
              VALUES ($user_id_val, '$role', '$ip', '$user_agent', '$status', '$reason')";
    mysqli_query($conn, $query);
}

/**
 * Log admin audit
 */
function log_audit($admin_id, $action, $entity_type, $entity_id = null, $old_values = null, $new_values = null) {
    global $conn;
    
    $admin_id = (int)$admin_id;
    $action = sanitize_string($action);
    $entity_type = sanitize_string($entity_type);
    $entity_id = $entity_id ? (int)$entity_id : 'NULL';
    $old_values = $old_values ? "'" . sanitize_string(json_encode($old_values)) . "'" : 'NULL';
    $new_values = $new_values ? "'" . sanitize_string(json_encode($new_values)) . "'" : 'NULL';
    $ip = sanitize_string($_SERVER['REMOTE_ADDR'] ?? '');
    
    $query = "INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, old_values, new_values, ip_address) 
              VALUES ($admin_id, '$action', '$entity_type', $entity_id, $old_values, $new_values, '$ip')";
    mysqli_query($conn, $query);
}

// =====================================================
// NOTIFICATION FUNCTIONS
// =====================================================

/**
 * Create notification for user
 */
function create_notification($user_id, $type, $title, $message, $link = '') {
    global $conn;
    
    $user_id = (int)$user_id;
    $type = sanitize_string($type);
    $title = sanitize_string($title);
    $message = sanitize_string($message);
    $link = sanitize_string($link);
    
    $query = "INSERT INTO notifications (user_id, type, title, message, link) 
              VALUES ($user_id, '$type', '$title', '$message', '$link')";
    return mysqli_query($conn, $query);
}

/**
 * Get unread notification count
 */
function get_unread_notification_count($user_id) {
    global $conn;
    $user_id = (int)$user_id;
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0");
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

/**
 * Get notifications for user
 */
function get_notifications($user_id, $limit = 10) {
    global $conn;
    $user_id = (int)$user_id;
    $limit = (int)$limit;
    $result = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT $limit");
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    return $notifications;
}

// =====================================================
// FORMATTING FUNCTIONS
// =====================================================

/**
 * Format currency
 */
function format_currency($amount) {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

/**
 * Format date
 */
function format_date($date, $format = '') {
    if (empty($format)) $format = DATE_FORMAT;
    return date($format, strtotime($date));
}

/**
 * Format date time
 */
function format_datetime($datetime, $format = '') {
    if (empty($format)) $format = DATETIME_FORMAT;
    return date($format, strtotime($datetime));
}

/**
 * Time elapsed (for notifications)
 */
function time_elapsed($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) return 'Just now';
    if ($difference < 3600) return floor($difference / 60) . ' min ago';
    if ($difference < 86400) return floor($difference / 3600) . ' hours ago';
    if ($difference < 604800) return floor($difference / 86400) . ' days ago';
    return date(DATE_FORMAT, $timestamp);
}

/**
 * Truncate text
 */
function truncate_text($text, $length = 100) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

/**
 * Generate unique slug
 */
function generate_slug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Generate unique booking ID
 */
function generate_booking_id() {
    $prefix = 'EVT';
    $unique = strtoupper(substr(uniqid(), -6));
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    return $prefix . $unique . $random;
}

/**
 * Generate OTP
 */
function generate_otp($length = 6) {
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

/**
 * Render category icon (handles Font Awesome class names)
 */
function category_icon($icon) {
    // If it already looks like a Font Awesome class (fa-*), render an <i> tag
    if (preg_match('/^fa[\w-]*\s/', trim($icon)) || preg_match('/^fa-[a-z-]+$/i', trim($icon))) {
        return '<i class="fas ' . htmlspecialchars($icon) . '"></i>';
    }
    // Fall back to rendering the stored value (e.g. emoji) as plain text
    return htmlspecialchars($icon);
}

/**
 * Get event banner URL (handles both full URLs and local file paths)
 */
function event_banner($banner) {
    if (empty($banner)) {
        return 'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=400&q=80';
    }
    // If it's already a full URL (http/https/data), return as is
    if (preg_match('/^https?:\/\//i', $banner) || strpos($banner, 'data:') === 0) {
        return $banner;
    }
    // Otherwise it's a local file path
    return SITE_URL . 'uploads/events/' . $banner;
}

/**
 * Get user IP
 */
function get_user_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

/**
 * Upload file with validation
 */
function upload_file($file, $target_dir, $allowed_types = null, $max_size = null) {
    if ($allowed_types === null) $allowed_types = ALLOWED_IMAGE_TYPES;
    if ($max_size === null) $max_size = MAX_FILE_SIZE;
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed with error code: ' . $file['error']];
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File size exceeds limit of ' . ($max_size / 1024 / 1024) . 'MB'];
    }
    
    // Validate file type
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types)) {
        return ['success' => false, 'error' => 'File type not allowed. Allowed: ' . implode(', ', $allowed_types)];
    }
    
    // Create directory if not exists
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Generate unique filename
    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target_path = $target_dir . $filename;
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Compress image if it's a JPEG/PNG
        compress_image($target_path, $ext, 80);
        return ['success' => true, 'filename' => $filename, 'path' => $target_path];
    }
    
    return ['success' => false, 'error' => 'Failed to move uploaded file'];
}

/**
 * Compress image
 */
function compress_image($source_path, $ext, $quality = 80) {
    $info = getimagesize($source_path);
    if ($info === false) return false;
    
    switch ($ext) {
        case 'jpeg':
        case 'jpg':
            $image = imagecreatefromjpeg($source_path);
            imagejpeg($image, $source_path, $quality);
            break;
        case 'png':
            $image = imagecreatefrompng($source_path);
            $png_quality = floor(($quality - 10) / 10);
            imagepng($image, $source_path, $png_quality);
            break;
        case 'webp':
            $image = imagecreatefromwebp($source_path);
            imagewebp($image, $source_path, $quality);
            break;
        default:
            return false;
    }
    
    imagedestroy($image);
    return true;
}

/**
 * Delete file
 */
function delete_file($filepath) {
    if (file_exists($filepath) && is_file($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Get pagination links
 */
function get_pagination($total_records, $per_page, $current_page, $url) {
    $total_pages = ceil($total_records / $per_page);
    if ($total_pages <= 1) return '';
    
    $html = '<nav><ul class="pagination justify-content-center">';
    
    // Previous
    $prev_class = $current_page <= 1 ? 'disabled' : '';
    $prev_url = $current_page > 1 ? $url . '&page=' . ($current_page - 1) : '#';
    $html .= '<li class="page-item ' . $prev_class . '"><a class="page-link" href="' . $prev_url . '">Previous</a></li>';
    
    // Pages
    for ($i = 1; $i <= $total_pages; $i++) {
        $active_class = $i == $current_page ? 'active' : '';
        $html .= '<li class="page-item ' . $active_class . '"><a class="page-link" href="' . $url . '&page=' . $i . '">' . $i . '</a></li>';
    }
    
    // Next
    $next_class = $current_page >= $total_pages ? 'disabled' : '';
    $next_url = $current_page < $total_pages ? $url . '&page=' . ($current_page + 1) : '#';
    $html .= '<li class="page-item ' . $next_class . '"><a class="page-link" href="' . $next_url . '">Next</a></li>';
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Send email (basic mail function - use PHPMailer in production)
 */
function send_email($to, $subject, $message, $from = '') {
    $smtp_host = getenv('SMTP_HOST') ?: '';
    $smtp_username = getenv('SMTP_USERNAME') ?: '';
    $smtp_password = getenv('SMTP_PASSWORD') ?: '';
    $smtp_port = (int)(getenv('SMTP_PORT') ?: 587);
    $smtp_encryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');
    $from = $from ?: (getenv('SMTP_FROM') ?: 'noreply@evento.com');
    $from_name = getenv('SMTP_FROM_NAME') ?: SITE_NAME;

    if ($smtp_host !== '' || $smtp_username !== '' || $smtp_password !== '') {
        if ($smtp_host === '' || $smtp_username === '' || $smtp_password === '' || $smtp_port < 1 || $smtp_port > 65535) {
            error_log('Evento SMTP is partially configured; email was not sent.');
            return false;
        }

        try {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $smtp_host;
            $mailer->SMTPAuth = true;
            $mailer->Username = $smtp_username;
            $mailer->Password = $smtp_password;
            $mailer->Port = $smtp_port;
            $mailer->Timeout = 10;
            $mailer->SMTPDebug = 0;
            if ($smtp_encryption === 'tls') {
                $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($smtp_encryption === 'ssl') {
                $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($smtp_encryption !== 'none') {
                error_log('Evento SMTP encryption must be tls, ssl, or none.');
                return false;
            }
            $mailer->setFrom($from, $from_name);
            $mailer->addAddress($to);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $message;
            $mailer->AltBody = trim(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $message)));
            return $mailer->send();
        } catch (\PHPMailer\PHPMailer\Exception $exception) {
            error_log('Evento SMTP delivery failed: ' . $exception->getMessage());
            return false;
        }
    }

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SITE_NAME . " <" . $from . ">" . "\r\n";
    
    $sent = @mail($to, $subject, $message, $headers);
    if (!$sent) {
        error_log('Evento email delivery failed; check the configured PHP mail transport.');
    }
    return $sent;
}

/**
 * Get settings value
 */
function get_setting($key, $default = '') {
    global $conn;
    $key = sanitize_string($key);
    $result = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = '$key'");
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Display flash message
 */
function display_flash_message() {
    $html = '';
    
    if (isset($_SESSION['success'])) {
        $html .= '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>' . $_SESSION['success'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['error'])) {
        $html .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>' . $_SESSION['error'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        unset($_SESSION['error']);
    }
    
    if (isset($_SESSION['warning'])) {
        $html .= '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>' . $_SESSION['warning'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        unset($_SESSION['warning']);
    }
    
    if (isset($_SESSION['info'])) {
        $html .= '<div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>' . $_SESSION['info'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        unset($_SESSION['info']);
    }
    
    return $html;
}
?>
