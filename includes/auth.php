<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/security_notifications.php';

// Sessions: hardened (HttpOnly, SameSite=Strict, Secure when HTTPS).
// Offline-friendly: Secure is intentionally CSS-dependent so local XAMPP
// (http) still works.
if (session_status() === PHP_SESSION_NONE) {
    harden_session_cookie();
    session_name('NERASKY_SESSION');
    session_start();
}

// Session key hygiene: regeneration bounds + idle/absolute timeouts + a
// lightweight fingerprint bound so a stolen cookie can't be replayed freely.
if (isset($_SESSION['uid'])) {
    enforce_session_limits();
}

// Send security headers on every response and make sure the double-submit
// CSRF cookie exists before any form renders.
security_headers((bool) ($_SESSION['uid'] ?? false));
csrf_cookie_touch();

/**
 * Returns the currently logged-in user row, or null.
 */
function get_user(): ?array
{
    $id = $_SESSION['uid'] ?? null;
    if (!$id) {
        return null;
    }
    $user = row('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$user) {
        unset($_SESSION['uid']);
        return null;
    }
    $premiumUntil = $user['premium_until'] ?? null;
    if ($premiumUntil && strtotime((string) $premiumUntil) <= time()) {
        q(
            "UPDATE subscriptions SET status = 'expired'
             WHERE user_id = ? AND status = 'active' AND ends_at <= NOW()",
            [(int) $id]
        );
        $expirationUpdate = q(
            'UPDATE users SET premium_until = NULL WHERE id = ? AND premium_until = ? AND premium_until <= NOW()',
            [(int) $id, $premiumUntil]
        );
        if ($expirationUpdate->rowCount() > 0) {
            $user['premium_until'] = null;
        } else {
            $freshPremiumUntil = row('SELECT premium_until FROM users WHERE id = ?', [(int) $id]);
            $user['premium_until'] = $freshPremiumUntil['premium_until'] ?? null;
        }
    }
    unset($user['password_hash']);
    return $user;
}

/** Refresh the cached user row inside helpers (after balance/status changes). */
function refresh_user(): void
{
    // get_user re-loads from DB every call, so this is a no-op placeholder
    // kept for readability when a fresh snapshot is desired.
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Require any logged-in user; redirects to login otherwise. */
function require_user(): array
{
    $u = get_user();
    if (!$u) {
        flash('info', 'Please log in to continue.');
        redirect('login.php');
    }
    return $u;
}

/** Require a logged-in user whose account has been manually verified by staff. */
function require_verified_user(): array
{
    $u = require_user();
    if ($u['account_status'] !== 'verified') {
        flash('warn', 'Your account is still under review. You can browse and message, but selling and buying unlock once an admin verifies your account.');
        redirect('dashboard.php');
    }
    return $u;
}

/** Require an administrator. */
function require_admin(): array
{
    $u = require_user();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('<!doctype html><meta charset="utf-8"><title>403</title><body style="font-family:system-ui;background:#0B1020;color:#E7EAF3;display:grid;place-items:center;min-height:90vh"><h1>403 · Access denied</h1></body>');
    }
    return $u;
}

/* ------------------------------------------------------------------ */
/* Anonymous visitor privacy                                          */
/* Sellers' names stay hidden until someone logs in.                   */
/* ------------------------------------------------------------------ */

/** True when the visitor is not logged in (names should be hidden). */
function hide_names(): bool
{
    return get_user() === null;
}

/** Display name for a row that may belong to another member. */
function member_name(array $row, string $key = 'full_name'): string
{
    return hide_names() ? 'Hidden member' : (string) ($row[$key] ?? '');
}

/** Avatar for a member, kept anonymous for logged-out visitors. */
function member_avatar(?string $name, int $size = 40, ?string $file = null): string
{
    return hide_names() ? avatar_html('', $size) : avatar_html($name, $size, $file);
}

/**
 * Frame-aware, privacy-aware avatar for a full user row.
 * Falls back to a plain avatar for guests (privacy) or frame "none".
 * Photos resolve via avatar_file_for() so feeding queries stay untouched.
 *
 * Person-id resolution: joined rows (services, reviews, order
 * counterparties) carry their own row `id`, which is NOT the member's
 * id. The member id is taken from explicit person keys first; only
 * genuine user rows (email + account_status present) may fall back to
 * their own `id`. Rows with no resolvable person never trigger a
 * lookup, so a wrong photo is impossible.
 */
function avatar_of(array $u, int $size = 40): string
{
    $uid = null;
    foreach (['user_id', 'seller_id', 'buyer_id', 'reviewer_id'] as $k) {
        if (isset($u[$k]) && (int) $u[$k] > 0) {
            $uid = (int) $u[$k];
            break;
        }
    }
    if ($uid === null && isset($u['email'], $u['account_status'])) {
        $uid = (int) ($u['id'] ?? 0);
    }
    $file  = $u['avatar_file'] ?? ($uid !== null ? avatar_file_for($uid) : null);
    $inner = member_avatar($u['full_name'] ?? '', $size, $file);
    $frame = (string) ($u['profile_frame'] ?? 'none');
    if ($frame === 'none' || $frame === '' || hide_names()) {
        return $inner;
    }
    $hex = profile_accent_hex((string) ($u['profile_accent'] ?? 'indigo'));
    return '<span class="avwrap avf-' . e($frame) . '" style="--avc:' . $hex . ';--avs:' . $size . 'px">' . $inner . '</span>';
}