<?php
/**
 * EVENTO - Single Event Detail Page
 * With booking section, reviews, and seat selection
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$slug = sanitize_input($_GET['slug'] ?? '');
$requested_quantity = max(1, (int)($_GET['quantity'] ?? 1));
if (empty($slug)) {
    header('Location: ' . SITE_URL . 'events.php');
    exit();
}

$event = fetch_single(
    "SELECT e.*, c.category_name, c.category_slug, c.category_icon, ci.city_name, ci.state_id, s.state_name, u.full_name as organizer_name, u.id as organizer_id, u.profile_photo as organizer_photo, u.is_organizer_approved as organizer_verified
    FROM events e
    JOIN categories c ON e.category_id = c.id
    JOIN cities ci ON e.city_id = ci.id
    LEFT JOIN states s ON ci.state_id = s.id
    JOIN users u ON e.user_id = u.id
    WHERE e.slug = ? AND e.status = 'approved'",
    "s", [$slug]
);

if (!$event) {
    $_SESSION['error'] = 'Event not found';
    header('Location: ' . SITE_URL . 'events.php');
    exit();
}

// Update views
mysqli_query($conn, "UPDATE events SET views = views + 1 WHERE id = " . $event['id']);

// Get gallery images
$gallery = fetch_all("SELECT * FROM event_gallery WHERE event_id = ?", "i", [$event['id']]);

// Get event FAQs
$event_faqs = fetch_all("SELECT * FROM event_faqs WHERE event_id = ? ORDER BY sort_order", "i", [$event['id']]);

// Get tickets
$tickets = fetch_all("SELECT * FROM tickets WHERE event_id = ? AND available > 0", "i", [$event['id']]);

// Get reviews
$reviews = fetch_all(
    "SELECT r.*, u.full_name, u.profile_photo FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.event_id = ? AND r.status = 1 
    ORDER BY r.created_at DESC LIMIT 10",
    "i", [$event['id']]
);

// Check if user has this event in wishlist
$in_wishlist = false;
if (is_logged_in()) {
    $wl = fetch_single("SELECT id FROM wishlist WHERE user_id = ? AND event_id = ?", "ii", [$_SESSION['user_id'], $event['id']]);
    $in_wishlist = $wl ? true : false;
}

// Get similar events
$similar = fetch_all(
    "SELECT e.*, c.category_name, c.category_icon, ci.city_name 
    FROM events e 
    JOIN categories c ON e.category_id = c.id 
    JOIN cities ci ON e.city_id = ci.id 
    WHERE e.category_id = ? AND e.id != ? AND e.status = 'approved' 
    ORDER BY RAND() LIMIT 4",
    "ii", [$event['category_id'], $event['id']]
);

$page_title = $event['title'];
include __DIR__ . '/includes/header.php';

$banner = event_banner($event['banner']);
?>

<!-- =====================================================
     BREADCRUMB
     ===================================================== -->
<section class="pt-5 pb-3">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>events.php">Events</a></li>
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>events.php?category=<?= $event['category_slug'] ?>"><?= htmlspecialchars($event['category_name']) ?></a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($event['title']) ?></li>
            </ol>
        </nav>
    </div>
</section>

<!-- =====================================================
     EVENT DETAIL
     ===================================================== -->
<section class="pb-5">
    <div class="container">
        <div class="row g-4">

            <!-- LEFT COLUMN - MAIN EVENT CONTENT -->
            <div class="col-lg-8">

                <!-- Event Banner -->
                <div class="position-relative rounded-4 overflow-hidden mb-4 shadow-sm" data-aos="fade-up">
                    <img src="<?= $banner ?>" 
                         class="w-100" alt="<?= htmlspecialchars($event['title']) ?>"
                         onerror="this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=1200&q=80'"
                         style="height: 400px; object-fit: cover;">
                    <div class="position-absolute top-0 start-0 p-3">
                        <span class="badge bg-primary fs-6"><?= category_icon($event['category_icon']) ?> <?= htmlspecialchars($event['category_name']) ?></span>
                        <?php if ($event['discount_price'] > 0 && $event['discount_price'] < $event['price']): ?>
                        <span class="badge bg-danger fs-6 ms-2"><?= round((1 - $event['discount_price'] / $event['price']) * 100) ?>% OFF</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Event Title & Info -->
                <div class="glass-card mb-4" data-aos="fade-up">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <h1 class="display-6 fw-bold mb-0"><?= htmlspecialchars($event['title']) ?></h1>
                        <?php if (is_logged_in()): ?>
                        <button class="btn btn-ghost <?= $in_wishlist ? 'text-danger' : '' ?>" 
                                onclick="toggleWishlist(<?= $event['id'] ?>, this)" 
                                title="<?= $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>">
                            <i class="fas fa-heart fa-lg"></i>
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Event Meta Info Grid -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-2">
                                <div class="stat-icon bg-primary-soft" style="width:40px;height:40px;">
                                    <i class="fas fa-calendar text-primary"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Date</small>
                                    <strong><?= format_date($event['event_date'], 'l, d F Y') ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-2">
                                <div class="stat-icon bg-info-soft" style="width:40px;height:40px;">
                                    <i class="fas fa-clock text-info"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Time</small>
                                    <strong><?= date('h:i A', strtotime($event['event_time'])) ?> 
                                        <?= $event['end_time'] ? '- ' . date('h:i A', strtotime($event['end_time'])) : '' ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-2">
                                <div class="stat-icon bg-success-soft" style="width:40px;height:40px;">
                                    <i class="fas fa-map-marker-alt text-success"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Venue</small>
                                    <strong><?= htmlspecialchars($event['venue']) ?></strong>
                                    <small class="d-block text-muted"><?= htmlspecialchars($event['city_name']) ?><?= $event['state_name'] ? ', ' . htmlspecialchars($event['state_name']) : '' ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-2">
                                <div class="stat-icon bg-warning-soft" style="width:40px;height:40px;">
                                    <i class="fas fa-user text-warning"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Organizer</small>
                                    <strong><?= htmlspecialchars($event['organizer_name']) ?></strong>
                                    <?php if (!empty($event['organizer_verified'])): ?><span class="badge bg-success-soft ms-1"><i class="fas fa-check-circle me-1"></i>Verified Organizer</span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- About Event -->
                    <h5 class="fw-bold mb-3">About This Event</h5>
                    <p class="text-secondary" style="line-height: 1.8;"><?= nl2br(htmlspecialchars($event['description'])) ?></p>

                    <?php if ($event['age_restriction'] && $event['age_restriction'] != 'All Ages'): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>Age Restriction: <?= htmlspecialchars($event['age_restriction']) ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($event['event_rules']): ?>
                    <div class="mt-4">
                        <h5 class="fw-bold mb-3">Event Rules</h5>
                        <p class="text-secondary"><?= nl2br(htmlspecialchars($event['event_rules'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($event['tags']): ?>
                    <div class="mt-4">
                        <h5 class="fw-bold mb-3">Tags</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach (explode(',', $event['tags']) as $tag): ?>
                            <span class="badge bg-primary-soft">#<?= htmlspecialchars(trim($tag)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Event Gallery -->
                <?php if (count($gallery) > 0): ?>
                <div class="glass-card mb-4" data-aos="fade-up">
                    <h5 class="fw-bold mb-3">Event Gallery</h5>
                    <div class="row g-2">
                        <?php foreach ($gallery as $img): ?>
                        <div class="col-4 col-md-3">
                            <img src="<?= SITE_URL ?>uploads/gallery/<?= $img['image'] ?>" class="w-100 rounded-3" style="height:120px;object-fit:cover;cursor:pointer;" alt="Gallery" onclick="window.open(this.src)">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Event FAQs -->
                <?php if (count($event_faqs) > 0): ?>
                <div class="glass-card mb-4" data-aos="fade-up">
                    <h5 class="fw-bold mb-3">Event FAQs</h5>
                    <div class="faq-accordion accordion" id="eventFaqAccordion">
                        <?php $ef = 0; foreach ($event_faqs as $efaq): $ef++; ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#efaq<?= $ef ?>">
                                    <?= htmlspecialchars($efaq['question']) ?>
                                </button>
                            </h2>
                            <div id="efaq<?= $ef ?>" class="accordion-collapse collapse" data-bs-parent="#eventFaqAccordion">
                                <div class="accordion-body"><?= htmlspecialchars($efaq['answer']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Reviews -->
                <div class="glass-card mb-4" data-aos="fade-up">
                    <h5 class="fw-bold mb-3">Reviews (<?= count($reviews) ?>)</h5>
                    <?php if (count($reviews) > 0): ?>
                        <?php foreach ($reviews as $review): ?>
                        <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                            <img src="<?= SITE_URL ?>uploads/users/<?= htmlspecialchars(empty($review['profile_photo']) || $review['profile_photo'] === 'default-user.png' ? 'default-user.svg' : $review['profile_photo']) ?>" 
                                 class="rounded-circle" width="48" height="48" style="object-fit:cover;"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($review['full_name']) ?>&background=6C3CE1&color=fff'">
                            <div>
                                <strong><?= htmlspecialchars($review['full_name']) ?></strong>
                                <div class="text-warning small mb-1">
                                    <?php for ($s = 0; $s < 5; $s++): ?>
                                        <i class="fas fa-star<?= $s < $review['rating'] ? '' : '-o' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="mb-0"><?= htmlspecialchars($review['review_text']) ?></p>
                                <small class="text-muted"><?= time_elapsed($review['created_at']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No reviews yet. Be the first to review!</p>
                    <?php endif; ?>

                    <?php if (is_logged_in()): ?>
                    <form method="POST" action="<?= SITE_URL ?>api/review.php" class="mt-3">
                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                        <?= csrf_field() ?>
                        <label class="form-label">Rate this event</label>
                        <div class="mb-2 star-rating">
                            <?php for ($r = 5; $r >= 1; $r--): ?>
                            <input type="radio" name="rating" value="<?= $r ?>" id="star<?= $r ?>" class="d-none">
                            <label for="star<?= $r ?>" class="fs-4" style="color: #F59E0B; cursor: pointer;" onclick="document.querySelector('input[name=rating][value=<?= $r ?>]').checked = true;">
                                <i class="far fa-star"></i>
                            </label>
                            <?php endfor; ?>
                        </div>
                        <textarea name="review_text" class="form-control mb-2" rows="3" placeholder="Write your review..."></textarea>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Submit Review</button>
                    </form>
                    <?php endif; ?>
                </div>

            </div><!-- /.col-lg-8 (Main Content) -->

            <!-- RIGHT COLUMN - BOOKING SIDEBAR -->
            <div class="col-lg-4">
                <div class="glass-card sticky-top" style="top: 90px;" data-aos="fade-left">
                    <h4 class="fw-bold mb-3">Book Tickets</h4>

                    <?php if ($event['available_seats'] <= 0): ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-times-circle fa-2x mb-2"></i>
                        <h6>Sold Out</h6>
                        <p class="mb-0 small">No tickets available for this event</p>
                    </div>
                    <?php elseif (strtotime($event['event_date']) < strtotime('today')): ?>
                    <div class="alert alert-secondary text-center">
                        <i class="fas fa-calendar-check fa-2x mb-2"></i>
                        <h6>Event Ended</h6>
                        <p class="mb-0 small">This event has already taken place</p>
                    </div>
                    <?php else: ?>

                    <!-- Price Display -->
                    <div class="text-center mb-4">
                        <?php if ($event['discount_price'] > 0 && $event['discount_price'] < $event['price']): ?>
                        <h1 class="display-4 fw-bold text-gradient mb-0"><?= CURRENCY_SYMBOL ?><?= number_format($event['discount_price']) ?></h1>
                        <small class="text-decoration-line-through text-muted"><?= CURRENCY_SYMBOL ?><?= number_format($event['price']) ?></small>
                        <span class="badge bg-success ms-2">Save <?= CURRENCY_SYMBOL ?><?= number_format($event['price'] - $event['discount_price']) ?></span>
                        <?php else: ?>
                        <h1 class="display-4 fw-bold text-gradient mb-0"><?= CURRENCY_SYMBOL ?><?= number_format($event['price']) ?></h1>
                        <?php endif; ?>
                        <small class="text-muted">per person</small>
                    </div>

                    <hr>

                    <!-- Availability -->
                    <div class="d-flex justify-content-between mb-3">
                        <span><i class="fas fa-chair me-2 text-success"></i>Available Seats</span>
                        <strong class="badge bg-<?= $event['available_seats'] < 20 ? 'danger' : 'success' ?>"><?= $event['available_seats'] ?></strong>
                    </div>

                    <hr>

                    <!-- Booking Form -->
                    <form action="<?= SITE_URL ?>booking/checkout.php" method="POST">
                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                        <?= csrf_field() ?>

                        <?php if (count($tickets) > 0): ?>
                        <input type="hidden" name="quantity" id="ticketQuantity" value="<?= $requested_quantity ?>">
                        <label class="form-label">Select Ticket Type</label>
                        <?php foreach ($tickets as $ticket): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded-3 border">
                            <div>
                                <strong><?= htmlspecialchars($ticket['ticket_type']) ?></strong>
                                <small class="d-block text-muted"><?= CURRENCY_SYMBOL ?><?= number_format($ticket['price']) ?></small>
                            </div>
                            <input type="number" name="tickets[<?= $ticket['id'] ?>]" class="form-control ticket-quantity-input" style="width: 80px;" min="0" max="<?= min($ticket['available'], 10) ?>" value="0" placeholder="Qty">
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <label class="form-label">Number of Tickets</label>
                        <input type="number" name="quantity" class="form-control mb-3" min="1" max="<?= min($event['available_seats'], 10) ?>" value="1" required>
                        <?php endif; ?>

                        <?php if (is_logged_in()): ?>
                        <button type="submit" class="btn btn-primary btn-block btn-lg mt-3">
                            <i class="fas fa-shopping-cart me-2"></i>Book Now
                        </button>
                        <?php else: ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <a href="<?= SITE_URL ?>auth/login.php" class="fw-bold">Login</a> to book tickets
                        </div>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                    <?php if (count($tickets) > 0): ?>
                    <script>
                    document.querySelectorAll('.ticket-quantity-input').forEach(function(input) {
                        input.addEventListener('input', function() {
                            var total = 0;
                            document.querySelectorAll('.ticket-quantity-input').forEach(function(item) { total += parseInt(item.value || 0, 10); });
                            document.getElementById('ticketQuantity').value = Math.max(1, total);
                        });
                    });
                    </script>
                    <?php endif; ?>

                    <hr>

                    <!-- Share -->
                    <div class="d-flex align-items-center gap-3 mt-3">
                        <span class="text-muted small">Share:</span>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(SITE_URL . 'event.php?slug=' . $event['slug']) ?>" target="_blank" class="text-primary"><i class="fab fa-facebook"></i></a>
                        <a href="https://twitter.com/intent/tweet?text=<?= urlencode($event['title']) ?>&url=<?= urlencode(SITE_URL . 'event.php?slug=' . $event['slug']) ?>" target="_blank" class="text-info"><i class="fab fa-twitter"></i></a>
                        <a href="https://wa.me/?text=<?= urlencode($event['title'] . ' - ' . SITE_URL . 'event.php?slug=' . $event['slug']) ?>" target="_blank" class="text-success"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </div><!-- /.col-lg-4 (Booking Sidebar) -->

        </div><!-- /.row -->
    </div><!-- /.container -->
</section>

<!-- =====================================================
     SIMILAR EVENTS
     ===================================================== -->
<?php if (count($similar) > 0): ?>
<section class="pb-5">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <h2 class="section-title mb-0">Similar Events</h2>
        </div>
        <div class="row g-4">
            <?php foreach ($similar as $sim): ?>
            <div class="col-md-6 col-lg-3" data-aos="fade-up">
                <div class="card event-card h-100">
                    <div class="position-relative">
                        <img src="<?= event_banner($sim['banner']) ?>" 
                             class="card-img-top" alt="<?= htmlspecialchars($sim['title']) ?>"
                             onerror="this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=400&q=80'" style="height:150px;object-fit:cover;">
                        <div class="event-price small"><?= CURRENCY_SYMBOL ?><?= number_format((float)$sim['discount_price'] > 0 ? $sim['discount_price'] : $sim['price']) ?></div>
                    </div>
                    <div class="card-body">
                        <h6 class="card-title"><?= htmlspecialchars($sim['title']) ?></h6>
                        <small class="text-muted d-block"><i class="fas fa-calendar me-1"></i><?= format_date($sim['event_date']) ?></small>
                        <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($sim['city_name']) ?></small>
                        <a href="<?= SITE_URL ?>event.php?slug=<?= $sim['slug'] ?>" class="btn btn-outline btn-sm w-100 mt-2">View</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
function toggleWishlist(eventId, btn) {
    fetch('<?= SITE_URL ?>api/wishlist.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({event_id: eventId})
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'added') {
            btn.classList.add('text-danger');
            showToast('Added to wishlist!', 'success');
        } else if (data.status === 'removed') {
            btn.classList.remove('text-danger');
            showToast('Removed from wishlist', 'info');
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
