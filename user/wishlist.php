<?php
/**
 * EVENTO - User Wishlist
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_auth();

$page_title = 'My Wishlist';
$user_id = $_SESSION['user_id'];

$wishlist = mysqli_query($conn, "SELECT w.id as wid, w.created_at as w_created, e.*, c.category_name, c.category_icon, ci.city_name 
    FROM wishlist w JOIN events e ON w.event_id = e.id 
    JOIN categories c ON e.category_id = c.id 
    JOIN cities ci ON e.city_id = ci.id 
    WHERE w.user_id = $user_id AND e.status = 'approved' 
    ORDER BY w.created_at DESC");

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="padding-top: 100px !important;">
    <h2 class="fw-bold mb-4"><i class="fas fa-heart me-2 text-danger"></i>My Wishlist</h2>
    
    <?php if (mysqli_num_rows($wishlist) > 0): ?>
    <div class="row g-4">
        <?php while ($event = mysqli_fetch_assoc($wishlist)): ?>
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="card event-card h-100">
                <div class="position-relative">
                    <img src="<?= SITE_URL ?>uploads/events/<?= $event['banner'] ?? 'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=400&q=80' ?>" 
                         class="card-img-top" alt="<?= htmlspecialchars($event['title']) ?>"
                         onerror="this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=400&q=80'" style="height:160px;object-fit:cover;">
                    <div class="event-price"><?= CURRENCY_SYMBOL ?><?= number_format((float)$event['discount_price'] > 0 ? $event['discount_price'] : $event['price']) ?></div>
                    <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2" 
                            onclick="removeWishlist(<?= $event['wid'] ?>, this)" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="card-body">
                    <h6 class="card-title"><?= htmlspecialchars($event['title']) ?></h6>
                    <small class="text-muted d-block"><i class="fas fa-calendar me-1"></i><?= format_date($event['event_date']) ?></small>
                    <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($event['city_name']) ?></small>
                    <a href="<?= SITE_URL ?>event.php?slug=<?= $event['slug'] ?>" class="btn btn-primary btn-sm w-100 mt-2">Book Now</a>
                </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-heart fa-4x text-muted mb-3"></i>
        <h4>Your wishlist is empty</h4>
        <p class="text-muted">Save events you love to book later!</p>
        <a href="<?= SITE_URL ?>events.php" class="btn btn-primary"><i class="fas fa-search me-2"></i>Browse Events</a>
    </div>
    <?php endif; ?>
</div>

<script>
function removeWishlist(id, btn) {
    if (confirm('Remove from wishlist?')) {
        fetch('<?= SITE_URL ?>api/wishlist.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({remove_id: id})
        }).then(r => r.json()).then(d => {
            if (d.status === 'removed') {
                btn.closest('.col-md-6').remove();
                showToast('Removed from wishlist', 'info');
            }
        });
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
