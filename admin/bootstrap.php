<?php
declare(strict_types=1);

/*
| Nerasky Admin bootstrap.
| Starts an ADMIN-ONLY session (separate cookie from the marketplace) and
| loads the shared database/helper stack. No marketplace auth is loaded —
| the marketplace's `require_user` / session never apply here.
*/

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path'     => '/',
    ]);
    session_name('NERASKY_ADMIN_SESSION');
    session_start();
}

define('ADMIN_SESSION_IDLE_SECONDS', 1800);
if (!empty($_SESSION['admin_last_activity']) && time() - (int) $_SESSION['admin_last_activity'] > ADMIN_SESSION_IDLE_SECONDS) {
    $_SESSION = [];
    session_regenerate_id(true);
}
if (isset($_SESSION['admin_uid'])) {
    $_SESSION['admin_last_activity'] = time();
}

require_once MARKET_APP_ROOT . '/includes/security.php';
csrf_cookie_touch();

require_once MARKET_APP_ROOT . '/includes/db.php';
require_once MARKET_APP_ROOT . '/includes/functions.php';

/* ------------------------------------------------------------------ */
/* Admin-only authentication (kept separate from marketplace auth)     */
/* ------------------------------------------------------------------ */

function admin_user(): ?array
{
    if (!empty($_SESSION['admin_2fa_pending'])) {
        return null;
    }
    $id = $_SESSION['admin_uid'] ?? null;
    if (!$id) {
        return null;
    }
    $u = row(
        "SELECT u.*, s.staff_role AS assigned_admin_role, s.active AS staff_active
         FROM users u
         LEFT JOIN admin_staff s ON s.user_id = u.id
         WHERE u.id = ?",
        [$id]
    );
    if (!$u || $u['role'] !== 'admin') {
        unset($_SESSION['admin_uid']);
        return null;
    }
    if (strcasecmp((string) $u['email'], PRIMARY_ADMIN_EMAIL) === 0) {
        $u['admin_role'] = 'super_admin';
    } elseif ((int) ($u['staff_active'] ?? 0) === 1 && in_array((string) ($u['assigned_admin_role'] ?? ''), ['moderator', 'finance_admin', 'super_admin'], true)) {
        $u['admin_role'] = $u['assigned_admin_role'];
    } else {
        unset($_SESSION['admin_uid']);
        return null;
    }
    unset($u['password_hash']);
    return $u;
}

function admin_role(array $admin): string
{
    return in_array(($role = (string) ($admin['admin_role'] ?? 'super_admin')), ['moderator', 'finance_admin', 'super_admin'], true)
        ? $role
        : 'super_admin';
}

function admin_role_label(string $role): string
{
    return [
        'moderator' => 'Moderator',
        'finance_admin' => 'Finance admin',
        'super_admin' => 'Super admin',
    ][$role] ?? 'Super admin';
}

function admin_can(array $admin, string $area): bool
{
    $role = admin_role($admin);
    if ($role === 'super_admin') {
        return true;
    }
    // Moderators can access moderation areas; Finance admins cannot.
    if ($role === 'finance_admin') {
        return in_array($area, ['overview', 'payments', 'cash_ins', 'withdrawals', 'orders', 'reports', 'tasks', 'support'], true);
    }
    // Moderators can access moderation areas; Finance admins cannot.
    return in_array($area, ['overview', 'users', 'services', 'payments', 'orders', 'reports', 'tasks', 'support'], true);
}

function require_admin_area(array $admin, string $area): void
{
    if (!admin_can($admin, $area)) {
        http_response_code(403);
        exit('<!doctype html><meta charset="utf-8"><title>Access denied</title><p style="font-family: sans-serif; padding: 2rem">You do not have permission to open this admin panel.</p>');
    }
}

function require_admin(): array
{
    $u = admin_user();
    if (!$u) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
    return $u;
}

function admin_attempt_login(string $email, string $password): bool
{
    $now = time();
    $attempts = $_SESSION['admin_login_attempts'] ?? [];
    $_SESSION['admin_login_attempts'] = array_values(array_filter(
        is_array($attempts) ? $attempts : [],
        static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - 900
    ));
    if (count($_SESSION['admin_login_attempts']) >= 5) {
        return false;
    }
    $u = row('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
    if (!$u || $u['role'] !== 'admin') {
        $_SESSION['admin_login_attempts'][] = $now;
        return false;
    }
    if (!password_verify($password, $u['password_hash'])) {
        $_SESSION['admin_login_attempts'][] = $now;
        return false;
    }
    session_regenerate_id(true);
    $twoFactor = row('SELECT enabled FROM admin_2fa WHERE user_id = ?', [(int) $u['id']]);
    if ($twoFactor && (int) $twoFactor['enabled'] === 1) {
        $_SESSION['admin_2fa_pending'] = (int) $u['id'];
    } else {
        $_SESSION['admin_uid'] = (int) $u['id'];
    }
    $_SESSION['admin_last_activity'] = $now;
    unset($_SESSION['admin_login_attempts']);
    return true;
}

function admin_2fa_pending_user(): ?array
{
    $id = (int) ($_SESSION['admin_2fa_pending'] ?? 0);
    return $id > 0 ? row("SELECT * FROM users WHERE id = ? AND role = 'admin'", [$id]) : null;
}

function admin_2fa_secret(int $userId): ?string
{
    $row = row('SELECT secret FROM admin_2fa WHERE user_id = ?', [$userId]);
    return $row ? (string) $row['secret'] : null;
}

function admin_base32_decode(string $secret): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(trim($secret));
    $bits = '';
    foreach (str_split($secret) as $char) {
        $value = strpos($alphabet, $char);
        if ($value === false) {
            continue;
        }
        $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
    }
    $binary = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $binary .= chr(bindec($byte));
        }
    }
    return $binary;
}

function admin_totp(string $secret, ?int $time = null): string
{
    $counter = intdiv($time ?? time(), 30);
    $payload = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $payload, admin_base32_decode($secret), true);
    $offset = ord($hash[19]) & 0x0f;
    $code = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string) ($code % 1000000), 6, '0', STR_PAD_LEFT);
}

function admin_totp_valid(string $secret, string $code): bool
{
    $code = preg_replace('/\D+/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return false;
    }

    function admin_recovery_valid(int $userId, string $code): bool
    {
        $row = row('SELECT recovery_codes FROM admin_2fa WHERE user_id = ? AND enabled = 1', [$userId]);
        $codes = $row && $row['recovery_codes'] ? json_decode((string) $row['recovery_codes'], true) : [];
        if (!is_array($codes)) {
            return false;
        }
        foreach ($codes as $index => $hash) {
            if (is_string($hash) && password_verify(trim($code), $hash)) {
                unset($codes[$index]);
                q('UPDATE admin_2fa SET recovery_codes = ? WHERE user_id = ?', [json_encode(array_values($codes)), $userId]);
                return true;
            }
        }
        return false;
    }
    $time = time();
    foreach ([-30, 0, 30] as $drift) {
        if (hash_equals(admin_totp($secret, $time + $drift), $code)) {
            return true;
        }
    }
    return false;
}

function admin_base32_secret(int $bytes = 20): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(random_bytes($bytes)) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }
    $secret = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) === 5) {
            $secret .= $alphabet[bindec($chunk)];
        }
    }
    return $secret;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}