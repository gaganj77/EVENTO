<?php
/**
 * EVENTO - Contact Page
 * Professional contact page with info cards, form, and map
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Contact Us';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $subject = sanitize_input($_POST['subject'] ?? '');
    $message = sanitize_input($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Please fill in all required fields';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email format';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $subject, $message);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $success = 'Thank you! Your message has been sent. We will get back to you soon.';
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<section class="hero-section" style="padding-top: 110px; padding-bottom: 50px;">
    <div class="container text-center">
        <h1 class="hero-title" data-aos="fade-up">Get In <span class="gradient-text">Touch</span></h1>
        <p class="hero-description mx-auto" style="max-width:600px;" data-aos="fade-up" data-aos-delay="100">
            Have a question, suggestion, or want to partner with us? We'd love to hear from you!
        </p>
    </div>
</section>

<!-- Contact Info Cards -->
<section class="pb-4">
    <div class="container">
        <div class="row g-4 justify-content-center">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                <div class="glass-card text-center p-4 h-100">
                    <div class="stat-icon bg-primary-soft mx-auto" style="width:60px;height:60px;font-size:22px;">
                        <i class="fas fa-map-marker-alt text-primary"></i>
                    </div>
<h5 class="fw-bold mt-3 mb-1">Our Address</h5>
                    <p class="text-muted mb-0">Parul University, Waghodia, Vadodara, Gujarat</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card text-center p-4 h-100">
                    <div class="stat-icon bg-success-soft mx-auto" style="width:60px;height:60px;font-size:22px;">
                        <i class="fas fa-envelope text-success"></i>
                    </div>
                    <h5 class="fw-bold mt-3 mb-1">Email Us</h5>
                    <p class="text-muted mb-0"><?= CONTACT_EMAIL ?></p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card text-center p-4 h-100">
                    <div class="stat-icon bg-info-soft mx-auto" style="width:60px;height:60px;font-size:22px;">
                        <i class="fas fa-phone text-info"></i>
                    </div>
                    <h5 class="fw-bold mt-3 mb-1">Call Us</h5>
                    <p class="text-muted mb-0"><?= CONTACT_PHONE ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Form & Map -->
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <!-- Left: Form -->
            <div class="col-lg-7" data-aos="fade-right">
                <div class="glass-card p-4">
                    <h4 class="fw-bold mb-1"><i class="fas fa-paper-plane text-primary me-2"></i>Send Us a Message</h4>
                    <p class="text-muted mb-4">Fill out the form below and we'll respond within 24 hours.</p>

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

                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Your Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="name" class="form-control" placeholder="Enter your full name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Your Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Subject</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                    <input type="text" name="subject" class="form-control" placeholder="What is this regarding?">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                                <textarea name="message" class="form-control" rows="5" placeholder="Write your message here..." required></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg px-4">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: Map + Social -->
            <div class="col-lg-5" data-aos="fade-left">
                <div class="glass-card p-4 mb-4">
                    <h4 class="fw-bold mb-3"><i class="fas fa-map-marked-alt text-primary me-2"></i>Find Us Here</h4>
                    <div class="rounded-4 overflow-hidden border" style="height:260px;">
<iframe
                            src="https://www.google.com/maps?q=Parul+University,+Waghodia,+Vadodara,+Gujarat&output=embed"
                            width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>

                <div class="glass-card p-4">
                    <h4 class="fw-bold mb-3"><i class="fas fa-share-alt text-primary me-2"></i>Follow Us</h4>
                    <p class="text-muted mb-3">Stay updated with the latest events and news.</p>
                    <div class="footer-social">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ CTA -->
<section class="pb-5">
    <div class="container">
        <div class="glass-card p-4 text-center" data-aos="fade-up">
            <h4 class="fw-bold mb-2"><i class="fas fa-question-circle text-primary me-2"></i>Have Questions?</h4>
            <p class="text-muted mb-3">Check out our frequently asked questions for quick answers.</p>
            <a href="<?= SITE_URL ?>faq.php" class="btn btn-outline-primary px-4">
                <i class="fas fa-book-open me-2"></i>View FAQ
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
