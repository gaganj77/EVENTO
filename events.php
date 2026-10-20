<?php
/**
 * EVENTO - Events Listing Page
 * With search, filters, sorting, and pagination
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Browse Events';

// Filters
$search = sanitize_input($_GET['search'] ?? '');
$category = sanitize_input($_GET['category'] ?? '');
$city = sanitize_input($_GET['city'] ?? '');
$organizer = sanitize_input($_GET['organizer'] ?? '');
$date_from = sanitize_input($_GET['date_from'] ?? '');
$date_to = sanitize_input($_GET['date_to'] ?? '');
$price_min = (float)($_GET['price_min'] ?? 0);
$price_max = (float)($_GET['price_max'] ?? 0);
$sort = sanitize_input($_GET['sort'] ?? 'date_asc');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = PER_PAGE;
$offset = ($page - 1) * $limit;

$where = "WHERE e.status = 'approved'";
$params = [];
$types = '';

if (!empty($search)) {
    $where .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.tags LIKE ? OR c.category_name LIKE ? OR ci.city_name LIKE ? OR u.full_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssssss';
}

if (!empty($category)) {
    $where .= " AND c.category_slug = ?";
    $params[] = $category;
    $types .= 's';
}

// Support both numeric city ID and city name from autocomplete
if (!empty($city)) {
    if (is_numeric($city)) {
        $where .= " AND e.city_id = ?";
        $params[] = (int)$city;
        $types .= 'i';
    } else {
        $where .= " AND ci.city_name LIKE ?";
        $params[] = "%$city%";
        $types .= 's';
    }
}

// Search by organizer name or ID
if (!empty($organizer)) {
    $where .= " AND (u.full_name LIKE ? OR CONCAT('ORG', u.id) LIKE ? OR u.id = ?)";
    $org_term = "%$organizer%";
    $params[] = $org_term;
    $params[] = $org_term;
    $params[] = (int)$organizer;
    $types .= 'ssi';
}

if (!empty($date_from)) {
    $where .= " AND e.event_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where .= " AND e.event_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if ($price_min > 0) {
    $where .= " AND (e.discount_price > 0 AND e.discount_price >= ? OR e.discount_price = 0 AND e.price >= ?)";
    $params[] = $price_min;
    $params[] = $price_min;
    $types .= 'dd';
}

if ($price_max > 0) {
    $where .= " AND (e.discount_price > 0 AND e.discount_price <= ? OR e.discount_price = 0 AND e.price <= ?)";
    $params[] = $price_max;
    $params[] = $price_max;
    $types .= 'dd';
}

$order = 'ORDER BY e.event_date ASC';
switch ($sort) {
    case 'date_desc': $order = 'ORDER BY e.event_date DESC'; break;
    case 'price_asc': $order = 'ORDER BY COALESCE(e.discount_price, e.price) ASC'; break;
    case 'price_desc': $order = 'ORDER BY COALESCE(e.discount_price, e.price) DESC'; break;
    case 'popular': $order = 'ORDER BY e.booked_seats DESC'; break;
    case 'rating': $order = 'ORDER BY e.rating DESC'; break;
    case 'newest': $order = 'ORDER BY e.created_at DESC'; break;
}

$base_sql = "FROM events e 
    JOIN categories c ON e.category_id = c.id 
    JOIN cities ci ON e.city_id = ci.id 
    JOIN users u ON e.user_id = u.id 
    $where";

// Get total count
$count_sql = "SELECT COUNT(*) as total $base_sql";
$count_result = prepared_query($count_sql, $types, $params);
$total_records = 0;
if ($count_result) {
    $row = mysqli_fetch_assoc($count_result);
    $total_records = $row['total'];
}

// Get events
$sql = "SELECT e.*, c.category_name, c.category_icon, c.category_slug, ci.city_name, u.full_name as organizer_name $base_sql $order LIMIT $limit OFFSET $offset";
$events = prepared_query($sql, $types, $params);

// Get categories for filter
$categories = mysqli_query($conn, "SELECT * FROM categories WHERE status = 1 ORDER BY category_name");
$cities = mysqli_query($conn, "SELECT * FROM cities WHERE status = 1 ORDER BY city_name");

include __DIR__ . '/includes/header.php';
?>

<section class="hero-section" style="padding-top: 100px; padding-bottom: 40px;">
    <div class="container">
        <h1 class="hero-title text-center" data-aos="fade-up">
            Browse <span class="gradient-text">Events</span>
        </h1>
        <p class="hero-description text-center mx-auto" data-aos="fade-up">Discover and book amazing events happening near you</p>
        
        <!-- Filter Form -->
        <form method="GET" class="search-bar mb-4" data-aos="fade-up">
            <div class="row g-2">
<div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control border-0" placeholder="Search events, organizers..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="position-relative">
                        <input type="text" name="city" id="cityAutocomplete" class="form-control" placeholder="City (type to search)" value="<?= htmlspecialchars($city) ?>" autocomplete="off">
                        <div class="position-absolute w-100 d-none shadow" id="citySuggestions" style="z-index:1050; background:var(--card-bg); border-radius:8px; max-height:250px; overflow-y:auto;"></div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select name="category" class="form-select border-0">
                        <option value="">All Categories</option>
                        <?php
                        mysqli_data_seek($categories, 0);
                        while ($cat = mysqli_fetch_assoc($categories)):
                        ?>
                        <option value="<?= $cat['category_slug'] ?>" <?= $category == $cat['category_slug'] ? 'selected' : '' ?>><?= $cat['category_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="sort" class="form-select border-0">
                        <option value="date_asc" <?= $sort == 'date_asc' ? 'selected' : '' ?>>Date: Earliest</option>
                        <option value="date_desc" <?= $sort == 'date_desc' ? 'selected' : '' ?>>Date: Latest</option>
                        <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="popular" <?= $sort == 'popular' ? 'selected' : '' ?>>Most Popular</option>
                        <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest First</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Filter</button>
                </div>
            </div>
        </form>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <?php if ($total_records > 0): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <p class="mb-0 text-muted">Showing <?= ($offset + 1) ?>-<?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> events</p>
        </div>
        <div class="row g-4">
            <?php while ($event = mysqli_fetch_assoc($events)): ?>
            <?php $availability = event_availability($event); ?>
            <div class="col-md-6 col-lg-4 col-xl-3" data-aos="fade-up">
                <div class="card event-card h-100">
                    <div class="position-relative">
<img src="<?= event_banner($event['banner']) ?>" 
                             class="card-img-top" alt="<?= htmlspecialchars($event['title']) ?>"
                             onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=400&q=80'"
                             style="height: 180px; object-fit: cover;">
<div class="event-badge"><?= htmlspecialchars($event['category_name']) ?></div>
                        <span class="badge <?= $availability['class'] ?> event-availability-badge"><i class="fas <?= $availability['icon'] ?> me-1"></i><?= $availability['label'] ?></span>
                        <div class="event-price"><?= CURRENCY_SYMBOL ?><?= number_format((float)$event['discount_price'] > 0 ? $event['discount_price'] : $event['price']) ?></div>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?= htmlspecialchars($event['title']) ?></h5>
                        <div class="mb-2">
                            <small class="text-muted d-block"><i class="fas fa-calendar me-1"></i><?= format_date($event['event_date']) ?></small>
                            <small class="text-muted d-block"><i class="fas fa-clock me-1"></i><?= date('h:i A', strtotime($event['event_time'])) ?></small>
                            <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($event['venue']) ?>, <?= htmlspecialchars($event['city_name']) ?></small>
                        </div>
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <span class="badge <?= $availability['class'] ?>"><i class="fas fa-chair me-1"></i><?= $event['available_seats'] ?> seats</span>
                            <a href="<?= SITE_URL ?>event.php?slug=<?= $event['slug'] ?>" class="btn btn-primary btn-sm <?= $availability['label'] !== 'Available' ? 'disabled' : '' ?>" <?= $availability['label'] !== 'Available' ? 'aria-disabled="true" tabindex="-1"' : '' ?>><?= $availability['label'] === 'Available' ? 'Book Now' : $availability['label'] ?></a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Pagination -->
        <?php
        $url = SITE_URL . 'events.php?' . http_build_query(array_filter(['search' => $search, 'category' => $category, 'city' => $city, 'sort' => $sort]));
        echo get_pagination($total_records, $limit, $page, $url);
        ?>
        
        <?php else: ?>
        <div class="text-center py-5" data-aos="fade-up">
            <i class="fas fa-calendar-times fa-4x mb-3" style="color: var(--text-muted);"></i>
            <h4>No events found</h4>
            <p class="text-muted">Try adjusting your search or filters</p>
            <a href="<?= SITE_URL ?>events.php" class="btn btn-primary"><i class="fas fa-redo me-2"></i>Clear Filters</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
// City AI Autocomplete
const cityInput = document.getElementById('cityAutocomplete');
const citySuggestions = document.getElementById('citySuggestions');

if (cityInput) {
    cityInput.addEventListener('input', function() {
        const query = this.value.trim();
        if (query.length < 1) {
            citySuggestions.classList.add('d-none');
            return;
        }
        fetch('<?= SITE_URL ?>api/search.php?action=cities&q=' + encodeURIComponent(query))
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success' && data.cities.length > 0) {
                    citySuggestions.innerHTML = data.cities.map(c => 
                        `<div class="city-suggestion px-3 py-2" data-city="${c.city_name}" style="cursor:pointer;">
                            <i class="fas fa-city me-2 text-primary"></i>${c.city_name}
                            <small class="text-muted ms-2">${c.events} events</small>
                        </div>`
                    ).join('');
                    citySuggestions.classList.remove('d-none');
                } else {
                    citySuggestions.classList.add('d-none');
                }
            });
    });
    
    citySuggestions.addEventListener('click', function(e) {
        const item = e.target.closest('.city-suggestion');
        if (item) {
            cityInput.value = item.dataset.city;
            citySuggestions.classList.add('d-none');
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#cityAutocomplete') && !e.target.closest('#citySuggestions')) {
            citySuggestions.classList.add('d-none');
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
