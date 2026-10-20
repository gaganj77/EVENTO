-- =====================================================
-- EVENTO - Complete Database Schema
-- Professional Event Booking Platform
-- =====================================================

CREATE DATABASE IF NOT EXISTS evento;
USE evento;

-- =====================================================
-- STATES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS states (
    id INT PRIMARY KEY AUTO_INCREMENT,
    state_name VARCHAR(100) NOT NULL,
    state_code VARCHAR(10) NOT NULL,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_state_name (state_name),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CITIES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS cities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    city_name VARCHAR(100) NOT NULL,
    state_id INT NOT NULL,
    latitude DECIMAL(10,8) DEFAULT NULL,
    longitude DECIMAL(11,8) DEFAULT NULL,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE CASCADE,
    INDEX idx_city_name (city_name),
    INDEX idx_state_id (state_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CATEGORIES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    category_slug VARCHAR(100) NOT NULL UNIQUE,
    category_icon VARCHAR(255) DEFAULT NULL,
    category_image VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category_slug (category_slug),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SUBCATEGORIES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS subcategories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    subcategory_name VARCHAR(100) NOT NULL,
    subcategory_slug VARCHAR(100) NOT NULL,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category_id (category_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- USERS TABLE (Customers & Organizers)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    profile_photo VARCHAR(255) DEFAULT 'default-user.png',
    role ENUM('user', 'organizer', 'admin') NOT NULL DEFAULT 'user',
    is_verified TINYINT(1) DEFAULT 0,
    is_organizer_approved TINYINT(1) DEFAULT 0,
    otp VARCHAR(10) DEFAULT NULL,
    otp_expiry DATETIME DEFAULT NULL,
    remember_token VARCHAR(255) DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    status TINYINT(1) DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_is_verified (is_verified),
    INDEX idx_organizer_approved (is_organizer_approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ADMINS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    profile_photo VARCHAR(255) DEFAULT 'default-admin.png',
    role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    remember_token VARCHAR(255) DEFAULT NULL,
    status TINYINT(1) DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    short_description VARCHAR(500) DEFAULT NULL,
    category_id INT NOT NULL,
    subcategory_id INT DEFAULT NULL,
    city_id INT NOT NULL,
    venue VARCHAR(255) NOT NULL,
    venue_address TEXT DEFAULT NULL,
    latitude DECIMAL(10,8) DEFAULT NULL,
    longitude DECIMAL(11,8) DEFAULT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    end_time TIME DEFAULT NULL,
    banner VARCHAR(255) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_price DECIMAL(10,2) DEFAULT NULL,
    total_seats INT NOT NULL DEFAULT 0,
    booked_seats INT NOT NULL DEFAULT 0,
    available_seats INT GENERATED ALWAYS AS (total_seats - booked_seats) STORED,
    status ENUM('pending', 'approved', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
    is_featured TINYINT(1) DEFAULT 0,
    is_trending TINYINT(1) DEFAULT 0,
    age_restriction VARCHAR(50) DEFAULT 'All Ages',
    event_rules TEXT DEFAULT NULL,
    tags VARCHAR(500) DEFAULT NULL,
    views INT DEFAULT 0,
    rating DECIMAL(2,1) DEFAULT 0.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL,
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_category_id (category_id),
    INDEX idx_city_id (city_id),
    INDEX idx_event_date (event_date),
    INDEX idx_status (status),
    INDEX idx_is_featured (is_featured),
    INDEX idx_is_trending (is_trending),
    INDEX idx_slug (slug),
    FULLTEXT idx_search (title, description, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVENT GALLERY TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS event_gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVENT FAQ TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS event_faqs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TICKETS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    ticket_type VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    available INT NOT NULL DEFAULT 0,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- BOOKINGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    coupon_code VARCHAR(50) DEFAULT NULL,
    final_amount DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    status ENUM('pending', 'confirmed', 'cancelled', 'refunded', 'completed') NOT NULL DEFAULT 'pending',
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT NULL,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cancelled_at DATETIME DEFAULT NULL,
    refund_amount DECIMAL(10,2) DEFAULT NULL,
    refund_requested TINYINT(1) DEFAULT 0,
    refund_reason TEXT DEFAULT NULL,
    checked_in TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_user_id (user_id),
    INDEX idx_event_id (event_id),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_booking_date (booking_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS booking_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    ticket_id INT NOT NULL,
    ticket_type VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_ticket_id (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PAYMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    payment_id VARCHAR(100) NOT NULL UNIQUE,
    gateway VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    status ENUM('pending', 'success', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    gateway_response TEXT DEFAULT NULL,
    transaction_time DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- REVIEWS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT DEFAULT NULL,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_event (user_id, event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_event_id (event_id),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- WISHLIST TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS wishlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (user_id, event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COUPONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) DEFAULT 0,
    min_quantity INT NOT NULL DEFAULT 1,
    max_discount_amount DECIMAL(10,2) DEFAULT NULL,
    usage_limit INT DEFAULT 0,
    used_count INT DEFAULT 0,
    event_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    valid_from DATETIME NOT NULL,
    valid_to DATETIME NOT NULL,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_valid_from (valid_from),
    INDEX idx_valid_to (valid_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COUPON USAGE TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS coupon_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    coupon_id INT NOT NULL,
    user_id INT NOT NULL,
    booking_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    INDEX idx_coupon_id (coupon_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CONTACT MESSAGES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    replied_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FAQ TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS faq (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    sort_order INT DEFAULT 0,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NEWSLETTER TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS newsletter (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ACTIVITY LOGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- LOGIN HISTORY TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS login_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    role ENUM('user', 'organizer', 'admin') NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    login_status ENUM('success', 'failed') NOT NULL,
    fail_reason VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_login_status (login_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- AUDIT LOGS TABLE (Admin actions)
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT DEFAULT NULL,
    old_values TEXT DEFAULT NULL,
    new_values TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_entity_type (entity_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SETTINGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_group VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key),
    INDEX idx_setting_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ORGANIZER REQUESTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS organizer_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    organization_name VARCHAR(255) DEFAULT NULL,
    organization_type VARCHAR(100) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    document_path VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- REPORTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    event_id INT DEFAULT NULL,
    report_type VARCHAR(50) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'reviewed', 'resolved') NOT NULL DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_event_id (event_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- RECENTLY VIEWED TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS recently_viewed (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_event (user_id, event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SPONSORS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS sponsors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sponsor_name VARCHAR(255) NOT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TESTIMONIALS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS testimonials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    designation VARCHAR(100) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    content TEXT NOT NULL,
    rating TINYINT DEFAULT 5,
    status TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT SAMPLE STATES
-- =====================================================
INSERT INTO states (state_name, state_code) VALUES
('Rajasthan', 'RJ'),
('Maharashtra', 'MH'),
('Delhi', 'DL'),
('Uttar Pradesh', 'UP'),
('Gujarat', 'GJ'),
('Karnataka', 'KA'),
('Tamil Nadu', 'TN'),
('West Bengal', 'WB');

-- =====================================================
-- INSERT SAMPLE CITIES
-- =====================================================
INSERT INTO cities (city_name, state_id, latitude, longitude) VALUES
('Kota', 1, 25.2138, 75.8648),
('Jaipur', 1, 26.9124, 75.7873),
('Jodhpur', 1, 26.2389, 73.0243),
('Udaipur', 1, 24.5854, 73.7125),
('Mumbai', 2, 19.0760, 72.8777),
('Pune', 2, 18.5204, 73.8567),
('New Delhi', 3, 28.6139, 77.2090),
('Lucknow', 4, 26.8467, 80.9462),
('Ahmedabad', 5, 23.0225, 72.5714),
('Bangalore', 6, 12.9716, 77.5946);

-- =====================================================
-- INSERT SAMPLE CATEGORIES
-- =====================================================
INSERT INTO categories (category_name, category_slug, category_icon, description) VALUES
('Music', 'music', 'fa-music', 'Live concerts, music festivals, and musical performances'),
('Workshop', 'workshop', 'fa-tools', 'Hands-on learning experiences and skill-building workshops'),
('Meetup', 'meetup', 'fa-users', 'Social gatherings, networking events, and community meetups'),
('Comedy', 'comedy', 'fa-laugh', 'Stand-up comedy shows and comedy festivals'),
('Sports', 'sports', 'fa-futbol', 'Sports events, tournaments, and athletic competitions'),
('Art', 'art', 'fa-palette', 'Art exhibitions, galleries, and creative showcases'),
('Food', 'food', 'fa-utensils', 'Food festivals, cooking classes, and culinary experiences'),
('Technology', 'technology', 'fa-laptop-code', 'Tech conferences, hackathons, and innovation summits');

-- =====================================================
-- INSERT SAMPLE FAQ
-- =====================================================
INSERT INTO faq (question, answer, category, sort_order) VALUES
('How do I book tickets for an event?', 'Simply browse events, select your preferred event, choose seats, apply any coupon codes, and proceed to checkout. You will receive a confirmation email with your QR code ticket.', 'Booking', 1),
('Can I cancel my booking?', 'Yes, you can cancel your booking from your profile. Cancellation is subject to the event organizer\'s refund policy. Refunds will be processed within 5-7 business days.', 'Cancellation', 2),
('How do I get my ticket after booking?', 'After successful payment, you will receive an email with your e-ticket containing a QR code. You can also download it from your booking history.', 'Tickets', 3),
('What payment methods are accepted?', 'We accept credit/debit cards, UPI, net banking, and popular digital wallets. All payments are processed securely.', 'Payment', 4),
('How do I become an organizer?', 'Register as a user, then apply to become an organizer from your dashboard. Our team will review your application and approve it within 24-48 hours.', 'Organizer', 5);

-- =====================================================
-- INSERT SAMPLE SETTINGS
-- =====================================================
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('site_name', 'Evento', 'general'),
('site_description', 'Discover amazing events happening near you', 'general'),
('site_logo', 'logo.png', 'general'),
('site_favicon', 'favicon.ico', 'general'),
('admin_email', 'jaiswalgagan67@gmail.com', 'general'),
('contact_email', 'evento.helpdesk@gmail.com', 'contact'),
('contact_phone', '+91 9876543210', 'contact'),
('contact_address', 'Kota, Rajasthan, India', 'contact'),
('social_facebook', 'https://facebook.com/evento', 'social'),
('social_instagram', 'https://instagram.com/evento', 'social'),
('social_twitter', 'https://twitter.com/evento', 'social'),
('social_youtube', 'https://youtube.com/evento', 'social'),
('razorpay_key', '', 'payment'),
('razorpay_secret', '', 'payment'),
('stripe_key', '', 'payment'),
('stripe_secret', '', 'payment'),
('paypal_client_id', '', 'payment'),
('paypal_secret', '', 'payment'),
('mail_host', '', 'email'),
('mail_port', '587', 'email'),
('mail_username', '', 'email'),
('mail_password', '', 'email'),
('mail_encryption', 'tls', 'email'),
('mail_from_name', 'Evento', 'email'),
('currency_symbol', '₹', 'general'),
('currency_code', 'INR', 'general'),
('timezone', 'Asia/Kolkata', 'general'),
('commission_percentage', '10', 'general'),
('max_file_size', '5', 'general'),
('allowed_image_types', 'jpg,jpeg,png,gif,webp', 'general');

-- =====================================================
-- INSERT SAMPLE SPONSORS
-- =====================================================
INSERT INTO sponsors (sponsor_name, sort_order) VALUES
('TechCorp', 1),
('EventHub', 2),
('BookMyShow', 3),
('Paytm', 4),
('Google', 5);

-- =====================================================
-- INSERT SAMPLE TESTIMONIALS
-- =====================================================
INSERT INTO testimonials (name, designation, content, rating, sort_order) VALUES
('Priya Sharma', 'Regular Attendee', 'Evento has completely changed how I discover events in my city. The booking process is seamless and I love the QR code tickets!', 5, 1),
('Rajesh Kumar', 'Event Organizer', 'As an organizer, Evento provides incredible tools to manage events. The analytics dashboard is a game-changer.', 5, 2),
('Ananya Patel', 'Music Lover', 'I have attended so many amazing concerts through Evento. The seat selection feature is fantastic!', 5, 3);

-- =====================================================
-- INSERT SAMPLE COUPONS
-- =====================================================
INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, usage_limit, valid_from, valid_to) VALUES
('WELCOME10', 'percentage', 10, 500, 100, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('EVENTO50', 'fixed', 50, 200, 50, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY)),
('FIRSTEVENT', 'percentage', 15, 0, 200, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY));
