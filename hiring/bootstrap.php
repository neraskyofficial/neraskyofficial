<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_name('NERASKY_HIRING_SESSION');
    session_start();
}

require_once MARKET_APP_ROOT . '/includes/db.php';
require_once MARKET_APP_ROOT . '/includes/functions.php';
