<?php
declare(strict_types=1);

define('ADMIN_APP_ROOT', __DIR__);
// In the deployment package Admin is /admin and the marketplace root is its parent.
$combinedRoot = dirname(__DIR__);
define(
    'MARKET_APP_ROOT',
    is_file($combinedRoot . '/includes/config.php')
        ? $combinedRoot
        : $combinedRoot . '/nerasky'
);
require_once MARKET_APP_ROOT . '/includes/config.php';

define('PRIMARY_ADMIN_EMAIL', 'admin@nerasky.ph');
$requestHost = (string) ($_SERVER['HTTP_HOST'] ?? 'nerasky.wuaze.com');
if (preg_match('/\A[a-zA-Z0-9.-]+(?::[0-9]{1,5})?\z/D', $requestHost) !== 1) {
    $requestHost = 'nerasky.wuaze.com';
}
$requestScheme = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
$requestOrigin = $requestScheme . '://' . $requestHost;
define('ADMIN_BASE_URL', $requestOrigin . '/admin');
define('MARKET_BASE_URL', $requestOrigin);

function market_url(string $path = ''): string
{
    return MARKET_BASE_URL . '/' . ltrim($path, '/');
}

function market_asset_url(string $path): string
{
    return market_url(ltrim($path, '/'));
}

/** Stream a sensitive marketplace upload through the authenticated admin app. */
function admin_document_url(string $path): string
{
    return base_url('document.php?path=' . rawurlencode(ltrim($path, '/')));
}