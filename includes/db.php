<?php
/**
 * EVENTO - Database Connection
 * Uses MySQLi with prepared statement support
 */

require_once __DIR__ . '/config.php';

// Turn off mysqli exceptions so we can handle connection failure gracefully
mysqli_report(MYSQLI_REPORT_OFF);

// Create database connection
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    http_response_code(503);
    $db_error = mysqli_connect_error();
    error_log('Evento database connection failed: ' . $db_error);
    $diagnostic = getenv('APP_ENV') === 'production'
        ? ''
        : "<div class='err'>Database error: " . htmlspecialchars($db_error) . "</div>";
    die("
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Service Temporarily Unavailable - " . SITE_NAME . "</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f6ff; color: #333; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .box { text-align: center; padding: 40px; max-width: 600px; }
        .icon { font-size: 56px; margin-bottom: 16px; }
        h1 { font-size: 28px; margin: 0 0 12px; color: #6C3CE1; }
        p { color: #666; line-height: 1.6; margin: 8px 0; }
        .err { font-size: 13px; color: #999; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='box'>
        <div class='icon'>🔌</div>
        <h1>Service Temporarily Unavailable</h1>
        <p>We couldn't connect to the database. Please check that the MySQL service is running (via XAMPP Control Panel) and try again.</p>
        <p>If you are the administrator, check the database service and connection settings.</p>
        " . $diagnostic . "
    </div>
</body>
</html>
");
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8mb4");

// Set timezone for the session
mysqli_query($conn, "SET time_zone = '+05:30'");

/**
 * Execute prepared statement and return result
 * 
 * @param string $sql SQL query with placeholders (?)
 * @param string $types Types of parameters (i, d, s, b)
 * @param array $params Array of parameters
 * @return mixed Query result
 */
function prepared_query($sql, $types = '', $params = []) {
    global $conn;
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return false;
    }
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Execute failed: " . mysqli_stmt_error($stmt));
        return false;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Get single row from prepared query
 */
function fetch_single($sql, $types = '', $params = []) {
    $result = prepared_query($sql, $types, $params);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

/**
 * Get all rows from prepared query
 */
function fetch_all($sql, $types = '', $params = []) {
    $result = prepared_query($sql, $types, $params);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    return $data;
}

/**
 * Insert data and return inserted ID
 */
function insert_data($sql, $types = '', $params = []) {
    global $conn;
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $insert_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return $insert_id;
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Update/Delete data and return affected rows
 */
function execute_query($sql, $types = '', $params = []) {
    global $conn;
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $affected;
    }
    
    mysqli_stmt_close($stmt);
    return false;
}
?>
