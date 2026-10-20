<?php
/**
 * EVENTO - Live Search API (AJAX)
 * Supports: event search and city autocomplete
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'events';
$query = mysqli_real_escape_string($conn, $_GET['q'] ?? '');

// =====================================================
// CITY AUTOCOMPLETE (AI style suggestions)
// =====================================================
if ($action === 'cities') {
    $cities = [];
    if (strlen($query) >= 1) {
        // Kota-only focus: only return the active city named "Kota"
        $sql = "SELECT ci.id, ci.city_name, 
                (SELECT COUNT(*) FROM events e WHERE e.city_id = ci.id AND e.status = 'approved') as event_count
                FROM cities ci 
                WHERE ci.status = 1 AND ci.city_name = 'Kota' AND ci.city_name LIKE '%$query%'
                ORDER BY event_count DESC, ci.city_name ASC 
                LIMIT 10";
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $cities[] = [
                'id' => $row['id'],
                'city_name' => $row['city_name'],
                'events' => $row['event_count']
            ];
        }
    }
    echo json_encode(['status' => 'success', 'cities' => $cities]);
    exit();
}

// =====================================================
// EVENT SEARCH
// =====================================================
$results = [];

if (strlen($query) >= 2) {
    $sql = "SELECT e.id, e.title, e.slug, e.price, e.discount_price, e.banner, c.category_name, ci.city_name, u.full_name as organizer_name 
            FROM events e 
            JOIN categories c ON e.category_id = c.id 
            JOIN cities ci ON e.city_id = ci.id 
            JOIN users u ON e.user_id = u.id 
            WHERE e.status = 'approved' 
            AND (e.title LIKE '%$query%' OR e.description LIKE '%$query%' OR e.tags LIKE '%$query%' OR c.category_name LIKE '%$query%' OR ci.city_name LIKE '%$query%' OR u.full_name LIKE '%$query%')
            LIMIT 10";
    
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $results[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'url' => SITE_URL . 'event.php?slug=' . $row['slug'],
            'image' => event_banner($row['banner'] ?? ''),
            'category' => $row['category_name'],
            'city' => $row['city_name'],
            'organizer' => $row['organizer_name'],
            'price' => number_format((float)$row['discount_price'] > 0 ? $row['discount_price'] : $row['price'])
        ];
    }
}

echo json_encode($results);
exit();
