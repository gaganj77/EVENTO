<?php
/**
 * EVENTO - Create Event (Organizer)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_organizer();

$page_title = 'Create Event';
$error = '';
$success = '';

$categories = mysqli_query($conn, "SELECT * FROM categories WHERE status = 1");
$cities = mysqli_query($conn, "SELECT * FROM cities WHERE status = 1");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $short_description = sanitize_input($_POST['short_description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $subcategory_id = (int)($_POST['subcategory_id'] ?? 0);
    $city_id = (int)($_POST['city_id'] ?? 0);
    $venue = sanitize_input($_POST['venue'] ?? '');
    $venue_address = sanitize_input($_POST['venue_address'] ?? '');
    $event_date = sanitize_input($_POST['event_date'] ?? '');
    $event_time = sanitize_input($_POST['event_time'] ?? '');
    $end_time = sanitize_input($_POST['end_time'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $discount_price = (float)($_POST['discount_price'] ?? 0);
    $total_seats = (int)($_POST['total_seats'] ?? 0);
    $age_restriction = sanitize_input($_POST['age_restriction'] ?? 'All Ages');
    $event_rules = sanitize_input($_POST['event_rules'] ?? '');
    $tags = sanitize_input($_POST['tags'] ?? '');
    
    if (empty($title) || empty($description) || $category_id <= 0 || $city_id <= 0 || empty($venue) || empty($event_date) || empty($event_time) || $total_seats <= 0) {
        $error = 'Please fill in all required fields';
    } elseif ($event_date < date('Y-m-d')) {
        $error = 'Event date cannot be in the past';
    } elseif ($discount_price > 0 && $discount_price >= $price) {
        $error = 'Discount price must be less than regular price';
    } else {
        $slug = generate_slug($title) . '-' . uniqid();
        $banner = '';
        
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $upload = upload_file($_FILES['banner'], UPLOAD_PATH . 'events/');
            if ($upload['success']) {
                $banner = $upload['filename'];
            } else {
                $error = $upload['error'];
            }
        }
        
        if (empty($error)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO events (user_id, title, slug, description, short_description, category_id, subcategory_id, city_id, venue, venue_address, event_date, event_time, end_time, price, discount_price, total_seats, banner, age_restriction, event_rules, tags, status) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $user_id = (int)$_SESSION['user_id'];
            mysqli_stmt_bind_param($stmt, "issssiiisssssddissss", $user_id, $title, $slug, $description, $short_description, $category_id, $subcategory_id, $city_id, $venue, $venue_address, $event_date, $event_time, $end_time, $price, $discount_price, $total_seats, $banner, $age_restriction, $event_rules, $tags);
            
            if (mysqli_stmt_execute($stmt)) {
                $event_id = mysqli_insert_id($conn);
                
                if (isset($_FILES['gallery']) && is_array($_FILES['gallery']['name'])) {
                    $gallery_files = $_FILES['gallery'];
                    for ($i = 0; $i < count($gallery_files['name']); $i++) {
                        if ($gallery_files['error'][$i] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $gallery_files['name'][$i],
                                'type' => $gallery_files['type'][$i],
                                'tmp_name' => $gallery_files['tmp_name'][$i],
                                'error' => $gallery_files['error'][$i],
                                'size' => $gallery_files['size'][$i]
                            ];
                            $upload = upload_file($file, UPLOAD_PATH . 'gallery/');
                            if ($upload['success']) {
                                mysqli_query($conn, "INSERT INTO event_gallery (event_id, image) VALUES ($event_id, '{$upload['filename']}')");
                            }
                        }
                    }
                }
                
                log_activity($_SESSION['user_id'], 'create_event', 'Created event: ' . $title);
                $_SESSION['success'] = 'Event created! Awaiting admin approval.';
                header('Location: ' . SITE_URL . 'organizer/my-events.php');
                exit();
            } else {
                $error = 'Failed to create event. Please try again.';
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="padding-top: 100px !important;">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex align-items-center gap-2 mb-4">
                <a href="<?= SITE_URL ?>organizer/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i></a>
                <h2 class="fw-bold mb-0"><i class="fas fa-plus-circle me-2 text-primary"></i>Create New Event</h2>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="glass-card">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Event Title *</label>
                            <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?= $cat['id'] ?>"><?= $cat['category_icon'] ?> <?= $cat['category_name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City *</label>
                            <select name="city_id" class="form-select" required>
                                <option value="">Select City</option>
                                <?php while ($ct = mysqli_fetch_assoc($cities)): ?>
                                <option value="<?= $ct['id'] ?>"><?= $ct['city_name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Venue *</label>
                            <input type="text" name="venue" class="form-control" required value="<?= htmlspecialchars($_POST['venue'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Venue Address</label>
                            <input type="text" name="venue_address" class="form-control" value="<?= htmlspecialchars($_POST['venue_address'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Event Date *</label>
                            <input type="date" name="event_date" class="form-control" required value="<?= $_POST['event_date'] ?? '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time *</label>
                            <input type="time" name="event_time" class="form-control" required value="<?= $_POST['event_time'] ?? '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-control" value="<?= $_POST['end_time'] ?? '' ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Short Description</label>
                            <input type="text" name="short_description" class="form-control" maxlength="500" value="<?= htmlspecialchars($_POST['short_description'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Full Description *</label>
                            <textarea name="description" class="form-control" rows="6" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Price (<?= CURRENCY_SYMBOL ?>) *</label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0" required value="<?= $_POST['price'] ?? '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Discount Price</label>
                            <input type="number" name="discount_price" class="form-control" step="0.01" min="0" value="<?= $_POST['discount_price'] ?? '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Seats *</label>
                            <input type="number" name="total_seats" class="form-control" min="1" required value="<?= $_POST['total_seats'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Age Restriction</label>
                            <select name="age_restriction" class="form-select">
                                <option value="All Ages">All Ages</option>
                                <option value="18+">18+</option>
                                <option value="21+">21+</option>
                                <option value="Family Friendly">Family Friendly</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tags (comma separated)</label>
                            <input type="text" name="tags" class="form-control" value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Event Rules</label>
                            <textarea name="event_rules" class="form-control" rows="3"><?= htmlspecialchars($_POST['event_rules'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Banner Image</label>
                            <input type="file" name="banner" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gallery Images (multiple)</label>
                            <input type="file" name="gallery[]" class="form-control" accept="image/*" multiple>
                        </div>
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Create Event</button>
                        <a href="<?= SITE_URL ?>organizer/dashboard.php" class="btn btn-ghost btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
    </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
