<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_GET['state'], $_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $_GET['state'])) {
    $_SESSION['error'] = 'Google sign-in could not be verified. Please try again.';
    header('Location: ' . SITE_URL . 'auth/login.php');
    exit();
}
unset($_SESSION['google_oauth_state']);

if (isset($_GET['error']) || empty($_GET['code'])) {
    $_SESSION['error'] = 'Google sign-in was cancelled.';
    header('Location: ' . SITE_URL . 'auth/login.php');
    exit();
}

$token_request = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($token_request, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $_GET['code'],
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
    ]),
]);
$token_response = json_decode(curl_exec($token_request), true);
curl_close($token_request);

if (empty($token_response['access_token'])) {
    $_SESSION['error'] = 'Google sign-in failed while exchanging the authorization code.';
    header('Location: ' . SITE_URL . 'auth/login.php');
    exit();
}

$profile_request = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt_array($profile_request, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token_response['access_token']],
]);
$profile = json_decode(curl_exec($profile_request), true);
curl_close($profile_request);

$email = filter_var($profile['email'] ?? '', FILTER_VALIDATE_EMAIL);
$name = trim($profile['name'] ?? 'Google User');
if (!$email) {
    $_SESSION['error'] = 'Google did not return a valid email address.';
    header('Location: ' . SITE_URL . 'auth/login.php');
    exit();
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM users WHERE email = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    $password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, 'INSERT INTO users (full_name, email, password, role, is_verified, status) VALUES (?, ?, ?, \'user\', 1, 1)');
    mysqli_stmt_bind_param($stmt, 'sss', $name, $email, $password);
    mysqli_stmt_execute($stmt);
    $user_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    $user = ['id' => $user_id, 'full_name' => $name, 'email' => $email, 'role' => 'user', 'is_organizer_approved' => 0];
    $_SESSION['welcome_coupon'] = issue_welcome_coupon($user_id);
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['organizer_approved'] = $user['is_organizer_approved'] ?? 0;
$_SESSION['auth_provider'] = 'google';
$_SESSION['booking_authenticated'] = false;
ensure_welcome_reward($user['id']);
$_SESSION['welcome_coupon'] = 'EVENTO26';
$_SESSION['welcome_coupon_notice'] = 'First booking rewards: EVENTO26 gives 10% off, or EGROUP26 gives 20% off on 3+ tickets.';
$_SESSION['success'] = 'Welcome to Evento, ' . $user['full_name'] . '!';

header('Location: ' . SITE_URL . 'index.php');
exit();