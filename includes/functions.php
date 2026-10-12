<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* ------------------------------------------------------------------ */
/* URLs & routing                                                      */
/* ------------------------------------------------------------------ */

/** Build an absolute app URL from a root-relative path. */
function base_url(string $path = ''): string
{
    $root = BASE_URL;
    $p = ltrim($path, '/');
    return $root === '' ? '/' . $p : $root . '/' . $p;
}

/** Redirect to an app page (handles BASE_URL). */
function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

/** Keep post-auth continuation links inside the Nerasky app. */
function safe_next(string $next, string $fallback = 'dashboard.php'): string
{
    $next = trim($next);
    if ($next !== '' && preg_match('/^[A-Za-z0-9_-]+\.php(?:\?[^#]*)?(?:#[A-Za-z0-9_-]+)?$/', $next) === 1) {
        return $next;
    }
    return $fallback;
}

/** Escape output for HTML. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function payment_account_details(string $provider): array
{
    $details = PAYMENT_ACCOUNT_DETAILS[$provider] ?? [];
    return [
        'name' => (string) ($details['name'] ?? ''),
        'number' => (string) ($details['number'] ?? ''),
        'qr' => (string) ($details['qr'] ?? ''),
    ];
}

/* ------------------------------------------------------------------ */
/* Flash messages & CSRF                                               */
/* ------------------------------------------------------------------ */

function flash(string $kind, string $msg): void
{
    $_SESSION['flash'][] = ['kind' => $kind, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $t = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Expired or invalid session — please go back and try again.');
    }
    // Double-submit layer: the NERASKY_CSRF cookie (set by security.php) must
    // also match, so a stolen form token alone can't be abused cross-site.
    $cookieTok = (string) ($_COOKIE['NERASKY_CSRF'] ?? '');
    if ($cookieTok === '' || !hash_equals(csrf_token(), $cookieTok)) {
        http_response_code(419);
        exit('Expired or invalid session — please go back and try again.');
    }
}

/* ------------------------------------------------------------------ */
/* Money & fees                                                        */
/* ------------------------------------------------------------------ */

/** Is this user an active Nerasky Pro member? */
function is_premium(?array $u): bool
{
    if (!$u) {
        return false;
    }
    $until = $u['premium_until'] ?? null;
    if (!$until) {
        return false;
    }
    return strtotime((string) $until) > time();
}

/** Number of Pro-discounted orders already created by this seller today. */
function premium_orders_today(?array $seller = null): int
{
    if (!$seller || !isset($seller['id']) || !is_premium($seller)) {
        return 0;
    }
    return (int) (scalar(
        'SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status <> "cancelled" AND created_at >= CURDATE()',
        [(int) $seller['id']]
    ) ?? 0);
}

/** Platform fee percent that applies to an order for the given seller. */
function effective_fee_rate(?array $seller = null): float
{
    if (!is_premium($seller)) {
        return (float) PLATFORM_FEE;
    }
    return premium_orders_today($seller) < PREMIUM_DAILY_DISCOUNTED_ORDERS
        ? (float) PREMIUM_FEE
        : (float) PLATFORM_FEE;
}

/** Platform fee for an order (first 3 Pro orders today use 3%, then 5%). */
function fee_for(float $price, ?array $seller = null): float
{
    return round($price * effective_fee_rate($seller) / 100, 2);
}

/** Seller keeps everything minus the platform fee. */
function payout_for(float $price, ?array $seller = null): float
{
    return round($price - fee_for($price, $seller), 2);
}

/** Normalise a user-entered price string. */
function clean_price(string $raw): float
{
    $n = str_replace([',', ' '], '', trim($raw));
    $f = (float) $n;
    return max(0, round($f, 2));
}

/** Full peso amount, e.g. ₱1,250.00 */
function money(float $n): string
{
    return '₱' . number_format($n, 2);
}

/** Compact peso amount, no decimals when whole. */
function price(float $n): string
{
    return '₱' . number_format($n, $n === (float) floor($n) ? 0 : 2);
}

/** Keep exact listing prices available to members, not public catalogue crawlers. */
function marketplace_price(float $amount, ?array $user): string
{
    return $user ? price($amount) : 'Estimated price';
}

/**
 * Move money through the internal wallet and record an auditable ledger entry.
 * This is deliberately separate from external payment settlement.
 */
function wallet_credit(int $userId, float $amount, string $description, ?int $orderId = null, string $type = 'credit'): int
{
    $amount = round($amount, 2);
    if ($userId <= 0 || $amount <= 0) {
        throw new InvalidArgumentException('Wallet credits must be positive.');
    }
    $db = db();
    $started = !$db->inTransaction();
    if ($started) {
        $db->beginTransaction();
    }
    try {
        q('UPDATE users SET wallet = wallet + ? WHERE id = ?', [$amount, $userId]);
        $ledgerId = insert(
            'INSERT INTO wallet_transactions (user_id, type, amount, direction, description, order_id)
             VALUES (?,?,?,?,?,?)',
            [$userId, $type, $amount, 'credit', $description, $orderId]
        );
        if ($started) {
            $db->commit();
        }
        return $ledgerId;
    } catch (Throwable $e) {
        if ($started && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function wallet_debit(int $userId, float $amount, string $description, ?int $orderId = null, string $type = 'debit'): bool
{
    $amount = round($amount, 2);
    if ($userId <= 0 || $amount <= 0) {
        throw new InvalidArgumentException('Wallet debits must be positive.');
    }
    $db = db();
    $started = !$db->inTransaction();
    if ($started) {
        $db->beginTransaction();
    }
    try {
        $balance = (float) (row('SELECT wallet FROM users WHERE id = ? FOR UPDATE', [$userId])['wallet'] ?? 0);
        if ($balance < $amount) {
            if ($started) {
                $db->rollBack();
            }
            return false;
        }
        q('UPDATE users SET wallet = wallet - ? WHERE id = ?', [$amount, $userId]);
        insert(
            'INSERT INTO wallet_transactions (user_id, type, amount, direction, description, order_id)
             VALUES (?,?,?,?,?,?)',
            [$userId, $type, $amount, 'debit', $description, $orderId]
        );
        if ($started) {
            $db->commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($started && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

/* ------------------------------------------------------------------ */
/* Time                                                                */
/* ------------------------------------------------------------------ */

function time_ago(string $datetime): string
{
    $ts   = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 45) {
        return 'just now';
    }
    if ($diff < 86400 && date('Ymd') === date('Ymd', $ts)) {
        return 'today at ' . date('g:i A', $ts);
    }
    $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $sec => $label) {
        if ($diff >= $sec) {
            $n = (int) floor($diff / $sec);
            return $n . ' ' . $label . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return date('M j, Y', $ts);
}
/* ------------------------------------------------------------------ */
/* Rendering helpers: avatars, badges, chips                           */
/* ------------------------------------------------------------------ */

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

/** Public URL for a stored profile photo (admin app serves a different docroot). */
function avatar_photo_url(string $file): string
{
    $path = 'uploads/avatars/' . $file;
    return function_exists('market_url') ? market_url($path) : base_url($path);
}

/** Per-request memoised avatar_file lookup — call sites' queries stay untouched. */
function avatar_file_for(int $userId): ?string
{
    static $memo = [];
    if ($userId <= 0) {
        return null;
    }
    if (!array_key_exists($userId, $memo)) {
        $r = row('SELECT avatar_file FROM users WHERE id = ?', [$userId]);
        $memo[$userId] = ($r['avatar_file'] ?? null) ?: null;
    }
    return $memo[$userId];
}

function avatar_html(?string $name, int $size = 40, ?string $file = null): string
{
    if ($file !== null && $file !== '' && is_file(APP_ROOT . '/uploads/avatars/' . $file)) {
        $v = filemtime(APP_ROOT . '/uploads/avatars/' . $file);
        return '<img class="avatar avatar-photo" loading="lazy" width="' . $size . '" height="' . $size . '" src="'
            . e(avatar_photo_url($file) . '?v=' . $v) . '" alt="">';
    }
    $name  = trim((string) $name);
    $label = $name !== '' ? $name : '?';
    $palette = ['#6366F1', '#8B5CF6', '#D946EF', '#EC4899', '#F59E0B', '#10B981', '#0EA5E9', '#F43F5E'];
    $bg = $palette[abs(crc32($label)) % count($palette)];
    $fs = max(11, (int) floor($size * 0.38));
    return '<span class="avatar" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . $fs . 'px;background:' . $bg . '">'
        . e(initials($label)) . '</span>';
}

function status_label(string $status): string
{
    $map = [
        'awaiting_payment' => 'Awaiting payment',
        'in_progress'      => 'In progress',
        'delivered'        => 'Delivered',
        'completed'        => 'Completed',
        'cancelled'        => 'Cancelled',
        'pending'          => 'Pending review',
        'reviewing'        => 'Under review',
        'shortlisted'      => 'Shortlisted',
        'submitted'        => 'Consent submitted',
        'approved'         => 'Approved',
        'verified'         => 'Verified',
        'succeeded'        => 'Paid',
        'failed'           => 'Failed',
        'rejected'         => 'Rejected',
        'active'           => 'Active',
        'expired'          => 'Expired',
        'paid'             => 'Paid',
    ];
    return $map[$status] ?? str_replace('_', ' ', ucfirst($status));
}

/** Nerasky Pro badge (small pill with the ★ mark). */
function premium_badge(string $size = 'sm'): string
{
    return '<span class="pro-badge' . ($size === 'lg' ? ' lg' : '') . '" title="Nerasky Pro member">★ Pro</span>';
}

/** Inherit the premium "glow" modifier when the seller is Pro. */
function premium_card_class(?array $u): string
{
    return is_premium($u) ? ' pro-glow' : '';
}

/* ------------------------------------------------------------------ */
/* Profile customization — frames & accents                            */
/* ------------------------------------------------------------------ */

/** Avatar border frames. "premium" frames require a Nerasky Pro member. */
function profile_frames(): array
{
    return [
        'none'    => ['label' => 'None',         'premium' => false],
        'classic' => ['label' => 'Classic ring', 'premium' => false],
        'soft'    => ['label' => 'Soft glow',    'premium' => false],
        'aurora'  => ['label' => 'Aurora',       'premium' => true],
        'gold'    => ['label' => 'Gold',         'premium' => true],
        'neon'    => ['label' => 'Neon',         'premium' => true],
        'galaxy'  => ['label' => 'Galaxy',       'premium' => true],
    ];
}

/** Public profile layouts. Premium layouts are visual-only and do not affect reach. */
function profile_layouts(): array
{
    return [
        'classic'  => ['label' => 'Classic', 'description' => 'Balanced profile header', 'premium' => false],
        'spotlight'=> ['label' => 'Spotlight', 'description' => 'Bold accent hero', 'premium' => true],
        'centered' => ['label' => 'Centered', 'description' => 'Clean centered presentation', 'premium' => true],
    ];
}

/** Accent colour palette (free for everyone). */
function profile_accents(): array
{
    return [
        'indigo'  => '#6366F1',
        'violet'  => '#8B5CF6',
        'sky'     => '#0EA5E9',
        'emerald' => '#10B981',
        'amber'   => '#F59E0B',
        'rose'    => '#EC4899',
    ];
}

function profile_accent_hex(string $key): string
{
    $a = profile_accents();
    return $a[$key] ?? $a['indigo'];
}

function profile_note_themes(): array
{
    return [
        'indigo' => ['label' => 'Indigo', 'background' => '#eef2ff', 'border' => '#818cf8', 'text' => '#3730a3'],
        'sky' => ['label' => 'Sky', 'background' => '#ecfeff', 'border' => '#22d3ee', 'text' => '#155e75'],
        'emerald' => ['label' => 'Emerald', 'background' => '#ecfdf5', 'border' => '#34d399', 'text' => '#065f46'],
        'amber' => ['label' => 'Sunset', 'background' => '#fffbeb', 'border' => '#fbbf24', 'text' => '#92400e'],
        'rose' => ['label' => 'Rose', 'background' => '#fff1f2', 'border' => '#fb7185', 'text' => '#9f1239'],
        'violet' => ['label' => 'Violet', 'background' => '#f5f3ff', 'border' => '#a78bfa', 'text' => '#5b21b6'],
    ];
}

function profile_note_theme_key(array $user): string
{
    $key = (string) ($user['profile_note_theme'] ?? 'indigo');
    return array_key_exists($key, profile_note_themes()) && is_premium($user) ? $key : 'indigo';
}

function profile_note_theme_class(array $user): string
{
    return 'note-theme-' . preg_replace('/[^a-z0-9-]/', '', profile_note_theme_key($user));
}

function chat_themes(): array
{
    return [
        'indigo' => ['label' => 'Indigo', 'description' => 'Calm purple-blue', 'gradient' => 'linear-gradient(135deg,#6366f1,#8b5cf6)'],
        'ocean' => ['label' => 'Ocean', 'description' => 'Cool blue-cyan', 'gradient' => 'linear-gradient(135deg,#0284c7,#06b6d4)'],
        'emerald' => ['label' => 'Emerald', 'description' => 'Fresh green', 'gradient' => 'linear-gradient(135deg,#059669,#34d399)'],
        'sunset' => ['label' => 'Sunset', 'description' => 'Warm orange-gold', 'gradient' => 'linear-gradient(135deg,#ea580c,#f59e0b)'],
        'rose' => ['label' => 'Rose', 'description' => 'Bright pink-coral', 'gradient' => 'linear-gradient(135deg,#db2777,#fb7185)'],
        'midnight' => ['label' => 'Midnight', 'description' => 'Deep slate', 'gradient' => 'linear-gradient(135deg,#1e293b,#475569)'],
    ];
}

function chat_theme_key(array $user): string
{
    $key = (string) ($user['chat_theme'] ?? 'indigo');
    return array_key_exists($key, chat_themes()) && is_premium($user) ? $key : 'indigo';
}

function chat_theme_class(array $user): string
{
    if (!is_premium($user)) {
        return 'chat-theme-default';
    }
    return 'chat-theme-' . preg_replace('/[^a-z0-9-]/', '', chat_theme_key($user));
}

/** Inline CSS custom properties that theme a profile hero with the accent. */
function profile_style_attr(array $u): string
{
    $hex = profile_accent_hex((string) ($u['profile_accent'] ?? 'indigo'));
    $rgb = '99 102 241';
    if (preg_match('/^#([0-9a-f]{6})$/i', $hex, $m)) {
        $v = hexdec($m[1]);
        $rgb = (($v >> 16) & 255) . ' ' . (($v >> 8) & 255) . ' ' . ($v & 255);
    }
    return '--pf:' . $hex . ';--pf-rgb:' . $rgb;
}

/** Coloured status pill. */
function sb(string $status): string
{
    $cls = match ($status) {
        'pending', 'awaiting_payment', 'submitted', 'reviewing' => 'warn',
        'verified', 'active', 'completed', 'paid', 'approved' => 'ok',
        'rejected', 'cancelled', 'expired', 'failed'              => 'bad',
        'in_progress', 'delivered', 'shortlisted'                 => 'brand',
        'succeeded'                                                => 'ok',
        default                                                   => 'muted',
    };
    if ($status === 'none') {
        return '<span class="pill pill-muted">Not required</span>';
    }
    return '<span class="pill pill-' . $cls . '"><span class="dot"></span>' . e(status_label($status)) . '</span>';
}
/* ------------------------------------------------------------------ */
/* Inline SVG icon system (Feather-style, offline)                     */
/* ------------------------------------------------------------------ */

function ui_icon(string $name, int $size = 18, string $class = ''): string
{
    $icons = [
        'bell'       => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'home'       => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'grid'       => '<rect x="3" y="3" width="7" height="7" rx="1.6"/><rect x="14" y="3" width="7" height="7" rx="1.6"/><rect x="14" y="14" width="7" height="7" rx="1.6"/><rect x="3" y="14" width="7" height="7" rx="1.6"/>',
        'box'        => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05"/><path d="M12 22.08V12"/>',
        'wallet'     => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
        'chat'       => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'message'    => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/>',
        'plus'       => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'search'     => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'star'       => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'shield'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'check'      => '<polyline points="20 6 9 17 4 12"/>',
        'x'          => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'alert'      => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'info'       => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'arrow-right'=> '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'arrow-left' => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'lock'       => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'card'       => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'send'       => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'paperclip'  => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        'camera'     => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
        'users'      => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'sparkle'    => '<path d="M12 3l1.9 5.8a2 2 0 0 0 1.3 1.3L21 12l-5.8 1.9a2 2 0 0 0-1.3 1.3L12 21l-1.9-5.8a2 2 0 0 0-1.3-1.3L3 12l5.8-1.9a2 2 0 0 0 1.3-1.3z"/>',
        'graduation' => '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/>',
        'pen'        => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'code'       => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
        'palette'    => '<circle cx="13.5" cy="6.5" r="1.5"/><circle cx="17.5" cy="10.5" r="1.5"/><circle cx="8.5" cy="7.5" r="1.5"/><circle cx="6.5" cy="12.5" r="1.5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.7-.77 1.7-1.7 0-.44-.16-.83-.44-1.14-.27-.3-.44-.7-.44-1.16 0-.93.77-1.7 1.7-1.7H17c3.31 0 6-2.69 6-6 0-5.52-4.48-10-10-10z"/>',
        'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.41 1.41-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V20h-2v-.49a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-1.41-1.41.06-.06A1.7 1.7 0 0 0 9.4 15a1.7 1.7 0 0 0-1.56-1.03H7v-2h.49A1.7 1.7 0 0 0 9.05 10.4a1.7 1.7 0 0 0-.34-1.88l-.06-.06 1.41-1.41.06.06a1.7 1.7 0 0 0 1.88.34A1.7 1.7 0 0 0 13.03 5.9V5h2v.9a1.7 1.7 0 0 0 1.03 1.55 1.7 1.7 0 0 0 1.88-.34L18 7.05l1.41 1.41-.06.06a1.7 1.7 0 0 0-.34 1.88A1.7 1.7 0 0 0 20.57 11H21v2h-.43A1.7 1.7 0 0 0 19.4 15z"/>',
        'file'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'presentation' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
        'video'      => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
        'puzzle'     => '<path d="M4 7h3V4a2 2 0 0 1 4 0v3h4V5a2 2 0 0 1 4 0v2a2 2 0 0 1 2 2h1a1 1 0 0 1 1 1v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z"/>',
        'trending'   => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
        'cash'       => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01M18 12h.01"/>',
        'download'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'copy'       => '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
        'clock'      => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'zap'        => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'log-out'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'external'   => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        'mail'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/>',
        'instagram'  => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/>',
        'facebook'   => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
    ];

    $body = $icons[$name] ?? $icons['sparkle'];
    $cls  = $class !== '' ? ' ' . e($class) : '';
    return '<svg class="ic' . $cls . '" width="' . $size . '" height="' . $size
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

function cat_icon(string $category): string
{
    $map = [
        'Tutoring & Academics'      => 'graduation',
        'Editing & Proofreading'    => 'pen',
        'Coding & Web'              => 'code',
        'Design & Creative'         => 'palette',
        'Writing & Translation'     => 'file',
        'Presentations & Documents' => 'presentation',
        'Audio & Video'             => 'video',
        'Study Groups & Other'      => 'puzzle',
    ];
    return '<span class="cat-ic">' . ui_icon($map[$category] ?? 'sparkle', 16) . '</span>';
}

function profile_statuses(): array
{
    return [
        'online' => ['label' => 'Online', 'class' => 'online'],
        'away' => ['label' => 'Away', 'class' => 'away'],
        'dnd' => ['label' => 'Do not disturb', 'class' => 'dnd'],
        'offline' => ['label' => 'Offline', 'class' => 'offline'],
    ];
}

function profile_status_badge(?array $user, bool $withLabel = true): string
{
    $statuses = profile_statuses();
    $status = $statuses[(string) ($user['profile_status'] ?? 'offline')] ?? $statuses['offline'];
    return '<span class="presence presence-' . e($status['class']) . '"><i></i>'
        . ($withLabel ? '<span>' . e($status['label']) . '</span>' : '') . '</span>';
}

/** Nerasky logo as inline SVG (unique gradient id per call). */
function logo_svg(int $size = 34, string $suffix = ''): string
{
    $id = 'nlg' . $size . '-' . md5($suffix . $size);
    $cls = $suffix !== '' ? ' logo-' . e($suffix) : '';
    return '<svg class="logo' . $cls . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<defs><linearGradient id="' . $id . '" x1="2" y1="2" x2="46" y2="46" gradientUnits="userSpaceOnUse">'
        . '<stop stop-color="#6366F1"/><stop offset=".55" stop-color="#8B5CF6"/><stop offset="1" stop-color="#EC4899"/>'
        . '</linearGradient></defs>'
        . '<rect x="2" y="2" width="44" height="44" rx="13" fill="url(#' . $id . ')"/>'
        . '<path d="M14 34V14l16 20V14" stroke="#fff" stroke-width="4.4" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<circle cx="35.5" cy="12.5" r="3.2" fill="#FDE68A"/></svg>';
}
/* ------------------------------------------------------------------ */
/* Uploads                                                             */
/* ------------------------------------------------------------------ */

/** Save an uploaded file into uploads/<folder>; returns [ok(bool), path|error]. */
function upload_file(array $file, string $folder, array $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf']): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return [false, 'Invalid upload request.'];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return [false, 'No file was selected.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'Upload failed (error code ' . $file['error'] . ').'];
    }
    if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return [false, 'The uploaded file could not be verified. Please try again.'];
    }
    if ($file['size'] > MAX_UPLOAD_MB * 1048576) {
        return [false, 'File exceeds the ' . MAX_UPLOAD_MB . ' MB limit.'];
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return [false, 'File type .' . e($ext) . ' is not allowed. Use ' . implode(', ', $allowed) . '.'];
    }
    $dir = APP_ROOT . '/uploads/' . trim($folder, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return [false, 'Could not store the file. Folder permissions?'];
    }
    return [true, 'uploads/' . trim($folder, '/') . '/' . $name];
}

/** Upload a verified raster image for a public service gallery. */
function upload_service_image(array $file): array
{
    if (!isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'One of the showcase images could not be uploaded. Please try again.'];
    }
    if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return [false, 'The showcase image upload could not be verified. Please try again.'];
    }
    if ((int) ($file['size'] ?? 0) > MAX_UPLOAD_MB * 1048576) {
        return [false, 'Each showcase image must be ' . MAX_UPLOAD_MB . ' MB or smaller.'];
    }

    $extensions = ['jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP];
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $image = @getimagesize((string) $file['tmp_name']);
    if (!isset($extensions[$extension]) || $image === false || $image[2] !== $extensions[$extension]) {
        return [false, 'Showcase images must be genuine JPG, PNG, or WebP files.'];
    }

    $dir = APP_ROOT . '/uploads/services';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return [false, 'Could not create the service image folder. Check folder permissions.'];
    }
    $name = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
        return [false, 'Could not store the showcase image. Check folder permissions.'];
    }
    return [true, 'uploads/services/' . $name];
}

/** Convert PHP's multiple-file upload shape into individual upload records. */
function normalize_multi_uploads(array $files): array
{
    if (!isset($files['name'], $files['type'], $files['tmp_name'], $files['error'], $files['size'])
        || !is_array($files['name']) || !is_array($files['type']) || !is_array($files['tmp_name'])
        || !is_array($files['error']) || !is_array($files['size'])) {
        return [];
    }

    $uploads = [];
    foreach ($files['name'] as $index => $name) {
        $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $uploads[] = [
            'name' => $name,
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $error,
            'size' => $files['size'][$index] ?? 0,
        ];
    }
    return $uploads;
}

/** Remove a gallery file without allowing paths outside the service image folder. */
function delete_service_image_file(string $path): void
{
    $prefix = 'uploads/services/';
    $relativePath = ltrim(str_replace('\\', '/', $path), '/');
    if (!str_starts_with($relativePath, $prefix)) {
        return;
    }
    $filename = substr($relativePath, strlen($prefix));
    if ($filename === '' || basename($filename) !== $filename) {
        return;
    }
    $absolutePath = APP_ROOT . '/' . $prefix . $filename;
    if (is_file($absolutePath) && !unlink($absolutePath)) {
        throw new RuntimeException('Could not remove service image file: ' . $filename);
    }
}

/** Relative uploads path -> absolute app URL. */
function asset_url(string $path): string
{
    if ($path === '' || $path === null) {
        return '';
    }
    foreach (['uploads/proofs/', 'uploads/cash-in-proofs/', 'uploads/works/', 'uploads/consents/'] as $privateFolder) {
        if (str_starts_with(ltrim($path, '/'), $privateFolder)) {
            return base_url('document.php?path=' . rawurlencode(ltrim($path, '/')));
        }
    }
    return base_url(ltrim($path, '/'));
}

/* ------------------------------------------------------------------ */
/* Marketplace helpers                                                 */
/* ------------------------------------------------------------------ */

function unread_chat_count(int $userId): int
{
    return (int) scalar(
        'SELECT COUNT(*) FROM messages m
         JOIN orders o ON o.id = m.order_id
         WHERE m.sender_id <> ? AND m.is_read = 0
           AND (o.buyer_id = ? OR o.seller_id = ?)',
        [$userId, $userId, $userId]
    );
}

/** Average rating for a seller (from published reviews) or null. */
function seller_rating(int $sellerId): ?float
{
    $row = row('SELECT ROUND(AVG(rating), 1) avg_r, COUNT(*) c FROM reviews WHERE reviewee_id = ?', [$sellerId]);
    if (!$row || (int) $row['c'] === 0 || $row['avg_r'] === null) {
        return null;
    }
    return (float) $row['avg_r'];
}

function seller_jobs_done(int $sellerId): int
{
    return (int) scalar("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'completed'", [$sellerId]);
}

/** Trust and service history shown on an order without requiring extra schema. */
function seller_trust_metrics(int $sellerId): array
{
    $metrics = row(
        "SELECT
            COUNT(*) AS total_orders,
            SUM(status = 'completed') AS completed_orders,
            SUM(status = 'cancelled') AS cancelled_orders,
            MIN(u.created_at) AS member_since,
            (
                SELECT AVG(TIMESTAMPDIFF(HOUR, o.created_at, first_reply.created_at))
                FROM orders o
                JOIN (
                    SELECT m.order_id, MIN(m.created_at) AS created_at
                    FROM messages m
                    WHERE m.sender_id = ?
                    GROUP BY m.order_id
                ) first_reply ON first_reply.order_id = o.id
                WHERE o.seller_id = ? AND first_reply.created_at >= o.created_at
            ) AS response_hours
         FROM users u
         LEFT JOIN orders all_orders ON all_orders.seller_id = u.id
         WHERE u.id = ?
         GROUP BY u.id, u.created_at",
        [$sellerId, $sellerId, $sellerId]
    ) ?? [];
    $total = (int) ($metrics['total_orders'] ?? 0);
    $cancelled = (int) ($metrics['cancelled_orders'] ?? 0);
    return [
        'completed' => (int) ($metrics['completed_orders'] ?? 0),
        'cancelled' => $cancelled,
        'cancellation_rate' => $total > 0 ? round(($cancelled / $total) * 100, 1) : null,
        'member_since' => $metrics['member_since'] ?? null,
        'response_hours' => $metrics['response_hours'] !== null ? (float) $metrics['response_hours'] : null,
    ];
}

/** Calculate the delivery deadline from the service promise captured on the order. */
function order_deadline(array $order): ?DateTimeImmutable
{
    $created = (string) ($order['created_at'] ?? '');
    $days = max(0, (int) ($order['delivery_days'] ?? 0));
    if ($created === '' || $days < 1) {
        return null;
    }
    try {
        return (new DateTimeImmutable($created))->modify('+' . $days . ' days');
    } catch (Throwable $e) {
        return null;
    }
}

function order_deadline_state(array $order): array
{
    $deadline = order_deadline($order);
    if (!$deadline || in_array((string) ($order['status'] ?? ''), ['completed', 'cancelled'], true)) {
        return ['deadline' => $deadline, 'overdue' => false, 'days_left' => null];
    }
    $now = new DateTimeImmutable('now');
    $seconds = $deadline->getTimestamp() - $now->getTimestamp();
    return [
        'deadline' => $deadline,
        'overdue' => $seconds < 0,
        'days_left' => (int) ceil($seconds / 86400),
    ];
}

function seller_response_time_label(?float $hours): ?string
{
    if ($hours === null) {
        return null;
    }
    if ($hours < 1) {
        return 'under 1 hour';
    }
    if ($hours < 24) {
        return round($hours) . ' hours';
    }
    return round($hours / 24, 1) . ' days';
}

/** Keep a small, session-backed history without creating a persistence table. */
function remember_viewed_service(int $serviceId): void
{
    if ($serviceId < 1 || session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $recent = array_values(array_filter(
        (array) ($_SESSION['recently_viewed_services'] ?? []),
        static fn ($id): bool => ctype_digit((string) $id) && (int) $id !== $serviceId
    ));
    array_unshift($recent, $serviceId);
    $_SESSION['recently_viewed_services'] = array_slice($recent, 0, 8);
}

/** Return valid service IDs in most-recent-first order. */
function recently_viewed_service_ids(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }

    return array_values(array_filter(
        array_slice((array) ($_SESSION['recently_viewed_services'] ?? []), 0, 8),
        static fn ($id): bool => ctype_digit((string) $id) && (int) $id > 0
    ));
}

/** Return saved service IDs for a member, newest first. */
function saved_service_ids(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    return array_map(
        static fn ($id): int => (int) $id,
        array_column(rows('SELECT service_id FROM favorites WHERE user_id = ? ORDER BY id DESC', [$userId]), 'service_id')
    );
}

function is_service_saved(int $userId, int $serviceId): bool
{
    return $userId > 0 && $serviceId > 0
        && (int) scalar('SELECT COUNT(*) FROM favorites WHERE user_id = ? AND service_id = ?', [$userId, $serviceId]) > 0;
}

/** Toggle a saved service and return its new saved state. */
function toggle_saved_service(int $userId, int $serviceId): bool
{
    if ($userId <= 0 || $serviceId <= 0) {
        return false;
    }
    if (is_service_saved($userId, $serviceId)) {
        q('DELETE FROM favorites WHERE user_id = ? AND service_id = ?', [$userId, $serviceId]);
        return false;
    }
    insert('INSERT IGNORE INTO favorites (user_id, service_id) VALUES (?,?)', [$userId, $serviceId]);
    return true;
}

function star_row(float $rating): string
{
    $out = '<span class="stars" aria-label="' . number_format($rating, 1) . ' / 5">';
    for ($i = 1; $i <= 5; $i++) {
        $filled = $rating >= $i - 0.25;
        $half   = !$filled && $rating >= $i - 0.75;
        $out .= $filled ? '★' : ($half ? '⯨' : '☆');
    }
    return $out . '</span>';
}

/** Is this user allowed to place orders? */
function can_buy(array $u): bool
{
    return $u['account_status'] === 'verified' && $u['role'] !== 'blocked';
}

/** Can this user create services? */
function can_sell(array $u): bool
{
    return $u['account_status'] === 'verified';
}

/* ------------------------------------------------------------------ */
/* Notifications                                                       */
/* ------------------------------------------------------------------ */

/** Insert a notification for a single user. */
function notify(int $userId, string $kind, string $text, string $link): void
{
    insert('INSERT INTO notifications (user_id, kind, text, link, is_read) VALUES (?,?,?,?,0)', [$userId, $kind, $text, $link]);
}

/** Add an actionable reminder once per order, without spamming the bell. */
function refresh_order_notifications(int $uid): void
{
    $pendingServices = rows('SELECT id, title FROM services WHERE seller_id = ? AND status = "pending" ORDER BY id DESC LIMIT 10', [$uid]);
    foreach ($pendingServices as $service) {
        $link = 'service-edit.php?id=' . (int) $service['id'];
        $text = 'Your service "' . mb_strimwidth((string) $service['title'], 0, 45, '…') . '" is waiting for admin review.';
        if (!scalar('SELECT id FROM notifications WHERE user_id = ? AND kind = "service" AND link = ? LIMIT 1', [$uid, $link])) {
            notify($uid, 'service', $text, $link);
        }
    }
    $orders = rows('SELECT id, buyer_id, seller_id, status FROM orders WHERE (buyer_id = ? OR seller_id = ?) AND status IN ("awaiting_payment","in_progress","delivered") ORDER BY id DESC LIMIT 25', [$uid, $uid]);
    foreach ($orders as $order) {
        $kind = null;
        $text = '';
        if ((int) $order['buyer_id'] === $uid && $order['status'] === 'delivered') {
            $kind = 'review';
            $text = 'Your order #' . (int) $order['id'] . ' is ready — review the work to release payment.';
        } elseif ((int) $order['buyer_id'] === $uid && $order['status'] === 'awaiting_payment') {
            $kind = 'reminder';
            $text = 'Payment is still needed for order #' . (int) $order['id'] . '.';
        } elseif ((int) $order['seller_id'] === $uid && $order['status'] === 'in_progress') {
            $kind = 'reminder';
            $text = 'Order #' . (int) $order['id'] . ' is in progress — check the delivery window.';
        }
        if ($kind !== null) {
            $link = 'order.php?order=' . (int) $order['id'];
            $exists = scalar('SELECT id FROM notifications WHERE user_id = ? AND kind = ? AND link = ? AND text = ? LIMIT 1', [$uid, $kind, $link, $text]);
            if (!$exists) {
                notify($uid, $kind, $text, $link);
            }
        }
    }
}

/** Notify every admin (used for moderation queues). */
function notify_admins(string $kind, string $text, string $link): void
{
    foreach (rows("SELECT id FROM users WHERE role = 'admin'") as $a) {
        notify((int) $a['id'], $kind, $text, $link);
    }
}

function notifications_unread_count(int $uid): int
{
    return (int) scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$uid]);
}

function latest_notifications(int $uid, int $limit = 8): array
{
    return rows('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$uid]);
}

function mark_notification_read(int $uid, int $nid): void
{
    q('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$nid, $uid]);
}

function mark_all_notifications_read(int $uid): void
{
    q('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$uid]);
}

/** SVG icon for a notification kind. */
function notif_icon(string $kind): string
{
    $icon = match ($kind) {
        'message'    => 'chat',
        'payment'    => 'card',
        'payout'     => 'cash',
        'order'      => 'box',
        'delivered'  => 'check',
        'withdrawal' => 'wallet',
        'moderation' => 'shield',
        'account'    => 'users',
        'pro'        => 'sparkle',
        'review'     => 'star',
        'reminder'   => 'clock',
        'service'    => 'grid',
        default      => 'bell',
    };
    return ui_icon($icon, 16);
}

/* ------------------------------------------------------------------ */
/* Direct conversations (student-to-student messaging)                 */
/* ------------------------------------------------------------------ */

/** Find the conversation between two users or create it. */
function start_conversation(int $a, int $b): int
{
    if ($a > $b) {
        [$a, $b] = [$b, $a];
    }
    $existing = row('SELECT id FROM conversations WHERE user1_id = ? AND user2_id = ?', [$a, $b]);
    if ($existing) {
        return (int) $existing['id'];
    }
    return (int) insert('INSERT INTO conversations (user1_id, user2_id) VALUES (?,?)', [$a, $b]);
}

/** Persist a direct message and ping the recipient. */
function send_conversation_message(int $convId, int $senderId, string $body): void
{
    insert('INSERT INTO conversation_messages (conversation_id, sender_id, body, is_read) VALUES (?,?,?,0)', [$convId, $senderId, $body]);
    q('UPDATE conversations SET last_message_at = NOW() WHERE id = ?', [$convId]);
    $c = row('SELECT * FROM conversations WHERE id = ?', [$convId]);
    if ($c) {
        $other = (int) $c['user1_id'] === $senderId ? (int) $c['user2_id'] : (int) $c['user1_id'];
        notify($other, 'message', $body, 'messages.php?conv=' . $convId);
    }

    /** Store a safe, non-executable attachment for a conversation message. */
    function conversation_attachment_upload(array $file): array
    {
        $allowed = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
            'txt' => 'text/plain',
        ];
        if (!isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return [false, 'Please choose a valid attachment.'];
        }
        if ((int) $file['size'] > 8 * 1048576) {
            return [false, 'Attachments must be 8 MB or smaller.'];
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset($allowed[$ext]) || !is_uploaded_file((string) $file['tmp_name'])) {
            return [false, 'Allowed attachments: JPG, PNG, GIF, WEBP, PDF, or TXT.'];
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if ($mime !== $allowed[$ext]) {
            return [false, 'The attachment content does not match its file type.'];
        }
        $dir = APP_ROOT . '/uploads/messages';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return [false, 'Could not create the attachment folder.'];
        }
        $name = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return [false, 'Could not store the attachment.'];
        }
        return [true, ['path' => 'uploads/messages/' . $name, 'name' => mb_substr(basename((string) $file['name']), 0, 180), 'mime' => $mime, 'size' => (int) $file['size']]];
    }

    function send_conversation_attachment_message(int $convId, int $senderId, string $body, array $attachment): void
    {
        insert('INSERT INTO conversation_messages (conversation_id, sender_id, body, attachment_path, attachment_name, attachment_mime, attachment_size, is_read) VALUES (?,?,?,?,?,?,?,0)', [$convId, $senderId, $body, $attachment['path'], $attachment['name'], $attachment['mime'], $attachment['size']]);
        q('UPDATE conversations SET last_message_at = NOW() WHERE id = ?', [$convId]);
        $c = row('SELECT * FROM conversations WHERE id = ?', [$convId]);
        if ($c) {
            $other = (int) $c['user1_id'] === $senderId ? (int) $c['user2_id'] : (int) $c['user1_id'];
            notify($other, 'message', $body !== '' ? $body : 'Sent an attachment', 'messages.php?conv=' . $convId);
        }
    }
}

function daily_message_count(int $userId): int
{
    return (int) scalar(
        'SELECT COUNT(*) FROM conversation_messages WHERE sender_id = ? AND created_at >= CURDATE()',
        [$userId]
    );
}

function message_daily_limit(?array $user): int
{
    return is_premium($user) ? PREMIUM_DAILY_MESSAGES : FREE_DAILY_MESSAGES;
}

function message_max_chars(?array $user): int
{
    return is_premium($user) ? PREMIUM_MESSAGE_MAX_CHARS : FREE_MESSAGE_MAX_CHARS;
}

function conversation_unread_count(int $uid): int
{
    return (int) scalar(
        'SELECT COUNT(*) FROM conversation_messages cm
         JOIN conversations c ON c.id = cm.conversation_id
         WHERE cm.is_read = 0 AND cm.sender_id <> ?
           AND (c.user1_id = ? OR c.user2_id = ?)',
        [$uid, $uid, $uid]
    );
}

/* ------------------------------------------------------------------ */
/* Admin Audit Logging                                                 */
/* ------------------------------------------------------------------ */

/**
 * Log an admin action to the audit trail for compliance and security.
 * Actions: 'verify', 'reject', 'approve', 'mark_paid', 'update_notes',
 *          'change_role', 'deactivate_staff', etc.
 */
function audit_log(
    int $adminId,
    string $action,
    string $entityType,
    int $entityId,
    ?array $details = null
): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    try {
        insert(
            'INSERT INTO admin_audit_log (admin_id, action, entity_type, entity_id, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $adminId,
                $action,
                $entityType,
                $entityId,
                $details ? json_encode($details) : null,
                $ip,
                $ua,
            ]
        );
    } catch (Exception $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

/**
 * Retrieve audit log entries with pagination and optional filters.
 */
function get_audit_log(
    ?string $action = null,
    ?string $entityType = null,
    ?int $adminId = null,
    int $limit = 50,
    int $offset = 0
): array {
    $query = 'SELECT * FROM admin_audit_log WHERE 1=1';
    $params = [];

    if ($action) {
        $query .= ' AND action = ?';
        $params[] = $action;
    }
    if ($entityType) {
        $query .= ' AND entity_type = ?';
        $params[] = $entityType;
    }
    if ($adminId) {
        $query .= ' AND admin_id = ?';
        $params[] = $adminId;
    }

    $query .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;

    return rows($query, $params);
}

/**
 * Count total audit log entries for pagination.
 */
function audit_log_count(
    ?string $action = null,
    ?string $entityType = null,
    ?int $adminId = null
): int {
    $query = 'SELECT COUNT(*) FROM admin_audit_log WHERE 1=1';
    $params = [];

    if ($action) {
        $query .= ' AND action = ?';
        $params[] = $action;
    }
    if ($entityType) {
        $query .= ' AND entity_type = ?';
        $params[] = $entityType;
    }
    if ($adminId) {
        $query .= ' AND admin_id = ?';
        $params[] = $adminId;
    }

    return (int) scalar($query, $params);
}