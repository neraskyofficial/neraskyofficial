<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Admin';
$me        = admin_user();
$flashes   = take_flashes();

$favicon = 'data:image/svg+xml,' . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="g" x1="2" y1="2" x2="46" y2="46" gradientUnits="userSpaceOnUse"><stop stop-color="#6366F1"/><stop offset=".55" stop-color="#8B5CF6"/><stop offset="1" stop-color="#EC4899"/></linearGradient></defs><rect x="2" y="2" width="44" height="44" rx="13" fill="url(#g)"/><path d="M14 34V14l16 20V14" stroke="#fff" stroke-width="4.4" stroke-linecap="round" stroke-linejoin="round"/></svg>'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <script>document.documentElement.classList.add('js');</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light">
<title><?= e($pageTitle) ?> · Nerasky Admin</title>
<link rel="icon" href="<?= e($favicon) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css?v=' . (is_file(ADMIN_APP_ROOT . '/assets/css/style.css') ? filemtime(ADMIN_APP_ROOT . '/assets/css/style.css') : '1'))) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/select-ui.css?v=' . (is_file(ADMIN_APP_ROOT . '/assets/css/select-ui.css') ? filemtime(ADMIN_APP_ROOT . '/assets/css/select-ui.css') : '1'))) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/file-ui.css?v=' . (is_file(ADMIN_APP_ROOT . '/assets/css/file-ui.css') ? filemtime(ADMIN_APP_ROOT . '/assets/css/file-ui.css') : '1'))) ?>">
<style>
  body{background:var(--dark)}
  .admin-topbar{background:var(--dark-1);border-bottom:1px solid var(--dark-line);color:#fff}
  .admin-topbar-inner{display:flex;align-items:center;justify-content:space-between;gap:1rem;min-height:60px;flex-wrap:wrap}
  .admin-topbar .brand{color:#fff;font-weight:800;font-size:1.1rem}
  .admin-topbar nav{display:flex;align-items:center;gap:.7rem;flex-wrap:wrap}
  .admin-topbar-user{font-size:var(--fs-xs);color:#a8b0d0;font-weight:700}
  @media (max-width:700px){
    .admin-topbar-inner{align-items:flex-start;padding-top:.7rem;padding-bottom:.7rem}
    .admin-topbar nav{width:100%;justify-content:flex-start;flex-wrap:nowrap;overflow-x:auto;padding-bottom:.2rem;scrollbar-width:none}
    .admin-topbar nav::-webkit-scrollbar{display:none}
    .admin-topbar-user{flex:1 1 100%}
  }
  @media (max-width:430px){
    .admin-topbar .btn{flex:1}
    .admin-topbar nav{gap:.45rem}
  }
  .admin-main{min-height:calc(100vh - 60px);background:var(--bg)}
  .admin-guard{min-height:60vh;display:grid;place-items:center;background:var(--bg)}
  .admin-card{max-width:460px;width:100%;margin:0 auto;background:#fff;border:1px solid var(--line);border-radius:var(--r-lg);padding:2.2rem;box-shadow:var(--sh-lg)}
</style>
</head>
<body data-auto-anim data-csrf="<?= e(csrf_token()) ?>">

<header class="admin-topbar">
  <div class="container admin-topbar-inner">
    <a class="brand" href="<?= e(base_url('index.php')) ?>"><?= logo_svg(30) ?> Nerasky&nbsp;Admin</a>
    <nav>
      <?php if ($me): ?>
        <span class="admin-topbar-user"><?= ui_icon('shield', 14) ?> <?= e($me['full_name']) ?> · <?= e(admin_role_label(admin_role($me))) ?></span>
        <a class="btn btn-ghost btn-sm" style="color:#fff" href="<?= e(base_url('2fa.php')) ?>"><?= ui_icon('lock', 14) ?> 2FA</a>
        <a class="btn btn-outline btn-sm" style="background:#fff" href="<?= e(market_url('')) ?>" target="_blank"><?= ui_icon('external', 14) ?> View marketplace</a>
        <a class="btn btn-ghost btn-sm" style="color:#fff" href="<?= e(base_url('logout.php')) ?>"><?= ui_icon('log-out', 14) ?> Sign out</a>
      <?php else: ?>
        <span class="admin-topbar-user" style="color:#8b93b8">Isolated moderation portal</span>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="admin-main">
<?php if ($flashes !== []): ?>
  <div class="toast-stack" aria-live="polite">
    <?php foreach ($flashes as $f): ?>
      <div class="toast toast-<?= e($f['kind']) ?>" role="status">
        <span class="toast-ic"><?= $f['kind'] === 'ok' ? '✓' : ($f['kind'] === 'bad' ? '✕' : ($f['kind'] === 'warn' ? '!' : 'i')) ?></span>
        <span><?= e($f['msg']) ?></span>
        <button class="toast-x" aria-label="Dismiss">&times;</button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>