<?php
declare(strict_types=1);

/*
|---------------------------------------------------------------------------
| Supabase Auth REST helpers
|---------------------------------------------------------------------------
| Email signup, confirmation, password sign-in, and Google OAuth use
| Supabase Auth. The MySQL account remains the marketplace profile/store.
| Only the Supabase publishable/anon key is used here; never the service key.
|---------------------------------------------------------------------------
*/

require_once __DIR__ . '/config.php';

function supabase_configured(): bool
{
    return SUPABASE_URL !== ''
        && SUPABASE_ANON_KEY !== ''
        && filter_var(SUPABASE_URL, FILTER_VALIDATE_URL) !== false
        && filter_var(SUPABASE_REDIRECT_URL, FILTER_VALIDATE_URL) !== false;
}

/**
 * Make an Auth API request.
 *
 * @return array{ok:bool,data?:array,error?:string,code?:string,status?:int}
 */
function supabase_auth_request(string $path, string $method = 'POST', array $payload = [], string $accessToken = ''): array
{
    if (!supabase_configured()) {
        return ['ok' => false, 'error' => 'Supabase is not configured.'];
    }

    $url = rtrim(SUPABASE_URL, '/') . '/auth/v1/' . ltrim($path, '/');
    $headers = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($accessToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }
    $options = [
        'method' => $method,
        'header' => implode("\r\n", $headers) . "\r\n",
        'timeout' => 12,
        'ignore_errors' => true,
    ];
    if ($method !== 'GET') {
        $content = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($content === false) {
            error_log('Could not encode Supabase Auth request (' . $path . ').');
            return ['ok' => false, 'error' => 'The Supabase request could not be prepared.'];
        }
        $options['content'] = $content;
    }

    $context = stream_context_create(['http' => $options]);
    $body = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $match) === 1) {
        $status = (int) $match[1];
    }
    if ($body === false) {
        $lastError = error_get_last();
        $message = is_array($lastError) ? (string) ($lastError['message'] ?? '') : '';
        error_log('Supabase Auth request failed (' . $path . '): ' . $message);
        return ['ok' => false, 'error' => 'Supabase could not be reached.', 'status' => $status];
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        error_log('Supabase Auth returned an invalid response (' . $path . ').');
        return ['ok' => false, 'error' => 'Supabase returned an invalid response.', 'status' => $status];
    }
    if ($status < 200 || $status >= 300) {
        $message = (string) ($data['msg'] ?? $data['message'] ?? $data['error_description'] ?? $data['error'] ?? 'Authentication request failed.');
        $code = (string) ($data['code'] ?? $data['error_code'] ?? '');
        return ['ok' => false, 'error' => $message, 'code' => $code, 'status' => $status];
    }

    return ['ok' => true, 'data' => $data, 'status' => $status];
}

/** Create the Supabase identity and send its signup confirmation email. */
function supabase_send_signup_confirmation(string $email, string $password, string $fullName): array
{
    $redirect = rawurlencode(SUPABASE_REDIRECT_URL);
    return supabase_auth_request(
        'signup?redirect_to=' . $redirect,
        'POST',
        [
            'email' => $email,
            'password' => $password,
            'data' => ['full_name' => $fullName],
        ]
    );
}

/** Ask Supabase to resend its signup confirmation without disclosing account existence. */
function supabase_resend_signup_confirmation(string $email): array
{
    return supabase_auth_request(
        'resend?redirect_to=' . rawurlencode(SUPABASE_REDIRECT_URL),
        'POST',
        ['type' => 'signup', 'email' => $email]
    );
}

/** Authenticate a password and return its Supabase user record. */
function supabase_password_sign_in(string $email, string $password): array
{
    $result = supabase_auth_request('token?grant_type=password', 'POST', [
        'email' => $email,
        'password' => $password,
    ]);
    if (!$result['ok']) {
        return $result;
    }

    $user = $result['data']['user'] ?? null;
    if (!is_array($user)) {
        error_log('Supabase password sign-in returned no user record.');
        return ['ok' => false, 'error' => 'Supabase returned an invalid authentication response.'];
    }
    return ['ok' => true, 'user' => $user];
}

/** Validate a Supabase access token and return its user record. */
function supabase_user_for_access_token(string $accessToken): array
{
    $result = supabase_auth_request('user', 'GET', [], $accessToken);
    if (!$result['ok']) {
        return $result;
    }
    return ['ok' => true, 'user' => $result['data']];
}

/** Whether Supabase has confirmed this user's email address. */
function supabase_user_email_confirmed(array $user): bool
{
    return !empty($user['email_confirmed_at']) || !empty($user['confirmed_at']);
}

/** True for a Supabase user authenticated through Google OAuth. */
function supabase_user_has_google_provider(array $user): bool
{
    $providers = $user['app_metadata']['providers'] ?? [];
    return ($user['app_metadata']['provider'] ?? '') === 'google'
        || (is_array($providers) && in_array('google', $providers, true));
}

/** Inline browser configuration contains only public values. */
function supabase_client_config(): string
{
    if (!supabase_configured()) {
        return '';
    }

    return 'window.NERASKY_SUPABASE_CONFIG = ' . json_encode([
        'url' => SUPABASE_URL,
        'key' => SUPABASE_ANON_KEY,
        'callbackUrl' => SUPABASE_REDIRECT_URL,
        'endpoint' => base_url('supabase_auth.php'),
        'loginUrl' => base_url('login.php'),
        'version' => SUPABASE_JS_VERSION,
        'csrf' => csrf_token(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';';
}

/** Render the OAuth buttons only when Supabase is configured. */
function google_signin_button(string $next = 'dashboard.php', string $actionText = 'Continue with Google'): string
{
    if (!supabase_configured()) {
        return '';
    }

    return '<div class="google-signin-wrap">'
        . '<button type="button" class="btn btn-google btn-block" data-supabase-google data-next="' . e(safe_next($next)) . '">'
        . '<svg class="g-ic" viewBox="0 0 48 48" width="18" height="18" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.72 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>'
        . '<span>' . e($actionText) . '</span>'
        . '</button>'
        . '<p class="hint center" style="font-size:.78rem;margin:.5rem 0 0">Google confirms this e-mail belongs to you.</p>'
        . '</div>';
}

/** Load the small auth module on auth and callback pages only. */
function supabase_auth_script(): string
{
    if (!supabase_configured()) {
        return '';
    }
    $currentNav = (string) ($GLOBALS['currentNav'] ?? '');
    $isCallback = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'supabase_callback.php';
    if (!$isCallback && !in_array($currentNav, ['login', 'register'], true)) {
        return '';
    }

    $file = APP_ROOT . '/assets/js/auth-supabase.js';
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return '<script type="module" src="' . e(base_url('assets/js/auth-supabase.js?v=' . $version)) . '"></script>';
}
