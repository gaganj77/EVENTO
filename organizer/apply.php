<?php
/**
 * EVENTO - Organizer Verification Application
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_auth();

if (($_SESSION['user_role'] ?? '') !== 'organizer') {
    $_SESSION['error'] = 'Only organizer accounts can submit verification.';
    header('Location: ' . SITE_URL . 'user/dashboard.php');
    exit();
}

$page_title = 'Organizer Verification';
$user_id = (int)$_SESSION['user_id'];
$error = '';
$request = fetch_single("SELECT * FROM organizer_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1", "i", [$user_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $organization_name = sanitize_input($_POST['organization_name'] ?? '');
    $organization_type = sanitize_input($_POST['organization_type'] ?? '');
    $website = sanitize_input($_POST['website'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $document_path = $request['document_path'] ?? null;

    if ($organization_name === '' || $organization_type === '' || $phone === '' || $address === '') {
        $error = 'Organization name, type, phone, and address are required.';
    } elseif ($website !== '' && !validate_url($website)) {
        $error = 'Please enter a valid website URL.';
    } else {
        if (!empty($_FILES['document']['name']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $extension = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true) || $_FILES['document']['size'] > 5 * 1024 * 1024) {
                $error = 'Verification document must be a PDF, JPG, or PNG up to 5MB.';
            } else {
                $filename = 'organizer-' . $user_id . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
                $target = SITE_PATH . 'uploads/users/' . $filename;
                if (move_uploaded_file($_FILES['document']['tmp_name'], $target)) {
                    $document_path = $filename;
                } else {
                    $error = 'Could not save the verification document.';
                }
            }
        }

        if ($error === '') {
            $stmt = mysqli_prepare($conn, "INSERT INTO organizer_requests (user_id, organization_name, organization_type, website, phone, address, document_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, "issssss", $user_id, $organization_name, $organization_type, $website, $phone, $address, $document_path);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['success'] = 'Verification application submitted. Admin will review your details.';
            header('Location: ' . SITE_URL . 'user/dashboard.php');
            exit();
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5" style="padding-top: 100px !important;">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="glass-card">
                <h2 class="fw-bold mb-2"><i class="fas fa-user-check me-2 text-primary"></i>Organizer Verification</h2>
                <p class="text-muted">Submit your organization details so the admin can verify you before event publishing.</p>
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($request && $request['status'] === 'pending'): ?><div class="alert alert-info">Your application is under admin review.</div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Organization / Brand Name *</label><input name="organization_name" class="form-control" required value="<?= htmlspecialchars($request['organization_name'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Organization Type *</label><input name="organization_type" class="form-control" placeholder="Event company, college, venue..." required value="<?= htmlspecialchars($request['organization_type'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Website</label><input type="url" name="website" class="form-control" placeholder="https://example.com" value="<?= htmlspecialchars($request['website'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Contact Phone *</label><input name="phone" class="form-control" required value="<?= htmlspecialchars($request['phone'] ?? '') ?>"></div>
                        <div class="col-12"><label class="form-label">Business / Organization Address *</label><textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($request['address'] ?? '') ?></textarea></div>
                        <div class="col-12"><label class="form-label">Verification Document</label><input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png"><small class="text-muted">Upload registration proof, ID, or authorization letter. Max 5MB.</small></div>
                    </div>
                    <button class="btn btn-primary mt-4"><i class="fas fa-paper-plane me-2"></i>Submit for Admin Review</button>
                    <a href="<?= SITE_URL ?>user/dashboard.php" class="btn btn-ghost mt-4 ms-2">Back</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
