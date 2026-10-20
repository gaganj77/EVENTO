<?php
/**
 * EVENTO - Home Page
 * Professional landing page with hero, featured events, categories, testimonials
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Home';

// Get featured events
$featured = mysqli_query($conn, "SELECT e.*, c.category_name, c.category_icon, ci.city_name, u.full_name as organizer_name 
    FROM events e 
    JOIN categories c ON e.category_id = c.id 
    JOIN cities ci ON e.city_id = ci.id 
    JOIN users u ON e.user_id = u.id 
    WHERE e.status = 'approved' AND e.is_featured = 1 
    ORDER BY e.created_at DESC LIMIT 6");

// Get upcoming events
$upcoming = mysqli_query($conn, "SELECT e.*, c.category_name, c.category_icon, ci.city_name, u.full_name as organizer_name 
    FROM events e 
    JOIN categories c ON e.category_id = c.id 
    JOIN cities ci ON e.city_id = ci.id 
    JOIN users u ON e.user_id = u.id 
    WHERE e.status = 'approved' AND e.event_date >= CURDATE() 
    ORDER BY e.event_date ASC LIMIT 8");

// Get trending events (most booked)
$trending = mysqli_query($conn, "SELECT e.*, c.category_name, c.category_icon, ci.city_name, u.full_name as organizer_name 
    FROM events e 
    JOIN categories c ON e.category_id = c.id 
    JOIN cities ci ON e.city_id = ci.id 
    JOIN users u ON e.user_id = u.id 
    WHERE e.status = 'approved' 
    ORDER BY e.booked_seats DESC, e.views DESC LIMIT 4");

// Get categories
$categories = mysqli_query($conn, "SELECT c.*, (SELECT COUNT(*) FROM events WHERE category_id = c.id AND status = 'approved') as event_count FROM categories c WHERE c.status = 1 ORDER BY event_count DESC");

// Get cities
$cities = mysqli_query($conn, "SELECT ci.*, (SELECT COUNT(*) FROM events WHERE city_id = ci.id AND status = 'approved') as event_count FROM cities ci WHERE ci.status = 1 ORDER BY event_count DESC LIMIT 8");

// Get testimonials
$testimonials = mysqli_query($conn, "SELECT * FROM testimonials WHERE status = 1 ORDER BY sort_order ASC LIMIT 6");

// Get sponsors
$sponsors = mysqli_query($conn, "SELECT * FROM sponsors WHERE status = 1 ORDER BY sort_order ASC");

// Get FAQs
$faqs = mysqli_query($conn, "SELECT * FROM faq WHERE status = 1 ORDER BY sort_order ASC LIMIT 6");

// =====================================================
// LIVE STATS (dynamically reflect DB counts)
// These update automatically when events/cities/users are added.
// =====================================================
$stat_events = fetch_single("SELECT COUNT(*) AS c FROM events WHERE status = 'approved'");
$stat_cities = fetch_single("SELECT COUNT(*) AS c FROM cities WHERE status = 1");
$stat_users  = fetch_single("SELECT COUNT(*) AS c FROM users WHERE status = 1");

$stat_events = $stat_events['c'] ?? 0;
$stat_cities = $stat_cities['c'] ?? 0;
$stat_users  = $stat_users['c'] ?? 0;

// Approximate active users for the "K+ Happy Users" display (real count of users)
$happy_users = $stat_users;

include __DIR__ . '/includes/header.php';
?>

<!-- =====================================================
     HERO BANNER
     ===================================================== -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7" data-aos="fade-up">
                <span class="hero-badge">
                    <i class="fas fa-bolt me-1"></i> India's Local Events Hub
                </span>
                <h1 class="hero-title mt-3">
                    Discover <span class="gradient-text">Amazing Events</span><br>Happening Near You
                </h1>
                <p class="hero-description mt-3">
                    From pulsating concerts and cultural festivals to workshops and comedy nights — find, book, and experience the best events in your city. One platform, endless experiences.
                </p>
                
                <!-- Search Bar -->
                <form action="<?= SITE_URL ?>events.php" method="GET" class="search-bar mt-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search events, categories, or cities..." aria-label="Search">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                </form>
                
<!-- Stats -->
                <div class="hero-stats mt-4">
                    <div class="hero-stat">
                        <div class="stat-number" data-count="<?= (int)$stat_events ?>"><?= (int)$stat_events ?></div>
                        <div class="stat-label">+ Events</div>
                    </div>
                    <div class="hero-stat">
                        <div class="stat-number" data-count="<?= (int)$stat_cities ?>"><?= (int)$stat_cities ?></div>
                        <div class="stat-label">+ Cities</div>
                    </div>
                    <div class="hero-stat">
                        <div class="stat-number" data-count="<?= (int)$happy_users ?>"><?= (int)$happy_users ?></div>
                        <div class="stat-label">K+ Happy Users</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 text-center" data-aos="fade-left">
                <div class="hero-image-wrap">
                    <img src="https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&q=80" 
                         alt="Events" class="img-fluid rounded-4 shadow-lg">
                    <div class="hero-float-card glass-card">
                        <i class="fas fa-ticket-alt text-primary"></i>
                        <div>
                            <strong>1,200+ Tickets</strong>
                            <small class="d-block">Booked this week</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =====================================================
     WHY EVENTO SECTION (Unique Value Proposition)
     ===================================================== -->
<section class="why-section py-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">Why Choose Evento?</h2>
            <p class="section-subtitle">We're different from the rest — here's how</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="0">
                <div class="feature-card">
                    <div class="feature-icon bg-primary-soft"><i class="fas fa-map-marker-alt text-primary"></i></div>
<h5>Hyper-Local Focus</h5>
                    <p>Discover events right in Kota — we bring local experiences to your doorstep, curated for our city.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-card">
                    <div class="feature-icon bg-success-soft"><i class="fas fa-shield-alt text-success"></i></div>
                    <h5>Verified Organizers</h5>
                    <p>Every organizer is vetted and approved by our team. Book with confidence knowing events are legitimate and safe.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-card">
                    <div class="feature-icon bg-warning-soft"><i class="fas fa-undo-alt text-warning"></i></div>
                    <h5>Easy Refunds</h5>
                    <p>Changed your plans? Cancel up to 48 hours before the event and get a full refund in 5-7 business days.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-card">
                    <div class="feature-icon bg-info-soft"><i class="fas fa-ticket-alt text-info"></i></div>
                    <h5>Instant QR Tickets</h5>
                    <p>Get your QR code ticket instantly after booking. No queues, no printouts needed — just scan and enter.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =====================================================
     CATEGORIES SECTION
     ===================================================== -->
<section class="py-5" style="background: var(--gradient-glass);">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div>
                <h2 class="section-title">Browse by Category</h2>
                <p class="section-subtitle">Find events that match your vibe</p>
            </div>
            <a href="<?= SITE_URL ?>events.php" class="btn btn-ghost d-none d-md-flex">
                View All <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
        <div class="row g-3">
            <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
            <div class="col-6 col-md-4 col-lg-3" data-aos="fade-up" data-aos-delay="<?= $i = ($i ?? 0) + 1; ?>">
                <a href="<?= SITE_URL ?>events.php?category=<?= $cat['category_slug'] ?>" class="text-decoration-none">
<div class="category-card">
                        <div class="category-icon"><?= category_icon($cat['category_icon']) ?></div>
                        <div class="category-name"><?= htmlspecialchars($cat['category_name']) ?></div>
                        <div class="category-count"><?= $cat['event_count'] ?> Events</div>
                    </div>
                </a>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- =====================================================
     FEATURED EVENTS SECTION
     ===================================================== -->
<section class="py-5">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div>
                <h2 class="section-title">🌟 Featured Events</h2>
                <p class="section-subtitle">Hand-picked events you don't want to miss</p>
            </div>
        </div>
        <div class="row g-4">
            <?php while ($event = mysqli_fetch_assoc($featured)): ?>
            <?php $availability = event_availability($event); ?>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= $j = ($j ?? 0) + 1; ?>">
                <div class="card event-card h-100">
                    <div class="position-relative">
<img src="<?= event_banner($event['banner']) ?>" 
                             class="card-img-top" alt="<?= htmlspecialchars($event['title']) ?>" 
                             onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=400&q=80'">
<div class="event-badge"><?= category_icon($event['category_icon']) ?> <?= htmlspecialchars($event['category_name']) ?></div>
                        <span class="badge <?= $availability['class'] ?> event-availability-badge"><i class="fas <?= $availability['icon'] ?> me-1"></i><?= $availability['label'] ?></span>
                        <div class="event-price"><?= CURRENCY_SYMBOL ?><?= number_format((float)$event['discount_price'] > 0 ? $event['discount_price'] : $event['price']) ?></div>
                        <div class="event-overlay">
                            <a href="<?= SITE_URL ?>event.php?slug=<?= $event['slug'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye me-1"></i> View Details
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($event['title']) ?></h5>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge bg-primary-soft"><i class="fas fa-calendar me-1"></i><?= format_date($event['event_date']) ?></span>
                            <span class="badge bg-info-soft"><i class="fas fa-clock me-1"></i><?= date('h:i A', strtotime($event['event_time'])) ?></span>
                            <span class="badge bg-success-soft"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($event['city_name']) ?></span>
                        </div>
                        <p class="card-text"><?= htmlspecialchars(truncate_text($event['short_description'] ?: $event['description'], 100)) ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted"><i class="fas fa-user me-1"></i><?= htmlspecialchars($event['organizer_name']) ?></small>
                            <small class="text-muted"><i class="fas fa-chair me-1"></i><?= $event['available_seats'] ?> seats left</small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- =====================================================
     TRENDING EVENTS SECTION
     ===================================================== -->
<section class="py-5" style="background: var(--gradient-glass);">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div>
                <h2 class="section-title">🔥 Trending Near You</h2>
                <p class="section-subtitle">Most happening events everyone's talking about</p>
            </div>
        </div>
        <div class="row g-4">
            <?php while ($tr = mysqli_fetch_assoc($trending)): ?>
            <?php $availability = event_availability($tr); ?>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="<?= $k = ($k ?? 0) + 1; ?>">
                <div class="card event-card h-100">
                    <div class="position-relative">
                        <img src="<?= event_banner($tr['banner']) ?>" 
                             class="card-img-top" alt="<?= htmlspecialchars($tr['title']) ?>" 
                             onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=400&q=80'"
                             style="height:160px;object-fit:cover;">
                        <div class="event-price"><?= CURRENCY_SYMBOL ?><?= number_format((float)$tr['discount_price'] > 0 ? $tr['discount_price'] : $tr['price']) ?></div>
                        <span class="badge <?= $availability['class'] ?> event-availability-badge"><i class="fas <?= $availability['icon'] ?> me-1"></i><?= $availability['label'] ?></span>
                        <span class="trending-badge"><i class="fas fa-fire"></i></span>
                    </div>
                    <div class="card-body">
                        <h6 class="card-title"><?= htmlspecialchars(truncate_text($tr['title'], 40)) ?></h6>
                        <small class="text-muted d-block"><i class="fas fa-calendar me-1"></i><?= format_date($tr['event_date']) ?></small>
                        <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($tr['city_name']) ?></small>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="text-warning small"><i class="fas fa-star"></i> <?= $tr['rating'] ?: 'New' ?></span>
                            <a href="<?= SITE_URL ?>event.php?slug=<?= $tr['slug'] ?>" class="btn btn-outline btn-sm">Book</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- =====================================================
     UPCOMING EVENTS SECTION
     ===================================================== -->
<section class="py-5">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div>
                <h2 class="section-title">📅 Upcoming Events</h2>
                <p class="section-subtitle">What's happening next in your city</p>
            </div>
            <a href="<?= SITE_URL ?>events.php" class="btn btn-primary d-none d-md-flex">
                View All Events <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
        <div class="row g-4">
            <?php while ($event = mysqli_fetch_assoc($upcoming)): ?>
            <?php $availability = event_availability($event); ?>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="<?= $l = ($l ?? 0) + 1; ?>">
                <div class="card event-card h-100">
                    <div class="position-relative">
<img src="<?= event_banner($event['banner']) ?>" 
                             class="card-img-top" alt="<?= htmlspecialchars($event['title']) ?>" 
                             onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=400&q=80'"
                             style="height:160px;object-fit:cover;">
                        <div class="event-badge"><?= htmlspecialchars($event['category_name']) ?></div>
                        <span class="badge <?= $availability['class'] ?> event-availability-badge"><i class="fas <?= $availability['icon'] ?> me-1"></i><?= $availability['label'] ?></span>
                        <div class="event-price"><?= CURRENCY_SYMBOL ?><?= number_format((float)$event['discount_price'] > 0 ? $event['discount_price'] : $event['price']) ?></div>
                    </div>
                    <div class="card-body">
                        <h6 class="card-title"><?= htmlspecialchars(truncate_text($event['title'], 40)) ?></h6>
                        <small class="text-muted d-block"><i class="fas fa-calendar me-1"></i><?= format_date($event['event_date']) ?></small>
                        <small class="text-muted d-block"><i class="fas fa-clock me-1"></i><?= date('h:i A', strtotime($event['event_time'])) ?></small>
                        <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($event['city_name']) ?></small>
                        <a href="<?= SITE_URL ?>event.php?slug=<?= $event['slug'] ?>" class="btn btn-outline btn-sm w-100 mt-2 <?= $availability['label'] !== 'Available' ? 'disabled' : '' ?>" <?= $availability['label'] !== 'Available' ? 'aria-disabled="true" tabindex="-1"' : '' ?>><?= $availability['label'] === 'Available' ? 'Book Now' : $availability['label'] ?></a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- =====================================================
     CITIES SECTION
     ===================================================== -->
<section class="py-5" style="background: var(--gradient-glass);">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div>
                <h2 class="section-title">📍 Explore Cities</h2>
                <p class="section-subtitle">Find events in your favorite cities</p>
            </div>
        </div>
        <div class="row g-3">
            <?php while ($city = mysqli_fetch_assoc($cities)): ?>
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="<?= $m = ($m ?? 0) + 1; ?>">
                <a href="<?= SITE_URL ?>events.php?city=<?= $city['id'] ?>" class="text-decoration-none">
                    <div class="glass-card text-center p-4">
                        <i class="fas fa-city fa-3x mb-3" style="color: var(--primary);"></i>
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($city['city_name']) ?></h6>
                        <small class="text-muted"><?= $city['event_count'] ?> Events</small>
                    </div>
                </a>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- =====================================================
     HOW IT WORKS SECTION
     ===================================================== -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">How It Works</h2>
            <p class="section-subtitle">Book your next experience in 3 simple steps</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <i class="fas fa-search fa-3x mb-3 text-primary"></i>
                    <h5>Discover Events</h5>
                    <p class="text-muted">Browse through hundreds of events across your city. Use filters to find exactly what you love.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="step-card">
                    <div class="step-number">2</div>
                    <i class="fas fa-ticket-alt fa-3x mb-3 text-success"></i>
                    <h5>Book Instantly</h5>
                    <p class="text-muted">Select your tickets, apply a coupon, and checkout securely. Get instant QR tickets in your inbox.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="step-card">
                    <div class="step-number">3</div>
                    <i class="fas fa-star fa-3x mb-3 text-warning"></i>
                    <h5>Enjoy & Review</h5>
                    <p class="text-muted">Attend the event, scan your QR code, and share your experience with a rating and review.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =====================================================
     TESTIMONIALS SECTION
     ===================================================== -->
<section class="py-5" style="background: var(--gradient-glass);">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">💬 What People Say</h2>
            <p class="section-subtitle">Trusted by thousands of event lovers</p>
        </div>
        <div class="row g-4">
            <?php while ($testimonial = mysqli_fetch_assoc($testimonials)): ?>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= $n = ($n ?? 0) + 1; ?>">
                <div class="testimonial-card h-100">
                    <div class="testimonial-stars">
                        <?php for ($s = 0; $s < 5; $s++): ?>
                            <i class="fas fa-star<?= $s < $testimonial['rating'] ? '' : '-o' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="testimonial-text">"<?= htmlspecialchars($testimonial['content']) ?>"</p>
                    <div class="testimonial-author">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($testimonial['name']) ?>&background=6C3CE1&color=fff" 
                             class="testimonial-avatar" alt="<?= htmlspecialchars($testimonial['name']) ?>">
                        <div>
                            <div class="testimonial-name"><?= htmlspecialchars($testimonial['name']) ?></div>
                            <div class="testimonial-designation"><?= htmlspecialchars($testimonial['designation']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- =====================================================
     SPONSORS SECTION
     ===================================================== -->
<section class="py-4">
    <div class="container">
        <div class="text-center mb-4" data-aos="fade-up">
            <h5 class="text-muted">Trusted & Partnered With</h5>
        </div>
        <div class="row justify-content-center align-items-center g-4" data-aos="fade-up">
            <?php while ($sponsor = mysqli_fetch_assoc($sponsors)): ?>
            <div class="col-4 col-md-2 text-center">
                <div class="glass-card py-3 px-2">
                    <i class="fas fa-building fa-2x" style="color: var(--primary);"></i>
                    <div class="fw-bold mt-2 small"><?= htmlspecialchars($sponsor['sponsor_name']) ?></div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- =====================================================
     NEWSLETTER SECTION
     ===================================================== -->
<section class="py-5">
    <div class="container">
        <div class="newsletter-section" data-aos="zoom-in">
            <div class="position-relative" style="z-index: 1;">
                <h2>📬 Stay Updated</h2>
                <p>Subscribe to get notified about the latest events in your city</p>
                <form class="newsletter-form" method="POST" action="<?= SITE_URL ?>api/newsletter.php">
                    <div class="input-group">
                        <input type="email" name="email" class="form-control" placeholder="Enter your email address" required>
                        <button class="btn" type="submit">
                            <i class="fas fa-paper-plane me-2"></i>Subscribe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- =====================================================
     FAQ SECTION
     ===================================================== -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">❓ Frequently Asked Questions</h2>
            <p class="section-subtitle">Everything you need to know about Evento</p>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="faq-accordion accordion" id="faqAccordion">
                    <?php $f = 0; while ($faq = mysqli_fetch_assoc($faqs)): $f++; ?>
                    <div class="accordion-item" data-aos="fade-up">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $f > 1 ? 'collapsed' : '' ?>" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#faq<?= $f ?>">
                                <?= htmlspecialchars($faq['question']) ?>
                            </button>
                        </h2>
                        <div id="faq<?= $f ?>" class="accordion-collapse collapse <?= $f == 1 ? 'show' : '' ?>" 
                             data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <?= htmlspecialchars($faq['answer']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
</content>
