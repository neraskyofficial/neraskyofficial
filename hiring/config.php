<?php
declare(strict_types=1);

define('HIRING_CONTACT_EMAIL', 'nerasky.careers@gmail.com');
define(
    'MARKET_APP_ROOT',
    is_file(dirname(__DIR__) . '/includes/config.php')
        ? dirname(__DIR__)
        : dirname(__DIR__) . '/nerasky'
);

require_once MARKET_APP_ROOT . '/includes/config.php';

function hiring_url(string $path = ''): string
{
    return base_url('hiring/' . ltrim($path, '/'));
}

function market_url(string $path = ''): string
{
    return base_url($path);
}
