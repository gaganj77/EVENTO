<?php
/**
 * EVENTO - Download PDF Ticket
 * Clean, properly-structured printable ticket (Save as PDF via browser print)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_auth();

$booking_id = sanitize_input($_GET['booking_id'] ?? '');

$booking = fetch_single(
    "SELECT b.*, e.title, e.event_date, e.event_time, e.end_time, e.venue, e.venue_address, e.banner, e.description,
     c.category_name, ci.city_name, s.state_name, u.full_name as user_name, u.email as user_email, u.phone as user_phone
     FROM bookings b
     JOIN events e ON b.event_id = e.id
     JOIN categories c ON e.category_id = c.id
     JOIN cities ci ON e.city_id = ci.id
     LEFT JOIN states s ON ci.state_id = s.id
     JOIN users u ON b.user_id = u.id
     WHERE b.booking_id = ? AND b.user_id = ?",
    "si", [$booking_id, $_SESSION['user_id']]
);

if (!$booking) {
    $_SESSION['error'] = 'Booking not found';
    header('Location: ' . SITE_URL . 'user/bookings.php');
    exit();
}

$unit_price = $booking['quantity'] > 0 ? $booking['total_amount'] / $booking['quantity'] : 0;
$amount_label = $booking['payment_status'] === 'completed'
    ? 'Total Paid'
    : ($booking['payment_status'] === 'pending' ? 'Amount Due at Venue' : 'Total Amount');
$banner = event_banner($booking['banner'] ?? '');
$verify_url = SITE_URL . 'booking/verify.php?booking_id=' . rawurlencode($booking['booking_id']);

require_once __DIR__ . '/../vendor/autoload.php';
$qr_result = new Endroid\QrCode\Builder\Builder(
    writer: new Endroid\QrCode\Writer\PngWriter(),
    data: $verify_url,
    encoding: new Endroid\QrCode\Encoding\Encoding('UTF-8'),
    errorCorrectionLevel: Endroid\QrCode\ErrorCorrectionLevel::High,
    size: 220,
    margin: 10
);
$qr_data_uri = $qr_result->build()->getDataUri();

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket - <?= htmlspecialchars($booking['booking_id']) ?></title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            background: #ffffff;
            color: #1f2937;
            padding: 0;
        }
        .ticket {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #dfe5ef;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(31,41,55,0.1);
            page-break-inside: avoid;
        }
        .ticket-header {
            background: linear-gradient(135deg, #6C3CE1, #0EA5E9);
            color: #ffffff;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ticket-header .brand { font-size: 23px; font-weight: 800; letter-spacing: 1px; }
        .ticket-header .subtitle { opacity: 0.9; font-size: 11px; margin-top: 3px; }
        .status-badge {
            background: rgba(255,255,255,0.22);
            border: 1px solid rgba(255,255,255,0.5);
            padding: 5px 12px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ticket-body { padding: 18px 24px 12px; }
        .event-banner {
            width: 100%;
            height: 125px;
            object-fit: cover;
            border-radius: 9px;
            margin-bottom: 12px;
        }
        .event-title { font-size: 19px; font-weight: 800; margin-bottom: 3px; }
        .event-category { color: #6C3CE1; font-weight: 600; font-size: 10px; text-transform: uppercase; letter-spacing: 0.6px; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px 12px;
            margin: 13px 0;
            padding: 11px 0;
            border-top: 1px dashed #d8dee9;
            border-bottom: 1px dashed #d8dee9;
        }
        .info-grid .item label {
            display: block;
            color: #9ca3af;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .info-grid .item strong { font-size: 10px; color: #111827; }
        .info-grid .item small { display: block; color: #6b7280; font-size: 8px; margin-top: 1px; }
        .price-summary {
            background: #f9fafb;
            border-radius: 8px;
            padding: 9px 14px;
            margin-bottom: 10px;
        }
        .price-row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 10px; }
        .price-row.total { border-top: 1px solid #e5e7eb; margin-top: 4px; padding-top: 6px; font-weight: 800; font-size: 13px; }
        .qr-section { text-align: center; padding: 2px 0 8px; }
        .qr-section .qr-box { display: inline-block; background: #fff; padding: 6px; border-radius: 8px; border: 1px solid #e5e7eb; }
        .qr-section .qr-box img { display: block; width: 118px; height: 118px; }
        .qr-section p { color: #6b7280; font-size: 9px; margin-top: 5px; }
        .ticket-footer {
            background: #f8f9fa;
            padding: 10px 24px;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        .print-btn {
            display: block;
            margin: 24px auto 0;
            padding: 14px 34px;
            background: #6C3CE1;
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
        }
        .print-btn:hover { background: #5b2ec4; }
        @media print {
            body { background: #fff; padding: 0; }
            .print-btn { display: none; }
            .ticket { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <!-- Header -->
        <div class="ticket-header">
            <div>
                <div class="brand">EVENTO</div>
                <div class="subtitle">E-Ticket / Booking Receipt</div>
            </div>
            <span class="status-badge"><?= htmlspecialchars(ucfirst($booking['status'])) ?></span>
        </div>

        <div class="ticket-body">
            <!-- Event Banner -->
            <img class="event-banner" src="<?= $banner ?>" alt="<?= htmlspecialchars($booking['title']) ?>"
                 onerror="this.src='https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=800&q=80'">

            <!-- Event Info -->
            <div class="event-title"><?= htmlspecialchars($booking['title']) ?></div>
            <div class="event-category"><?= htmlspecialchars($booking['category_name']) ?></div>

            <!-- Key Details -->
            <div class="info-grid">
                <div class="item">
                    <label>Booking ID</label>
                    <strong><?= htmlspecialchars($booking['booking_id']) ?></strong>
                </div>
                <div class="item">
                    <label>Status</label>
                    <strong><?= ucfirst($booking['status']) ?></strong>
                </div>
                <div class="item">
                    <label>Payment</label>
                    <strong><?= ucfirst($booking['payment_status']) ?></strong>
                </div>
                <div class="item">
                    <label>Date</label>
                    <strong><?= format_date($booking['event_date'], 'l, d M Y') ?></strong>
                </div>
                <div class="item">
                    <label>Time</label>
                    <strong><?= date('h:i A', strtotime($booking['event_time'])) ?>
                        <?= $booking['end_time'] ? '- ' . date('h:i A', strtotime($booking['end_time'])) : '' ?></strong>
                </div>
                <div class="item">
                    <label>Qty</label>
                    <strong><?= (int)$booking['quantity'] ?> ticket(s)</strong>
                </div>
                <div class="item">
                    <label>Venue</label>
                    <strong><?= htmlspecialchars($booking['venue']) ?></strong>
                    <small><?= htmlspecialchars($booking['city_name']) ?><?= $booking['state_name'] ? ', ' . htmlspecialchars($booking['state_name']) : '' ?></small>
                </div>
                <div class="item">
                    <label>Attendee</label>
                    <strong><?= htmlspecialchars($booking['user_name']) ?></strong>
                    <small><?= htmlspecialchars($booking['user_email']) ?></small>
                </div>
                <div class="item">
                    <label>Booked On</label>
                    <strong><?= format_date($booking['created_at']) ?></strong>
                </div>
            </div>

            <!-- Price Summary -->
            <div class="price-summary">
                <div class="price-row">
                    <span>Ticket Price (<?= (int)$booking['quantity'] ?> x ₹<?= number_format($unit_price, 2) ?>)</span>
                    <span>₹<?= number_format($booking['total_amount'], 2) ?></span>
                </div>
                <?php if ($booking['discount_amount'] > 0): ?>
                <div class="price-row">
                    <span>Discount<?= $booking['coupon_code'] ? ' (' . htmlspecialchars($booking['coupon_code']) . ')' : '' ?></span>
                    <span>- ₹<?= number_format($booking['discount_amount'], 2) ?></span>
                </div>
                <?php endif; ?>
                <div class="price-row total">
                    <span><?= htmlspecialchars($amount_label) ?></span>
                    <span>₹<?= number_format($booking['final_amount'], 2) ?></span>
                </div>
            </div>

            <!-- QR Code -->
            <div class="qr-section">
                <div class="qr-box"><img src="<?= $qr_data_uri ?>" alt="Ticket verification QR code"></div>
                <p>Scan this QR code at the venue entrance to verify your ticket</p>
            </div>
        </div>

        <div class="ticket-footer">
            <p>Present this ticket (printed or on your phone) at the venue entrance. Each ticket is valid for one person.</p>
            <p>&copy; <?= date('Y') ?> Evento. All rights reserved.</p>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>

</body>
</html>
<?php
$ticket_html = ob_get_clean();
$ticket_html = preg_replace('/<button class="print-btn".*?<\/button>/s', '', $ticket_html);
$ticket_html = preg_replace('/<script src=".*?qrcode\.min\.js"><\/script>/s', '', $ticket_html);
$ticket_html = preg_replace('/<script>\s*new QRCode\(.*?<\/script>/s', '', $ticket_html);

$options = new Dompdf\Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf\Dompdf($options);
$dompdf->loadHtml($ticket_html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('Evento-Ticket-' . $booking['booking_id'] . '.pdf', ['Attachment' => true]);
exit();
?>
