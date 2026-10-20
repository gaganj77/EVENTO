<?php
/**
 * EVENTO - Newsletter Subscription API
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $check = mysqli_query($conn, "SELECT id FROM newsletter WHERE email = '$email'");
        if (mysqli_num_rows($check) == 0) {
            mysqli_query($conn, "INSERT INTO newsletter (email) VALUES ('$email')");
            $_SESSION['success'] = 'Successfully subscribed to newsletter!';
        } else {
            $_SESSION['info'] = 'Email already subscribed!';
        }
    } else {
        $_SESSION['error'] = 'Invalid email address';
    }
}
$redirect = $_SERVER['HTTP_REFERER'] ?? (SITE_URL . 'index.php');
header('Location: ' . $redirect);
exit();
