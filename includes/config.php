<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Nerasky configuration
|--------------------------------------------------------------------------
| Combined marketplace, landing page, and Hiring configuration for hosting.
| Replace the database and callback placeholders before running install.php.
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'Nerasky');
define('APP_TAGLINE', 'The student micro-service marketplace — built by students, for students.');

// Platform commission (percent) kept by Nerasky on every completed order.
define('PLATFORM_FEE', 5);

// Nerasky Pro subscription (simulated billing — paid from the wallet balance).
define('PREMIUM_PRICE', 49.00);   // pesos per recharge period
define('PREMIUM_DAYS', 30);       // days added per recharge
define('PREMIUM_FEE', 3);         // discounted platform fee (percent) for active Pro sellers
define('PREMIUM_DAILY_DISCOUNTED_ORDERS', 3); // discounted Pro orders per seller per day

// Messaging safeguards: generous daily limits keep the inbox useful and
// protect the self-hosted app from spam and runaway storage.
define('FREE_DAILY_MESSAGES', 30);
define('PREMIUM_DAILY_MESSAGES', 100);
define('FREE_MESSAGE_MAX_CHARS', 1000);
define('PREMIUM_MESSAGE_MAX_CHARS', 1600);
define('FREE_NOTE_REPLIES', 3);

// Replace these with the MySQL values shown in the InfinityFree control panel.
define('DB_HOST', 'sql300.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_43066221_DB_HOST');
define('DB_USER', 'if0_43066221');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Uploads
define('MAX_UPLOAD_MB', 6);             // max file for proofs / consent / deliverables
define('MAX_PAYMENT_SCREENSHOT_MB', 8); // payment screenshots may be a little larger
define('MAX_SERVICE_IMAGES', 5);

/* ---------------------------------------------------------------- */
/* Supabase Auth (email verification + Google Sign-In)               */
/*                                                                  */
/* Create a Supabase project, then paste its Project URL and          */
/* publishable (anon) key below. Never put the service_role key here. */
/*                                                                  */
/* Add the callback URL below to Supabase Auth's redirect URL allow   */
/* list. Update it when the app's local port or production domain     */
/* changes.                                                          */
/* ---------------------------------------------------------------- */
define('SUPABASE_URL', 'https://qzsytxksbvzqflbuwrsw.supabase.co'); // e.g. https://your-project.supabase.co
define('SUPABASE_ANON_KEY', 'sb_publishable_nMlT-l2jA_Al0O2ds_GDWA_1pMbrfeC'); // publishable / anon key only
define('SUPABASE_REDIRECT_URL', 'https://nerasky.wuaze.com/supabase_callback.php');
define('SUPABASE_JS_VERSION', '2.57.4');

// Security alert email delivery (optional; alerts are logged when unconfigured).
define('SECURITY_MAIL_HOST', '');
define('SECURITY_MAIL_PORT', 587);
define('SECURITY_MAIL_ENCRYPTION', 'tls'); // tls, ssl, or ''
define('SECURITY_MAIL_USERNAME', '');
define('SECURITY_MAIL_PASSWORD', '');
define('SECURITY_MAIL_FROM', '');
define('SECURITY_MAIL_FROM_NAME', APP_NAME . ' Security');

// Service categories available on the marketplace
define('CATEGORIES', [
    'Tutoring & Academics',
    'Editing & Proofreading',
    'Coding & Web',
    'Design & Creative',
    'Writing & Translation',
    'Presentations & Documents',
    'Audio & Video',
    'Study Groups & Other',
]);

// Seller withdrawals and the optional manual GCash payment route.
define('PAYMENT_METHODS', ['GCash']);
define('PAYMENT_ACCOUNT_DETAILS', [
    'GCash' => [
        'name' => '',
        'number' => '',
        'qr' => 'assets/gcash-payment-qr.jpg',
    ],
]);
define('ENABLE_EXTERNAL_PAYMENTS', trim(PAYMENT_ACCOUNT_DETAILS['GCash']['name']) !== ''
    && trim(PAYMENT_ACCOUNT_DETAILS['GCash']['number']) !== ''
    && is_file(dirname(__DIR__) . '/' . PAYMENT_ACCOUNT_DETAILS['GCash']['qr']));

// Configure separate test and live credentials from the private PayMongo
// dashboard. Never place live keys in frontend code or source control.
define('PAYMONGO_SECRET_KEY', '');
define('PAYMONGO_PUBLIC_KEY', '');
define('PAYMONGO_WEBHOOK_SECRET', '');
define('PAYMONGO_API_BASE_URL', 'https://api.paymongo.com/v1');
define('PAYMONGO_ENABLED', PAYMONGO_SECRET_KEY !== ''
    && PAYMONGO_PUBLIC_KEY !== ''
    && PAYMONGO_WEBHOOK_SECRET !== ''
    && preg_match('/^sk_(test|live)_/', PAYMONGO_SECRET_KEY) === 1
    && preg_match('/^pk_(test|live)_/', PAYMONGO_PUBLIC_KEY) === 1
    && str_starts_with(PAYMONGO_PUBLIC_KEY, 'pk_' . (str_starts_with(PAYMONGO_SECRET_KEY, 'sk_live_') ? 'live_' : 'test_')));

// Paths
define('APP_ROOT', dirname(__DIR__));

$docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
$appPath = str_replace('\\', '/', APP_ROOT);
if ($docRoot !== '' && stripos($appPath, $docRoot) === 0) {
    $baseUrl = rtrim(substr($appPath, strlen($docRoot)), '/');
} else {
    $baseUrl = '';
}

if (preg_match('~^/admin(?:/|$)~', (string) parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH)) === 1) {
    $baseUrl .= '/admin';
}
define('BASE_URL', $baseUrl);

define('HIRING_SITE_URL', BASE_URL . '/hiring/careers.php');
define('ADMIN_SITE_URL', BASE_URL === '/admin' ? '/admin/' : BASE_URL . '/admin/');
define('ADMIN_SETUP_TOKEN', 'REPLACE_WITH_A_PRIVATE_RANDOM_TOKEN');