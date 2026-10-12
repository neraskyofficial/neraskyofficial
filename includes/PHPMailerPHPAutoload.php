<?php
declare(strict_types=1);

/**
 * Minimal PSR-4 autoloader for the vendored PHPMailer package.
 * (Composer is not required — the three source files live in
 * includes/phpmailer/.)
 */

spl_autoload_register(static function (string $class): void {
    // Only classes in the PHPMailer\PHPMailer namespace.
    if (strncmp($class, 'PHPMailer\\PHPMailer\\', 20) !== 0) {
        return;
    }
    $file = __DIR__ . '/phpmailer/' . str_replace('\\', '/', substr($class, 20)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});