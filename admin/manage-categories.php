<?php
/**
 * EVENTO - Admin Manage Categories
 */
require_once __DIR__ . '/admin_auth.php';
$page_title = 'Manage Categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat_name = mysqli_real_escape_string($conn, $_POST['category_name'] ?? '');
    $cat_icon = mysqli_real_escape_string($conn, $_POST['category_icon'] ?? '🎯');
    
    if (!empty($cat_name)) {
        $slug = generate_slug($cat_name);
        mysqli_query($conn, "INSERT INTO categories (category_name, category_slug, category_icon) VALUES ('$cat_name', '$slug', '$cat_icon')");
        $_SESSION['success'] = 'Category added!';
        header('Location: manage-categories.php');
        exit();
    }
}

$delete_id = (int)($_GET['delete'] ?? 0);
if ($delete_id > 0) {
    mysqli_query($conn, "DELETE FROM categories WHERE id = $delete_id");
    header('Location: manage-categories.php');
    exit();
}

$categories = mysqli_query($conn, "SELECT c.*, (SELECT COUNT(*) FROM events WHERE category_id = c.id) as event_count FROM categories c ORDER BY c.category_name");
?>
<div class="container-fluid py-4" style="padding-top: 90px !important;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-tags me-2 text-primary"></i>Manage Categories</h2>
        <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
    
    <div class="row g-4">
        <div class="col-md-4">
            <div class="glass-card">
                <h5 class="fw-bold mb-3">Add Category</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="category_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icon (Emoji)</label>
                        <input type="text" name="category_icon" class="form-control" value="🎯">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add Category</button>
                </form>
            </div>
        <div class="col-md-8">
            <div class="glass-card">
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Icon</th><th>Name</th><th>Slug</th><th>Events</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                            <tr>
                                <td style="font-size:1.5rem;"><?= $cat['category_icon'] ?></td>
                                <td><strong><?= htmlspecialchars($cat['category_name']) ?></strong></td>
                                <td><small><?= $cat['category_slug'] ?></small></td>
                                <td><span class="badge bg-primary-soft"><?= $cat['event_count'] ?></span></td>
                                <td>
                                    <a href="?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete category?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
        </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
