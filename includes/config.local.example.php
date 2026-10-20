<?php
/**
 * Copy this file to config.local.php on the hosting server and enter that
 * host's settings there. Never commit config.local.php or share its secrets.
 */

$settings = [
    'APP_ENV' => 'production',
    'SITE_URL' => '',
    'DB_HOST' => '',
    'DB_USER' => '',
    'DB_PASS' => '',
    'DB_NAME' => '',
    'SMTP_HOST' => '',
    'SMTP_PORT' => '587',
    'SMTP_USERNAME' => '',
    'SMTP_PASSWORD' => '',
    'SMTP_ENCRYPTION' => 'tls',
    'SMTP_FROM' => '',
    'SMTP_FROM_NAME' => 'Evento',
    'GOOGLE_CLIENT_ID' => '',
    'GOOGLE_CLIENT_SECRET' => '',
    'RAZORPAY_KEY_ID' => '',
    'RAZORPAY_KEY_SECRET' => '',
];

foreach ($settings as $name => $value) {
    if ($value !== '') {
        putenv($name . '=' . $value);
    }
}

unset($settings, $name, $value, $localConfig);
