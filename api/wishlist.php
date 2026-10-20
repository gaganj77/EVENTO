<?php
/**
 * EVENTO - Wishlist API (AJAX)
 *
 * Endpoints (all require login):
 *   GET  api/wishlist.php                      -> {status:'ok', event_ids:[...]}
 *   POST api/wishlist.php  {event_id: 12}      -> {status:'added'}
 *   POST api/wishlist.php  {remove_id: 3}      -> {status:'removed'}   (remove_id = wishlist.id OR event_id)
 *   POST api/wishlist.php  {toggle: 12}        -> {status:'added'|'removed'}
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Login required']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Accept JSON body as well as normal form posts
$raw = file_get_contents('php://input');
$payload = [];
if ($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}
$input = array_merge($_POST, $payload);

// ---------------------------------------------------------------
// GET : list saved events
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = fetch_all("SELECT w.id AS wishlist_id, w.event_id FROM wishlist w WHERE w.user_id = ? ORDER BY w.created_at DESC", "i", [$user_id]);
    echo json_encode([
        'status'    => 'ok',
        'count'     => count($rows),
        'event_ids' => array_map('intval', array_column($rows, 'event_id')),
        'items'     => $rows,
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// ---------------------------------------------------------------
// POST : toggle
// ---------------------------------------------------------------
if (isset($input['toggle'])) {
    $event_id = (int)$input['toggle'];
    $existing = fetch_single("SELECT id FROM wishlist WHERE user_id = ? AND event_id = ?", "ii", [$user_id, $event_id]);
    if ($existing) {
        execute_query("DELETE FROM wishlist WHERE id = ?", "i", [(int)$existing['id']]);
        echo json_encode(['status' => 'removed', 'event_id' => $event_id]);
    } else {
        insert_data("INSERT IGNORE INTO wishlist (user_id, event_id) VALUES (?, ?)", "ii", [$user_id, $event_id]);
        echo json_encode(['status' => 'added', 'event_id' => $event_id]);
    }
    exit();
}

// ---------------------------------------------------------------
// POST : remove
// ---------------------------------------------------------------
if (isset($input['remove_id'])) {
    $remove_id = (int)$input['remove_id'];
    // The UI sends wishlist.id, but accept an event id as well.
    $row = fetch_single("SELECT id FROM wishlist WHERE user_id = ? AND (id = ? OR event_id = ?)", "iii", [$user_id, $remove_id, $remove_id]);
    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Not in wishlist']);
        exit();
    }
    execute_query("DELETE FROM wishlist WHERE id = ? AND user_id = ?", "ii", [(int)$row['id'], $user_id]);
    echo json_encode(['status' => 'removed', 'remove_id' => $remove_id]);
    exit();
}

// ---------------------------------------------------------------
// POST : add
// ---------------------------------------------------------------
if (isset($input['event_id'])) {
    $event_id = (int)$input['event_id'];
    $event = fetch_single("SELECT id FROM events WHERE id = ? AND status = 'approved'", "i", [$event_id]);
    if (!$event) {
        echo json_encode(['status' => 'error', 'message' => 'Event not found']);
        exit();
    }
    $added = insert_data("INSERT IGNORE INTO wishlist (user_id, event_id) VALUES (?, ?)", "ii", [$user_id, $event_id]);
    echo json_encode(['status' => 'added', 'event_id' => $event_id, 'new' => $added !== false]);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Nothing to do']);

