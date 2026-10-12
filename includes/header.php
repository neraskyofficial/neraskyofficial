<?php
declare(strict_types=1);

$pageTitle    = $pageTitle ?? '';
$pageMeta     = $pageMeta ?? APP_TAGLINE;
$bodyClass    = trim(($bodyClass ?? '') . ' page-nav-' . preg_replace('/[^a-z0-9_-]/i', '', (string) ($currentNav ?? '')));
$currentNav   = $currentNav ?? '';
$fullTitle    = $pageTitle !== '' ? $pageTitle . ' · ' . APP_NAME : APP_NAME;
$me           = get_user();
$__flashes    = take_flashes();

/* Nav counters for logged-in users (guard for pre-install). */
$navMsgUnread   = 0;
$navNotifUnread = 0;
$navNotifs      = [];
if ($me) {
    try {
        $navMsgUnread   = conversation_unread_count((int) $me['id']);
        $navNotifUnread = notifications_unread_count((int) $me['id']);
        $navNotifs      = latest_notifications((int) $me['id']);
    } catch (Throwable $e) {
        $navMsgUnread = $navNotifUnread = 0;
        $navNotifs    = [];
    }
}

?>
<!DOCTYPE html>
<html lang="en" class="<?= $currentNav === 'home' ? 'page-home' : '' ?>">
<head>
  <script>document.documentElement.classList.add('js');</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($pageMeta) ?>">
<meta name="color-scheme" content="light">
<link rel="icon" type="image/png" href="<?= e(base_url('assets/nerasky-logo.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(base_url('assets/nerasky-logo.png')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css?v=' . (is_file(APP_ROOT . '/assets/css/style.css') ? filemtime(APP_ROOT . '/assets/css/style.css') : '1'))) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/select-ui.css?v=' . (is_file(APP_ROOT . '/assets/css/select-ui.css') ? filemtime(APP_ROOT . '/assets/css/select-ui.css') : '1'))) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/file-ui.css?v=' . (is_file(APP_ROOT . '/assets/css/file-ui.css') ? filemtime(APP_ROOT . '/assets/css/file-ui.css') : '1'))) ?>">
<script><?php echo supabase_client_config(); ?></script>
</head>
<body class="<?= e($bodyClass) ?>" data-nav="<?= e($currentNav) ?>" data-motion="auto" data-csrf="<?= e(csrf_token()) ?>">

<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e(base_url('index.php')) ?>" aria-label="<?= e(APP_NAME) ?> home">
      <?= logo_svg(34) ?>
      <span class="brand-name">Nerasky</span>
    </a>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>

    <nav class="main-nav" id="mainNav" aria-label="Main">
      <a class="nav-link<?= $currentNav === 'browse' ? ' active' : '' ?>" href="<?= e(base_url('browse.php')) ?>">Browse</a>
      <a class="nav-link<?= $currentNav === 'guide' ? ' active' : '' ?>" href="<?= e(base_url('guide.php')) ?>">Guide</a>
      <a class="nav-link<?= $currentNav === 'about' ? ' active' : '' ?>" href="<?= e(base_url('about.php')) ?>">About</a>
      <?php if (!$me): ?><a class="nav-link nav-admin-recruit" href="<?= e(base_url('support.php')) ?>">Contact Nerasky</a><?php endif; ?>

      <?php if ($me): ?>
        <span class="nav-account-group" aria-label="Your workspace">
        <a class="nav-link<?= $currentNav === 'dashboard' ? ' active' : '' ?>" href="<?= e(base_url('dashboard.php')) ?>">Dashboard</a>
        <a class="nav-link<?= $currentNav === 'messages' ? ' active' : '' ?>" href="<?= e(base_url('messages.php')) ?>">
          Messages<?php if ($navMsgUnread > 0): ?><span class="nav-badge"><?= min(9, $navMsgUnread) ?><?= $navMsgUnread > 9 ? '+' : '' ?></span><?php endif; ?>
        </a>
        <a class="nav-link<?= $currentNav === 'orders' ? ' active' : '' ?>" href="<?= e(base_url('orders.php')) ?>">Orders</a>
        <a class="nav-link<?= $currentNav === 'wallet' ? ' active' : '' ?>" href="<?= e(base_url('wallet.php')) ?>">Wallet</a>
        <a class="nav-link<?= $currentNav === 'premium' ? ' active' : '' ?>" href="<?= e(base_url('premium.php')) ?>"><?= is_premium($me) ? '★ Pro' : '★ Go Pro' ?></a>
        </span>

        <a class="btn btn-primary btn-sm btn-sell" href="<?= e(base_url('sell.php')) ?>">+ Post a service</a>

        <!-- notification bell -->
        <div class="notif-menu">
          <button class="icon-btn notif-btn" id="notifBtn" aria-haspopup="true" aria-expanded="false" aria-label="Notifications">
            <?= ui_icon('bell', 18) ?>
            <?php if ($navNotifUnread > 0): ?><span class="notif-dot"><?= min(9, $navNotifUnread) ?><?= $navNotifUnread > 9 ? '+' : '' ?></span><?php endif; ?>
          </button>
          <div class="notif-drop" id="notifDrop">
            <div class="notif-head">
              <b>Notifications</b>
              <span class="flex gap-1">
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('notifications.php')) ?>">View all</a>
                <?php if ($navNotifUnread > 0): ?><button class="btn btn-ghost btn-sm" id="notifMarkAll" type="button">Mark all read</button><?php endif; ?>
              </span>
            </div>
            <div class="notif-list">
              <?php if (count($navNotifs) > 0): ?>
                <?php foreach ($navNotifs as $n): ?>
                  <a class="notif-item<?= $n['is_read'] ? '' : ' unread' ?>" href="<?= e(base_url($n['link'])) ?>">
                    <span class="notif-ic"><?= notif_icon((string) $n['kind']) ?></span>
                    <span class="notif-item-main">
                      <span><?= e(mb_strimwidth((string) $n['text'], 0, 90, '…')) ?></span>
                      <small><?= time_ago((string) $n['created_at']) ?></small>
                    </span>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="empty-state" style="padding:1.6rem 1rem">
                  <div class="es-ic"><?= ui_icon('bell', 26) ?></div>
                  <b>All caught up</b>
                  <p class="muted">Notifications for messages, payments and orders land here.</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="user-menu">
          <button class="user-menu-btn" id="userMenuBtn" aria-haspopup="true" aria-expanded="false">
            <?= avatar_of($me, 34) ?>
            <?php if ($me['role'] === 'admin'): ?><span class="admin-dot" title="Admin"></span><?php endif; ?>
          </button>
          <div class="user-menu-drop" id="userMenuDrop">
            <div class="user-menu-head">
              <strong><?= e($me['full_name']) ?></strong>
              <span><?= e($me['email']) ?></span>
            </div>
            <a href="<?= e(base_url('profile.php?id=' . $me['id'])) ?>">My public profile</a>
            <a href="<?= e(base_url('dashboard.php')) ?>">Dashboard</a>
            <a href="<?= e(base_url('messages.php')) ?>">Messages<?= $navMsgUnread > 0 ? ' (' . $navMsgUnread . ')' : '' ?></a>
            <a href="<?= e(base_url('wallet.php')) ?>">Wallet</a>
            <a href="<?= e(base_url('premium.php')) ?>"><?= ui_icon('sparkle', 13) ?> <?= is_premium($me) ? 'Manage Pro' : 'Go Pro' ?></a>
            <?php if ($me['role'] === 'admin' && ADMIN_SITE_URL !== ''): ?><a href="<?= e(ADMIN_SITE_URL) ?>" target="_blank" rel="noopener"><?= ui_icon('shield', 14) ?> Admin</a><?php endif; ?>
            <div class="menu-sep"></div>
            <a class="danger" href="<?= e(base_url('logout.php')) ?>">Sign out</a>
          </div>
        </div>
      <?php else: ?>
        <a class="nav-link" href="<?= e(base_url('login.php')) ?>">Log in</a>
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('register.php')) ?>">Sign up free</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="site-main">
<?php if ($__flashes !== []): ?>
  <div class="toast-stack" aria-live="polite">
    <?php foreach ($__flashes as $f): ?>
      <div class="toast toast-<?= e($f['kind']) ?>" role="status">
        <span class="toast-ic"><?php
          $fk = $f['kind'] ?? 'info';
          echo $fk === 'ok' ? ui_icon('check', 16) : ($fk === 'bad' ? ui_icon('x', 16) : ($fk === 'warn' ? ui_icon('alert', 16) : ui_icon('info', 16)));
        ?></span>
        <span><?= e($f['msg']) ?></span>
        <button class="toast-x" aria-label="Dismiss">&times;</button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>