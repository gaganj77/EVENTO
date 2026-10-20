<?php
/**
 * EVENTO - Admin: Add Event
 * Creates an event on behalf of an organizer using the real events schema.
 * (secured: requires admin session)
 */
require_once __DIR__ . '/admin_auth.php';
$page_title = 'Add Event';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title          = sanitize_input($_POST['title'] ?? '');
    $description    = sanitize_input($_POST['description'] ?? '');
    $category_id    = (int)($_POST['category_id'] ?? 0);
    $city_id        = (int)($_POST['city_id'] ?? 0);
    $organizer_id   = (int)($_POST['user_id'] ?? 0);
    $venue          = sanitize_input($_POST['venue'] ?? '');
    $event_date     = sanitize_input($_POST['event_date'] ?? '');
    $event_time     = sanitize_input($_POST['event_time'] ?? '');
    $price          = (float)($_POST['price'] ?? 0);
    $discount_price = (float)($_POST['discount_price'] ?? 0);
    $total_seats    = (int)($_POST['total_seats'] ?? 0);
    $status         = ($_POST['status'] ?? 'approved') === 'pending' ? 'pending' : 'approved';

    if ($title === '' || $description === '' || $category_id <= 0 || $city_id <= 0 || $organizer_id <= 0
        || $venue === '' || $event_date === '' || $event_time === '' || $total_seats <= 0) {
        $error = 'Please fill in all required fields.';
    } elseif ($discount_price > 0 && $discount_price >= $price) {
        $error = 'Discount price must be less than the regular price.';
    } else {
        $slug = generate_slug($title) . '-' . uniqid();
        $banner = '';
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = upload_file($_FILES['image'], UPLOAD_PATH . 'events/');
            if ($upload['success']) { $banner = $upload['filename']; } else { $error = $upload['error']; }
        }
        if ($error === '') {
            $new_id = insert_data(
                "INSERT INTO events (user_id, title, slug, description, category_id, city_id, venue,
                                     event_date, event_time, price, discount_price, total_seats, banner, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                "isssiiissddiis",
                [$organizer_id, $title, $slug, $description, $category_id, $city_id, $venue,
                 $event_date, $event_time, $price, $discount_price, $total_seats, $banner, $status]
            );
            if ($new_id) {
                log_audit($admin_id, 'add_event', 'events', (int)$new_id, null, ['title' => $title, 'organizer_id' => $organizer_id]);
                create_notification($organizer_id, 'event', 'Event Added By Admin',
                    'Admin added the event "' . $title . '" to your account.', SITE_URL . 'organizer/my-events.php');
                $_SESSION['success'] = 'Event "' . $title . '" added successfully (ID #' . $new_id . ').';
                header('Location: ' . SITE_URL . 'admin/manage-events.php');
                exit();
            }
            $error = 'Could not save the event. Please try again.';
        }
    }
}

$organizers = fetch_all("SELECT id, full_name, email FROM users WHERE role = 'organizer' ORDER BY full_name");
$categories = fetch_all("SELECT id, category_name FROM categories WHERE status = 1 ORDER BY category_name");
$cities     = fetch_all("SELECT id, city_name FROM cities ORDER BY city_name");

include __DIR__ . '/../includes/header.php';
?>
<!-- MARKUP-A -->


if(isset($_POST['submit']))
{
    $title=$_POST['title'];
    $category=$_POST['category'];
    $description=$_POST['description'];

    $city=$_POST['city'];
    $venue=$_POST['venue'];

    $date=$_POST['date'];
    $event_time=$_POST['event_time'];

    $price=$_POST['price'];
    $seats=$_POST['seats'];

    $image = $_FILES['image']['name'];
    $tmp_name = $_FILES['image']['tmp_name'];

    move_uploaded_file(
    $tmp_name,
    "../images/".$image
    );

    // INSERT QUERY

    mysqli_query(
    $conn,

    "INSERT INTO events
(
    title,
    description,
    city_id,
    venue,
    event_date,
    event_time,
    price,
    seats,
    images,
    category
)
    VALUES
    (
     '$title',
    '$description',
    '$city',
    '$venue',
    '$date',
    '$event_time',
    '$price',
    '$seats',
    '$image',
    '$category'
    )"
    );

    echo "Event Added";
}

?>

<form method="POST" enctype="multipart/form-data">

Title
<br>
<input type="text" name="title" required>

<br><br>

Category
<br>

<select name="category">
    <option value="Workshop">Workshop</option>
    <option value="Meetup">Meetup</option>
    <option value="Music">Music</option>
    <option value="Comedy">Comedy</option>
    <option value="Sports">Sports</option>
    <option value="Art">Art</option>
</select>

<div class="col-md-4">
                    <label class="form-label">Event Date *</label>
                    <input type="date" name="event_date" class="form-control" required value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Start Time *</label>
                    <input type="time" name="event_time" class="form-control" required value="<?= htmlspecialchars($_POST['event_time'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Price (<?= CURRENCY_SYMBOL ?>) *</label>
                    <input type="number" name="price" class="form-control" step="0.01" min="0" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Discount Price</label>
                    <input type="number" name="discount_price" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($_POST['discount_price'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Total Seats *</label>
                    <input type="number" name="total_seats" class="form-control" min="1" required value="<?= htmlspecialchars($_POST['total_seats'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Banner Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="col-12">
                    <label class="form-label">Full Description *</label>
                    <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Save Event</button>
                <a href="<?= SITE_URL ?>admin/manage-events.php" class="btn btn-ghost btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>