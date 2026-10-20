<?php
/**
 * EVENTO - User Profile Management
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_auth();

$page_title = 'My Profile';
$user = get_logged_in_user();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        
        if (empty($full_name)) {
            $error = 'Name is required';
        } elseif (!empty($phone) && !validate_phone($phone)) {
            $error = 'Invalid phone number';
        } else {
            // Handle photo upload
            $photo = $user['profile_photo'];
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $upload = upload_file($_FILES['profile_photo'], UPLOAD_PATH . 'users/');
                if ($upload['success']) {
                    if ($user['profile_photo'] != 'default-user.png') {
                        delete_file(UPLOAD_PATH . 'users/' . $user['profile_photo']);
                    }
                    $photo = $upload['filename'];
                } else {
                    $error = $upload['error'];
                }
            }
            
            if (empty($error)) {
                $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, phone = ?, profile_photo = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "sssi", $full_name, $phone, $photo, $user['id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $success = 'Profile updated successfully';
                $user = get_logged_in_user();
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (empty($current) || empty($new) || empty($confirm)) {
            $error = 'Please fill in all password fields';
        } elseif (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match';
        } elseif (strlen($new) < 8) {
            $error = 'Password must be at least 8 characters';
        } else {
            $hashed = password_hash($new, PASSWORD_BCRYPT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $hashed, $user['id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $success = 'Password changed successfully';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="padding-top: 100px !important;">
    <div class="row g-4">

        <!-- ============ LEFT: PROFILE CARD ============ -->
        <div class="col-lg-4">
            <div class="glass-card p-4 text-center">
                <img src="<?= SITE_URL ?>uploads/users/<?= htmlspecialchars(empty($user['profile_photo']) || $user['profile_photo'] === 'default-user.png' ? 'default-user.svg' : $user['profile_photo']) ?>"
                     class="rounded-circle mb-3" width="150" height="150" style="object-fit:cover;border:4px solid var(--primary);"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['full_name']) ?>&background=6C3CE1&color=fff&size=150'">
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['full_name']) ?></h4>
                <p class="text-muted mb-2"><?= htmlspecialchars($user['email']) ?></p>
                <span class="badge bg-primary-soft"><?= ucfirst($user['role']) ?></span>
            </div>
        </div>

        <!-- ============ RIGHT: FORMS ============ -->
        <div class="col-lg-8">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="glass-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4 gap-2">
                    <h5 class="fw-bold mb-0"><i class="fas fa-user-edit me-2 text-primary"></i>Edit Profile</h5>
                    <a href="<?= SITE_URL ?>user/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="glass-card p-4">
                <h5 class="fw-bold mb-4"><i class="fas fa-lock me-2 text-warning"></i>Change Password</h5>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Current Password</label>
                            <div class="password-input-group">
                                <input type="password" name="current_password" class="form-control" required>
                                <button type="button" class="password-toggle" tabindex="-1"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">New Password</label>
                            <div class="password-input-group">
                                <input type="password" name="new_password" class="form-control" required>
                                <button type="button" class="password-toggle" tabindex="-1"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Confirm Password</label>
                            <div class="password-input-group">
                                <input type="password" name="confirm_password" class="form-control" required>
                                <button type="button" class="password-toggle" tabindex="-1"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="change_password" class="btn btn-warning"><i class="fas fa-key me-2"></i>Change Password</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
