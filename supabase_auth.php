<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function supabase_respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    supabase_respond(405, ['ok' => false, 'message' => 'Method not allowed.']);
}
if (!supabase_configured()) {
    supabase_respond(503, ['ok' => false, 'message' => 'Secure sign-in is temporarily unavailable.']);
}

verify_csrf();

if (!throttle_allows('supabase-auth:' . (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 20, 900)) {
    supabase_respond(429, ['ok' => false, 'message' => 'Too many attempts. Please wait and try again.']);
}

$accessToken = trim((string) ($_POST['access_token'] ?? ''));
if ($accessToken === '' || strlen($accessToken) > 8192) {
    supabase_respond(400, ['ok' => false, 'message' => 'A valid sign-in session was not received.']);
}

$authResult = supabase_user_for_access_token($accessToken);
if (!$authResult['ok']) {
    supabase_respond(401, ['ok' => false, 'message' => 'We could not verify this sign-in. Please try again.']);
}

$identity = $authResult['user'];
$email = strtolower(trim((string) ($identity['email'] ?? '')));
$supabaseUid = (string) ($identity['id'] ?? '');
$isGoogle = supabase_user_has_google_provider($identity);

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $supabaseUid === '') {
    supabase_respond(401, ['ok' => false, 'message' => 'We could not verify the account details. Please try again.']);
}
if (!supabase_user_email_confirmed($identity)) {
    supabase_respond(403, ['ok' => false, 'message' => 'Please confirm your e-mail address before signing in.']);
}
$user = row('SELECT * FROM users WHERE email = ?', [$email]);
$isExistingAccount = (bool) $user;
if (!$user && $isGoogle) {
    $metadata = $identity['user_metadata'] ?? [];
    $name = is_array($metadata) ? trim((string) ($metadata['full_name'] ?? $metadata['name'] ?? '')) : '';
    if ($name === '') {
        $name = substr($email, 0, strpos($email, '@') ?: 20);
    }
    $newId = insert(
        'INSERT INTO users (full_name, email, password_hash, account_status, supabase_uid, email_verified, google_verified, email_verified_at)
         VALUES (?,?,?,?,?,1,1,NOW())',
        [$name, $email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), 'pending', $supabaseUid]
    );
    notify_admins('account', 'New Google Sign-In waiting: ' . $name . ' (' . $email . ').', 'admin/users.php');
    $user = row('SELECT * FROM users WHERE id = ?', [$newId]);
}

if (!$user) {
    supabase_respond(403, ['ok' => false, 'message' => 'Create a Nerasky account first, then confirm your e-mail and sign in.']);
}
if ($user['account_status'] === 'rejected') {
    supabase_respond(403, ['ok' => false, 'message' => 'This account was not approved.']);
}

$googleMethodAdded = $isExistingAccount && $isGoogle && (int) ($user['google_verified'] ?? 0) !== 1;
q(
    'UPDATE users SET supabase_uid = ?, email_verified = 1,
       google_verified = CASE WHEN ? = 1 THEN 1 ELSE google_verified END,
       email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?',
    [$supabaseUid, $isGoogle ? 1 : 0, (int) $user['id']]
);
if ($googleMethodAdded) {
    $alert = send_security_alert_email(
        (string) $user['email'],
        'A Google sign-in method was added to your Nerasky account',
        'A Google sign-in was used to access your Nerasky account. If you did not connect or use Google sign-in, contact Nerasky support.'
    );
    if (!$alert['ok']) {
        error_log('Could not send Google sign-in method alert for user ' . (int) $user['id'] . '.');
        flash('warn', 'Google sign-in was connected, but we could not email the security alert. Contact support if this was unexpected.');
    }
}

session_regenerate_id(true);
$_SESSION['uid'] = (int) $user['id'];
stamp_login();

$next = safe_next((string) ($_POST['next'] ?? ''));
supabase_respond(200, ['ok' => true, 'redirect' => base_url($next)]);
