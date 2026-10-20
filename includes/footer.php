<?php
/**
 * EVENTO - Footer Template
 * Professional footer with links, social icons, and newsletter
 */
?>
<!-- =====================================================
     FOOTER SECTION
     ===================================================== -->
<footer class="footer">
    <div class="container">
        <div class="row g-4">
            <!-- Brand -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-brand">
                    <i class="fas fa-calendar-star me-2"></i>EVENTO
                </div>
                <p class="footer-description">
                    Discover and book the best events in your city. From concerts and workshops to meetups and sports events, Evento brings you closer to experiences that matter.
                </p>
                <div class="footer-social">
                    <a href="#" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" target="_blank" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" target="_blank" title="YouTube"><i class="fab fa-youtube"></i></a>
                    <a href="#" target="_blank" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6">
                <h5 class="footer-title">Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="<?= SITE_URL ?>index.php"><i class="fas fa-chevron-right fa-xs"></i> Home</a></li>
                    <li><a href="<?= SITE_URL ?>events.php"><i class="fas fa-chevron-right fa-xs"></i> Events</a></li>
                    <li><a href="<?= SITE_URL ?>contact.php"><i class="fas fa-chevron-right fa-xs"></i> Contact</a></li>
                    <li><a href="<?= SITE_URL ?>about.php"><i class="fas fa-chevron-right fa-xs"></i> About Us</a></li>
                    <li><a href="<?= SITE_URL ?>faq.php"><i class="fas fa-chevron-right fa-xs"></i> FAQ</a></li>
                </ul>
            </div>
            
            <!-- Categories -->
            <div class="col-lg-3 col-md-6">
                <h5 class="footer-title">Categories</h5>
                <ul class="footer-links">
                    <?php
                    $cat_result = mysqli_query($conn, "SELECT * FROM categories WHERE status = 1 LIMIT 6");
                    while ($cat = mysqli_fetch_assoc($cat_result)):
                    ?>
<li>
                        <a href="<?= SITE_URL ?>events.php?category=<?= $cat['category_slug'] ?>">
                            <i class="fas fa-chevron-right fa-xs"></i> <?= category_icon($cat['category_icon']) ?> <span class="ms-1"><?= $cat['category_name'] ?></span>
                        </a>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>
            
            <!-- Contact -->
            <div class="col-lg-3 col-md-6">
                <h5 class="footer-title">Contact Us</h5>
                <ul class="footer-links">
                    <li>
                        <a href="<?= SITE_URL ?>contact.php"><i class="fas fa-map-marker-alt"></i> Parul University, Waghodia, Vadodara, Gujarat</a>
                    </li>
                    <li>
                        <a href="mailto:<?= CONTACT_EMAIL ?>"><i class="fas fa-envelope"></i> <?= CONTACT_EMAIL ?></a>
                    </li>
                    <li>
                        <a href="tel:<?= CONTACT_PHONE ?>"><i class="fas fa-phone"></i> <?= CONTACT_PHONE ?></a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="row align-items-center">
                <div class="col-md-6 text-md-start">
                    <p class="mb-0">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-muted me-3">Privacy Policy</a>
                    <a href="#" class="text-muted me-3">Terms of Service</a>
                    <a href="#" class="text-muted">Refund Policy</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- =====================================================
     SCRIPTS
     ===================================================== -->
<!-- Bootstrap 5 JS Bundle (local) -->
<script src="<?= SITE_URL ?>js/bootstrap.bundle.min.js"></script>
<!-- AOS Animation Library (local) -->
<script src="<?= SITE_URL ?>js/aos.js"></script>
<!-- Chart.js for Dashboard (local) -->
<script src="<?= SITE_URL ?>js/chart.umd.min.js"></script>
<!-- QR Code Generator (local) -->
<script src="<?= SITE_URL ?>js/qrcode.min.js"></script>
<!-- Custom JS -->
<script src="<?= SITE_URL ?>js/main.js"></script>

<script>
    // Initialize AOS
    AOS.init({
        duration: 800,
        once: true,
        offset: 100
    });
    
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('mainNavbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // Back to Top button
    const backToTop = document.getElementById('backToTop');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            backToTop.classList.add('show');
        } else {
            backToTop.classList.remove('show');
        }
    });
    
    if (backToTop) {
        backToTop.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
    
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            if (themeIcon) {
                themeIcon.className = newTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            }
        });
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        if (themeIcon) {
            themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }
    }
    
    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(function(alert) {
        setTimeout(function() {
            alert.classList.remove('show');
            setTimeout(function() { alert.remove(); }, 300);
        }, 5000);
    });
</script>

</body>
</html>
