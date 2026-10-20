<?php
/**
 * EVENTO - Organizer Payout / Commission helpers
 *
 * The platform keeps a commission on every confirmed booking and pays the
 * remaining amount to the event organizer. This file provides:
 *   - ensure_payouts_table()        : creates the `payouts` table when missing
 *   - payout_commission_percent()   : platform commission % (settings driven)
 *   - organizer_payout_rows()       : earnings + payable + paid per organizer
 *   - organizer_payout_detail()     : per-event breakdown for one organizer
 *   - record_payout()               : save a payout entry (and optional coupon)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Create the payouts table if it doesn't exist yet (idempotent).
 */
function ensure_payouts_table()
{
    global $conn;

    $sql = "CREATE TABLE IF NOT EXISTS payouts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        organizer_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        method VARCHAR(50) DEFAULT 'bank',
        reference VARCHAR(100) DEFAULT NULL,
        coupon_code VARCHAR(50) DEFAULT NULL,
        period_from DATE DEFAULT NULL,
        period_to DATE DEFAULT NULL,
        note TEXT DEFAULT NULL,
        status ENUM('pending','paid') NOT NULL DEFAULT 'paid',
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payout_organizer (organizer_id),
        INDEX idx_payout_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    return (bool)mysqli_query($conn, $sql);
}

/**
 * Platform commission percentage (stored in settings as commission_percent).
 */
function payout_commission_percent()
{
    $value = get_setting('commission_percent', '');
    if ($value === '' || !is_numeric($value)) {
        return 10.0;
    }
    $percent = (float)$value;
    if ($percent < 0) { $percent = 0; }
    if ($percent > 100) { $percent = 100; }
    return $percent;
}

/**
 * Earnings summary per organizer (confirmed bookings only).
 *
 * @param int|null $organizer_id limit to one organizer
 * @return array rows with commission/payable/balance pre-calculated
 */
function organizer_payout_rows($organizer_id = null)
{
    global $conn;

    ensure_payouts_table();

    $where = "WHERE u.role = 'organizer'";
    if ($organizer_id) {
        $where .= " AND u.id = " . (int)$organizer_id;
    }

    $sql = "SELECT u.id AS organizer_id, u.full_name, u.email, u.is_organizer_approved,
                   COUNT(DISTINCT e.id) AS events_count,
                   COUNT(b.id) AS bookings_count,
                   COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN b.quantity ELSE 0 END), 0) AS tickets_sold,
                   COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%' THEN b.total_amount ELSE 0 END), 0) AS gross_collection,
                   COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%' THEN b.discount_amount ELSE 0 END), 0) AS total_discount,
                   COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%' THEN b.final_amount ELSE 0 END), 0) AS net_collection,
                   COALESCE((SELECT SUM(p.amount) FROM payouts p WHERE p.organizer_id = u.id AND p.status = 'paid'), 0) AS total_paid
            FROM users u
            LEFT JOIN events e ON e.user_id = u.id
            LEFT JOIN bookings b ON b.event_id = e.id
            $where
            GROUP BY u.id
            ORDER BY net_collection DESC, u.full_name ASC";

    $rows = [];
    $percent = payout_commission_percent();
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $net = (float)$row['net_collection'];
        $commission = round($net * $percent / 100, 2);
        $payable = round($net - $commission, 2);
        $row['commission_percent'] = $percent;
        $row['platform_commission'] = $commission;
        $row['organizer_payable'] = $payable;
        $row['balance'] = round($payable - (float)$row['total_paid'], 2);
        $rows[] = $row;
    }
    return $rows;
}
/**
 * Per-event booking/revenue breakdown for one organizer.
 */
function organizer_payout_detail($organizer_id)
{
    global $conn;

    $sql = "SELECT e.id, e.title, e.event_date, e.status,
                   COUNT(b.id) AS bookings_count,
                   COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN b.quantity ELSE 0 END), 0) AS tickets_sold,
                   COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%' THEN b.discount_amount ELSE 0 END), 0) AS total_discount,
                   COALESCE(SUM(CASE WHEN b.status = 'confirmed' AND b.payment_status = 'completed' AND COALESCE(b.payment_method, '') NOT LIKE 'DEMO%' THEN b.final_amount ELSE 0 END), 0) AS net_collection
            FROM events e
            LEFT JOIN bookings b ON b.event_id = e.id
            WHERE e.user_id = " . (int)$organizer_id . "
            GROUP BY e.id
            ORDER BY net_collection DESC, e.event_date DESC";

    $rows = [];
    $percent = payout_commission_percent();
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $net = (float)$row['net_collection'];
        $row['platform_commission'] = round($net * $percent / 100, 2);
        $row['organizer_payable'] = round($net - $row['platform_commission'], 2);
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Record a payout for an organizer and optionally create a payout coupon
 * (a coupon worth the settled amount that the organizer can hand out).
 *
 * @return array ['success' => bool, 'message' => string, 'payout_id' => int, 'coupon' => string]
 */
function record_payout($organizer_id, $amount, $method = 'bank', $reference = '', $note = '', $create_coupon = false, $admin_id = null)
{
    $organizer_id = (int)$organizer_id;
    $amount = round((float)$amount, 2);
    $method = sanitize_string($method);
    $reference = sanitize_string($reference);
    $note = sanitize_string($note);

    if ($organizer_id <= 0 || $amount <= 0) {
        return ['success' => false, 'message' => 'Select an organizer and enter a valid amount.'];
    }

    $organizer = fetch_single("SELECT id, full_name FROM users WHERE id = ? AND role = 'organizer'", "i", [$organizer_id]);
    if (!$organizer) {
        return ['success' => false, 'message' => 'Organizer not found.'];
    }

    ensure_payouts_table();

    $coupon_code = '';
    if ($create_coupon) {
        $coupon_code = 'PAYOUT' . $organizer_id . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $created = insert_data(
            "INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, min_quantity, max_discount_amount, usage_limit, event_id, user_id, valid_from, valid_to, status)
             VALUES (?, 'fixed', ?, 0, 1, 0, 1, NULL, ?, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY), 1)",
            "sdi",
            [$coupon_code, $amount, $organizer_id]
        );
        if (!$created) {
            $coupon_code = '';
        }
    }

    $payout_id = insert_data(
        "INSERT INTO payouts (organizer_id, amount, method, reference, coupon_code, note, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, 'paid', ?)",
        "idssssi",
        [$organizer_id, $amount, $method, $reference, $coupon_code ?: null, $note, (int)$admin_id]
    );

    if ($payout_id) {
        create_notification(
            $organizer_id,
            'payout',
            'Payout Received',
            'A payout of ' . CURRENCY_SYMBOL . number_format($amount, 2) . ' has been recorded for your account.',
            SITE_URL . 'organizer/dashboard.php'
        );
        return [
            'success'   => true,
            'message'   => 'Payout of ' . CURRENCY_SYMBOL . number_format($amount, 2) . ' recorded for ' . $organizer['full_name'] . '.',
            'payout_id' => (int)$payout_id,
            'coupon'    => $coupon_code,
        ];
    }

    return ['success' => false, 'message' => 'Could not save the payout entry.'];
}