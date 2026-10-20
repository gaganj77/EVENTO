<?php
/**
 * EVENTO - Admin Manage Cities
 */
require_once __DIR__ . '/admin_auth.php';
$page_title = 'Manage Cities';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $city_name = mysqli_real_escape_string($conn, $_POST['city_name'] ?? '');
    $state_id = (int)($_POST['state_id'] ?? 0);
    
    if (!empty($city_name) && $state_id > 0) {
        mysqli_query($conn, "INSERT INTO cities (city_name, state_id) VALUES ('$city_name', $state_id)");
        header('Location: manage-cities.php');
        exit();
    }
}

$delete_id = (int)($_GET['delete'] ?? 0);
if ($delete_id > 0) { mysqli_query($conn, "DELETE FROM cities WHERE id = $delete_id"); header('Location: manage-cities.php'); exit(); }

$states = mysqli_query($conn, "SELECT * FROM states WHERE status = 1");
$cities = mysqli_query($conn, "SELECT ci.*, s.state_name FROM cities ci JOIN states s ON ci.state_id = s.id ORDER BY ci.city_name");
?>
<div class="container-fluid py-4" style="padding-top: 90px !important;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-city me-2 text-primary"></i>Manage Cities</h2>
        <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="glass-card">
                <h5 class="fw-bold mb-3">Add City</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">City Name</label>
                        <input type="text" name="city_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">State</label>
                        <select name="state_id" class="form-select" required>
                            <option value="">Select State</option>
                            <?php while ($st = mysqli_fetch_assoc($states)): ?>
                            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['state_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add City</button>
                </form>
            </div>
        <div class="col-md-8">
            <div class="glass-card">
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>City</th><th>State</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php while ($city = mysqli_fetch_assoc($cities)): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($city['city_name']) ?></strong></td>
                                <td><?= htmlspecialchars($city['state_name']) ?></td>
                                <td><a href="?delete=<?= $city['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
        </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
