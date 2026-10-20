<?php
/**
 * EVENTO - Admin: Add City
 * (secured: requires admin session, keeps the JSON-free simple UI)
 */
require_once __DIR__ . '/admin_auth.php';
$page_title = 'Add City';

$message = '';
$message_type = 'info';

if (isset($_POST['submit'])) {
    $city = sanitize_input($_POST['city'] ?? '');
    $state_id = (int)($_POST['state_id'] ?? 1);
    $status = isset($_POST['status']) ? 1 : 0;

    if ($city === '') {
        $message = 'Please enter a city name.';
        $message_type = 'danger';
    } else {
        $exists = fetch_single("SELECT id FROM cities WHERE city_name = ?", "s", [$city]);
        if ($exists) {
            $message = 'City already exists.';
            $message_type = 'warning';
        } else {
            $new_id = insert_data("INSERT INTO cities (city_name, state_id, status) VALUES (?, ?, ?)", "sii", [$city, $state_id, $status]);
            if ($new_id) {
                log_audit($admin_id, 'add_city', 'cities', (int)$new_id);
                $message = 'City "' . htmlspecialchars($city) . '" added successfully.';
                $message_type = 'success';
            } else {
                $message = 'Could not add the city.';
                $message_type = 'danger';
            }
        }
    }
}

$states = fetch_all("SELECT id, state_name FROM states ORDER BY state_name");
$cities = fetch_all("SELECT c.*, s.state_name, (SELECT COUNT(*) FROM events e WHERE e.city_id = c.id) AS event_count
                     FROM cities c LEFT JOIN states s ON s.id = c.state_id ORDER BY s.state_name, c.city_name");

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5" style="padding-top: 100px !important;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0"><i class="fas fa-city me-2 text-primary"></i>Add City</h2>
        <a href="<?= SITE_URL ?>admin/manage-cities.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Manage Cities</a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="glass-card mb-4">
        <form method="POST" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">City Name *</label>
                <input type="text" name="city" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">State</label>
                <select name="state_id" class="form-select">
                    <?php foreach ($states as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['state_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="status" id="status" value="1">
                    <label class="form-check-label" for="status">Active</label>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" name="submit" value="1" class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>Add City</button>
            </div>
        </form>
        <small class="text-muted d-block mt-2">Tip: keep only the cities you actually operate in set to <strong>Active</strong>.</small>
    </div>

    <div class="glass-card">
        <h5 class="fw-bold mb-3">Available Cities (<?= count($cities) ?>)</h5>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>#</th><th>City</th><th>State</th><th>Events</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($cities as $c): ?>
                    <tr>
                        <td><?= (int)$c['id'] ?></td>
                        <td><strong><?= htmlspecialchars($c['city_name']) ?></strong></td>
                        <td><small><?= htmlspecialchars($c['state_name'] ?? '-') ?></small></td>
                        <td><?= (int)$c['event_count'] ?></td>
                        <td><span class="badge <?= $c['status'] ? 'bg-success-soft' : 'bg-danger-soft' ?>"><?= $c['status'] ? 'Active' : 'Inactive' ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>