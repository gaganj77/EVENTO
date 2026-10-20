<?php
/**
 * EVENTO - FAQ Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$page_title = 'FAQ';
$faqs = mysqli_query($conn, "SELECT * FROM faq WHERE status = 1 ORDER BY sort_order");
include __DIR__ . '/includes/header.php';
?>

<section class="hero-section" style="padding-top: 100px; padding-bottom: 40px;">
    <div class="container text-center">
        <h1 class="hero-title" data-aos="fade-up">Frequently Asked <span class="gradient-text">Questions</span></h1>
        <p class="hero-description mx-auto" data-aos="fade-up">Find answers to common questions about Evento</p>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="faq-accordion accordion" id="faqAccordion">
                    <?php $f = 0; while ($faq = mysqli_fetch_assoc($faqs)): $f++; ?>
                    <div class="accordion-item" data-aos="fade-up">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $f > 1 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $f ?>">
                                <?= htmlspecialchars($faq['question']) ?>
                            </button>
                        </h2>
                        <div id="faq<?= $f ?>" class="accordion-collapse collapse <?= $f == 1 ? 'show' : '' ?>" data-bs-parent="#faqAccordion">
                            <div class="accordion-body"><?= htmlspecialchars($faq['answer']) ?></div>
                    </div>
                    <?php endwhile; ?>
                </div>
        </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
