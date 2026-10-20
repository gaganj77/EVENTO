<?php
require_once __DIR__ . '/../includes/config.php';

if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
    $_SESSION['error'] = 'Google sign-in is not configured yet. Add the Google OAuth Client ID and Secret in includes/config.php.';
    header('Location: ' . SITE_URL . 'auth/login.php');
    exit();
}

$_SESSION['google_oauth_state'] = bin2hex(random_bytes(24));

$params = http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'online',
    'state' => $_SESSION['google_oauth_state'],
    'prompt' => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit();