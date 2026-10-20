<?php
/**
 * EVENTO - Submit Review
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_auth();
    
    $event_id = (int)($_POST['event_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $review_text = sanitize_input($_POST['review_text'] ?? '');
    
    if ($event_id <= 0 || $rating < 1 || $rating > 5) {
        $_SESSION['error'] = 'Invalid review data';
    } else {
        $check = mysqli_query($conn, "SELECT id FROM bookings WHERE user_id = {$_SESSION['user_id']} AND event_id = $event_id AND status = 'confirmed'");
        
        if (mysqli_num_rows($check) > 0) {
            $existing = mysqli_query($conn, "SELECT id FROM reviews WHERE user_id = {$_SESSION['user_id']} AND event_id = $event_id");
            
            if (mysqli_num_rows($existing) > 0) {
                mysqli_query($conn, "UPDATE reviews SET rating = $rating, review_text = '$review_text' WHERE user_id = {$_SESSION['user_id']} AND event_id = $event_id");
                $_SESSION['success'] = 'Review updated successfully';
            } else {
                mysqli_query($conn, "INSERT INTO reviews (user_id, event_id, rating, review_text) VALUES ({$_SESSION['user_id']}, $event_id, $rating, '$review_text')");
                $_SESSION['success'] = 'Review submitted successfully';
                
                $avg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(rating) as avg_rating FROM reviews WHERE event_id = $event_id AND status = 1"));
                $new_rating = round($avg['avg_rating'], 1);
                mysqli_query($conn, "UPDATE events SET rating = $new_rating WHERE id = $event_id");
            }
        } else {
            $_SESSION['error'] = 'You can only review events you have attended';
        }
    }
}
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? SITE_URL . 'events.php'));
exit();
