<?php
/**
 * EVENTO - About Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$page_title = 'About Us';
include __DIR__ . '/includes/header.php';
?>

<section class="hero-section" style="padding-top: 100px; padding-bottom: 40px;">
    <div class="container">
        <h1 class="hero-title text-center" data-aos="fade-up">About <span class="gradient-text">Evento</span></h1>
        <p class="hero-description text-center mx-auto" data-aos="fade-up">Your ultimate destination for discovering and booking amazing events</p>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <div class="row g-4 align-items-center mb-5">
            <div class="col-lg-6" data-aos="fade-right">
                <img src="https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&q=80" class="img-fluid rounded-4 shadow-lg" alt="About Evento">
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <h2 class="section-title">Our Story</h2>
                <p class="text-secondary" style="line-height:1.8;">Evento was born from a simple idea: make it easy for people to discover and book amazing events in their city. From concerts and workshops to meetups and sports events, we bring together the best experiences your city has to offer.</p>
                <p class="text-secondary" style="line-height:1.8;">Our platform connects event organizers with attendees, providing a seamless booking experience with secure payments, instant ticket delivery, and dedicated support.</p>
                <div class="hero-stats mt-4">
                    <div class="hero-stat"><div class="stat-number">500+</div><div class="stat-label">Events</div>
                    <div class="hero-stat"><div class="stat-number">50+</div><div class="stat-label">Cities</div>
                    <div class="hero-stat"><div class="stat-number">10K+</div><div class="stat-label">Happy Users</div>
                </div>
        </div>
        
        <div class="row g-4 text-center">
            <div class="col-md-4" data-aos="fade-up">
                <div class="glass-card py-5">
                    <i class="fas fa-bullseye fa-3x mb-3 text-primary"></i>
                    <h5 class="fw-bold">Our Mission</h5>
                    <p class="text-muted">To make event discovery and booking effortless, enjoyable, and accessible to everyone.</p>
                </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card py-5">
                    <i class="fas fa-eye fa-3x mb-3 text-success"></i>
                    <h5 class="fw-bold">Our Vision</h5>
                    <p class="text-muted">To be the most trusted and loved event discovery platform in every city.</p>
                </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card py-5">
                    <i class="fas fa-heart fa-3x mb-3 text-danger"></i>
                    <h5 class="fw-bold">Our Values</h5>
                    <p class="text-muted">Trust, innovation, community, and delivering exceptional experiences.</p>
                </div>
        </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
