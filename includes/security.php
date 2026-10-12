<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Nerasky security bootstrap
|--------------------------------------------------------------------------
| Central, dependency-free hardening. Loaded from includes/auth.php so every
| rendered page gets the same protections: HTTP security headers, hardened
| session settings, a DB-backed rate limiter, a bot honeypot helper, and
| tightened CSRF tokens.
|
| No external APIs, no frameworks — everything below is plain PHP + MySQL.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config.php';

/* ------------------------------------------------------------------ */
/* HTTP security headers                                               */
/* ------------------------------------------------------------------ */

/**
 * Emit the security-related HTTP response headers the app should send on
 * every request. `$authenticated` toggles cache headers: authed pages are
 * told not to be stored by proxies/browsers.
 */
function security_headers(bool $authenticated = false): void
{
    if (headers_sent()) {
        return;
    }

    // Clickjacking protection (modern + legacy).
    header('X-Frame-Options: SAMEORIGIN', true);
    header("Content-Security-Policy: frame-ancestors 'self'", true);

    // Prevent browsers from MIME-sniffing away from the declared type.
    header('X-Content-Type-Options: nosniff', true);

    // Limit what is sent in the Referer header on cross-origin links.
    header('Referrer-Policy: strict-origin-when-cross-origin', true);

    // Site isolation hardening.
    header('Cross-Origin-Opener-Policy: same-origin', true);
    header('Cross-Origin-Resource-Policy: same-origin', true);

    // Keep ambient browser features we don't use switched off.
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()', true);

    // Authed pages should not be cached or stored.
    if ($authenticated) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        header('Pragma: no-cache', true);
        header('Expires: 0', true);
    }
}

/* ------------------------------------------------------------------ */
/* Session hardening                                                   */
/* ------------------------------------------------------------------ */

/** Absolute max lifetime of a logged-in session (seconds). */
const SESSION_ABS_TIMEOUT = 8 * 3600;   // 8 hours
/** Idle timeout after which a user is logged out (seconds). */
const SESSION_IDLE_TIMEOUT = 30 * 60;   // 30 minutes

/**
 * Tighten session cookie flags. Call before session_start().
 */
function harden_session_cookie(): void
{
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,                                   // in-memory; destroyed on close
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict',                             // tighter than Lax
    ]);
}

/**
 * Enforce idle + absolute session timeouts and bind the session to a simple
 * browser fingerprint so a stolen cookie can't be replayed from elsewhere.
 */
function enforce_session_limits(): void
{
    $now = time();

    // Absolute timeout.
    if (isset($_SESSION['login_at']) && ($now - (int) $_SESSION['login_at']) > SESSION_ABS_TIMEOUT) {
        logout_user();
        return;
    }

    // Idle timeout.
    if (isset($_SESSION['last_active']) && ($now - (int) $_SESSION['last_active']) > SESSION_IDLE_TIMEOUT) {
        logout_user();
        return;
    }

    // Bind to a lightweight fingerprint (UA + IP) to reduce replay risk.
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $fp = hash('sha256', $ua . '|' . $ip);
    if (isset($_SESSION['fp']) && !hash_equals($_SESSION['fp'], $fp)) {
        logout_user();
        return;
    }

    // Remember + refresh activity timestamps.
    $_SESSION['last_active'] = $now;
    if (!isset($_SESSION['fp'])) {
        $_SESSION['fp'] = $fp;
    }
}

/** Stamp session timestamps only after a successful login. */
function stamp_login(): void
{
    $_SESSION['login_at']   = time();
    $_SESSION['last_active'] = time();
}

/* ------------------------------------------------------------------ */
/* Rate limiting (brute-force protection)                              */
/* ------------------------------------------------------------------ */

/**
 * DB-backed throttle. Returns true (and records the attempt) if the caller
 * is within limits, or false if they've tripped the limit and must wait.
 *
 * Uses a small `security_throttle` table created by the installer/upgrader.
 */
function throttle_allows(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $keyHash = hash('sha256', $key);

    // Housekeeping: drop stale windows so the table never grows unbounded.
    q('DELETE FROM security_throttle WHERE window_expires < ?', [time()]);

    $row = row('SELECT attempts, window_expires FROM security_throttle WHERE key_hash = ?', [$keyHash]);
    $now = time();

    if ($row) {
        if ((int) $row['window_expires'] < $now) {
            // Window over — start a fresh one.
            q('UPDATE security_throttle SET attempts = 1, window_expires = ? WHERE key_hash = ?', [$now + $windowSeconds, $keyHash]);
            return true;
        }
        if ((int) $row['attempts'] >= $maxAttempts) {
            return false; // locked out for now
        }
        q('UPDATE security_throttle SET attempts = attempts + 1 WHERE key_hash = ?', [$keyHash]);
        return true;
    }

    insert(
        'INSERT INTO security_throttle (key_hash, attempts, window_expires) VALUES (?,1,?)',
        [$keyHash, $now + $windowSeconds]
    );
    return true;
}

/** Seconds remaining until the throttle for a key resets (or 0). */
function throttle_retry_after(string $key): int
{
    $row = row('SELECT window_expires FROM security_throttle WHERE key_hash = ?', [hash('sha256', $key)]);
    if (!$row) {
        return 0;
    }
    return max(0, (int) $row['window_expires'] - time());
}

/* ------------------------------------------------------------------ */
/* CSRF strengthening + bot honeypot                                   */
/* ------------------------------------------------------------------ */

/**
 * Double-submit CSRF: besides the session token, drop a matching cookie tied
 * to the session so POSTs must carry a token matching BOTH. This makes CSRF
 * attacks that lack the cookie fail even if a form token leaks.
 *
 * HttpOnly is fine here — this cookie only needs to travel back automatically;
 * it never needs to be read by JavaScript.
 */
function csrf_cookie_touch(): void
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    if (!headers_sent()) {
        setcookie(
            'NERASKY_CSRF',
            $_SESSION['csrf'],
            [
                'expires'  => 0,
                'path'     => '/',
                'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );
    }
}

/** Render a hidden honey-pot field (kept visually empty for humans). */
function honeypot_field(): string
{
    return '<div aria-hidden="true" class="hp-field" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">'
        . '<label for="company_website">Leave this blank</label>'
        . '<input type="text" id="company_website" name="company_website" tabindex="-1" autocomplete="off">'
        . '</div>';
}

/** True when a bot has filled the honeypot field. */
function honeypot_tripped(): bool
{
    return trim((string) ($_POST['company_website'] ?? '')) !== '';
}

/**
 * Strengthened CSRF check. Verifies the session token AND the double-submit
 * cookie token, comparing in constant time.
 */
function verify_csrf_strict(): void
{
    $posted = (string) ($_POST['csrf'] ?? '');
    $sessionTok = csrf_token();
    $cookieTok  = (string) ($_COOKIE['NERASKY_CSRF'] ?? '');

    if ($posted === '' || $cookieTok === ''
        || !hash_equals($sessionTok, $posted)
        || !hash_equals($sessionTok, $cookieTok)) {
        http_response_code(419);
        exit('Security check failed — your session token was rejected. Please refresh and try again.');
    }
}

