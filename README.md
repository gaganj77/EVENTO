# 🎪 EVENTO - Professional Event Booking Platform

A local event booking platform built with PHP 8, MySQL, Bootstrap 5, and modern vanilla JavaScript. Features a unique glassmorphism UI design with purple-teal gradient theme. Online card/UPI checkout is enabled only when Razorpay credentials are configured; otherwise checkout safely offers cash bookings and reports keep them unpaid.

## 🚀 Features

### User Features
- Authentication (Login/Register/Forgot Password/OTP; OTP email delivery requires a working PHP mail transport)
- Role-based access (User/Organizer/Admin)
- Browse and search events with smart filters
- Book tickets with seat selection
- Apply coupon codes for discounts
- QR code tickets with download
- Booking history and wishlist management
- Event reviews and ratings
- Notification center
- Dark/Light mode toggle

### Organizer Features
- Professional dashboard with analytics
- Create/Edit/Delete events with gallery
- Track bookings and revenue
- View attendee lists
- Create discount coupons
- Download reports

### Admin Features
- Complete platform management
- Manage users, organizers, events
- Approve/reject events and organizer requests
- Manage categories, cities, coupons
- View revenue charts and analytics
- Site settings management
- Activity and audit logs

## 🛠️ Tech Stack

| Technology | Purpose |
|------------|---------|
| **PHP 8** | Backend logic & API |
| **MySQL** | Database |
| **Bootstrap 5.3** | Frontend framework |
| **Font Awesome 6** | Icons |
| **Chart.js** | Dashboard charts |
| **QRCode.js** | QR code generation |
| **AOS** | Scroll animations |

## 🎨 Design System

- **Primary Colors**: Purple (#6C3CE1) → Teal (#0EA5E9) gradient
- **Design Style**: Glassmorphism with soft shadows
- **Typography**: Inter font family
- **Dark/Light Mode**: System-aware with manual toggle

## 📁 Project Structure

```
evento/
├── admin/           # Admin panel
├── api/             # AJAX/API endpoints
├── auth/            # Authentication pages
├── booking/         # Booking system
├── css/             # Stylesheets
├── database/        # SQL schema
├── images/          # Static images
├── includes/        # Core includes (header, footer, config)
├── js/              # JavaScript
├── organizer/       # Organizer panel
├── uploads/         # User uploads (events, gallery, users)
├── user/            # User dashboard
├── about.php
├── contact.php
├── event.php        # Single event page
├── events.php       # Events listing
├── faq.php
└── index.php        # Home page
```

## 💾 Installation Guide

### Prerequisites
- XAMPP (PHP 8+, MySQL)
- Web browser

### Steps

1. **Place the project files** in `C:\xampp\htdocs\evento\`

2. **Import Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `evento`
   - Import `database/schema.sql` file
   - Create the first administrator with `database/create_admin.php` after setting the four `INITIAL_ADMIN_*` environment variables below. Never use a shared demo password.

3. **Configure Database**
   - Set `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME` in the PHP server environment.
   - Local XAMPP defaults are host `localhost`, user `root`, and an empty password.

4. **Start XAMPP**
   - Start Apache and MySQL services

5. **Access the application**
   - URL: http://localhost/evento/ (or appropriate path)

- **Organizer panel**: http://localhost/evento/organizer/dashboard.php
- **User dashboard**: http://localhost/evento/user/dashboard.php

### Local mail and payment configuration

Set these environment variables for the Apache process and restart Apache:

| Variable | Purpose |
|----------|---------|
| `APP_ENV` | Set to `production` to hide PHP diagnostics from visitors |
| `SITE_URL` | Canonical public site URL, including `https://` |
| `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` | Database connection |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Optional Google sign-in credentials |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD` | SMTP server used for OTP and account email |
| `SMTP_ENCRYPTION` | `tls` (default), `ssl`, or `none` |
| `SMTP_FROM`, `SMTP_FROM_NAME` | Optional verified sender address and name |
| `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET` | Enables Razorpay order creation, checkout, server-side signature/payment verification, capture, and booking confirmation |

Configure variables for the Apache process (for XAMPP, use Apache's environment configuration or the environment of the process that starts Apache) and restart Apache. Never commit or share the secret values. Razorpay orders reserve tickets for up to 30 minutes; abandoned or failed payments release the reservation when checkout is revisited only after Razorpay confirms there is no authorized or captured payment. Orders with a payment in progress stay reserved for safe reconciliation. The callback validates the Razorpay signature and retrieves the payment from Razorpay before marking a booking as paid. Cash bookings remain available and are not counted as collected revenue until an administrator records receipt.

### Vercel deployment limitations

This is the original PHP/MySQL application, not a static-only site. Vercel's PHP support is community-maintained; the application's MySQL database, shared PHP sessions, and persistent event/profile uploads also need separately provisioned services. The current Vercel project does not have those backend services configured, so uploading the PHP source alone will not make sign-in, bookings, organizer tools, or admin reports work. Do not replace the application with a mock front end or upload the local QA database. Keep the local XAMPP installation as the working copy until a compatible Vercel backend and its free-tier limits have been verified.

Never publish credentials, QA logs, database setup utilities, or private local account/booking data. Set `APP_ENV=production`, use provider-managed environment variables, and create a unique administrator password before exposing a real backend publicly.

### Free PHP/MySQL hosting

For a complete PHP deployment, use a host that supports PHP and MySQL rather than the static Vercel preview. Copy `includes/config.local.example.php` to `includes/config.local.php` on the host and enter the host-issued database connection details there. That local settings file is ignored by Git; never upload it to a public repository or share its secrets. Import `database/schema.sql` into the database you created on the host, then upload the application files and the `vendor/` directory. Exclude `_qa/`, local logs, database setup utilities, and local account/booking data.

User registration OTP requires a working SMTP or PHP mail transport. Google sign-in and card payments require their own valid provider credentials; without them, Google sign-in and online payment stay unavailable while cash booking remains available. Free hosts can impose resource, email, upload, and database limits, so verify their current terms and test each feature on the deployed site before presenting it as fully operational.

## 🔒 Security Features

- Password hashing (bcrypt)
- Prepared SQL statements
- CSRF token protection
- XSS prevention (htmlspecialchars)
- Input validation & sanitization
- Session management
- Role-based middleware
- File upload validation & compression
- Activity logging

## 🗄️ Database Tables

25+ tables including:
- `users`, `admins`, `events`, `categories`, `cities`, `states`
- `bookings`, `booking_items`, `payments`, `tickets`
- `reviews`, `wishlist`, `notifications`, `coupons`
- `activity_logs`, `login_history`, `audit_logs`
- `settings`, `faq`, `newsletter`, `contact_messages`
- `sponsors`, `testimonials`, `event_gallery`, `event_faqs`

## 🧪 Sample Data

The database (via `database/schema.sql` + `database/sample_data.sql`) includes realistic demo content:

| Data | Count |
|------|-------|
| States | 8 |
| Cities | 20 (Indore, Surat, Ahmedabad, Vadodara, Mumbai, Delhi, Bangalore, Jaipur, Pune, Hyderabad & more) |
| Categories | 8 (Music, Workshop, Meetup, Comedy, Sports, Art, Food, Technology) |
| Users | 6 (3 organizers + 3 customers) |
| Events | 17 approved events across 10+ cities with banner images |
| Bookings | 4 sample confirmed bookings |
| Reviews | 4 sample reviews with ratings |
| Coupons | 3 (WELCOME10, EVENTO50, FIRSTEVENT) |
| FAQs | 9 |
| Testimonials | 6 |
| Admin | 1 (admin@evento.com) |
| Settings | 30 site configuration entries |

### Quick Database Rebuild
To rebuild the database from scratch with all sample data:
```bash
cd C:\xampp\htdocs\evento
php database\setup_db.php
```
This drops and recreates the `evento` database, imports the schema, and loads all sample data automatically.

## 📄 License

MIT License - feel free to use for personal or commercial projects.

## 🤝 Support

For issues, questions, or contributions, please open an issue on GitHub.
