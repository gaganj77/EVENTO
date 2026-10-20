<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../includes/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}
mysqli_set_charset($conn, 'utf8mb4');

$username = trim((string)getenv('INITIAL_ADMIN_USERNAME'));
$email = filter_var(trim((string)getenv('INITIAL_ADMIN_EMAIL')), FILTER_VALIDATE_EMAIL);
$name = trim((string)getenv('INITIAL_ADMIN_NAME'));
$password = (string)getenv('INITIAL_ADMIN_PASSWORD');

if ($username === '' || !$email || $name === '' || strlen($password) < 16) {
    fwrite(STDERR, "Set INITIAL_ADMIN_USERNAME, INITIAL_ADMIN_EMAIL, INITIAL_ADMIN_NAME, and an INITIAL_ADMIN_PASSWORD of at least 16 characters.\n");
    exit(1);
}

$stmt = mysqli_prepare($conn, 'SELECT id FROM admins WHERE email = ? OR username = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ss', $email, $username);
mysqli_stmt_execute($stmt);
$existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
if ($existing) {
    fwrite(STDERR, "An administrator with that email or username already exists; no account was changed.\n");
    exit(1);
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);
$role = 'super_admin';
$stmt = mysqli_prepare($conn, 'INSERT INTO admins (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, ?, 1)');
mysqli_stmt_bind_param($stmt, 'sssss', $username, $email, $password_hash, $name, $role);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

fwrite(STDOUT, "Initial administrator created. Remove INITIAL_ADMIN_* environment variables from the hosting service now.\n");
