<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$code = (int) ($_GET['code'] ?? 404);
$copy = match ($code) {
    403 => ['Access needs a different key.', 'You do not have permission to open this page or file.'],
    500 => ['We hit a small roadblock.', 'Nerasky could not finish that request. Please try again in a moment.'],
    default => ['That page took a different route.', 'The link may be outdated, or the page may have moved.'],
};
http_response_code($code);
$pageTitle = (string) $code . ' · Nerasky';
$currentNav = '';
require __DIR__ . '/includes/header.php';
?>
<section class="error-page">
  <div class="error-orbit" aria-hidden="true"></div>
  <div class="container error-card reveal">
    <span class="error-code"><?= $code ?></span>
    <span class="eyebrow">Nerasky system message</span>
    <h1><?= e($copy[0]) ?></h1>
    <p><?= e($copy[1]) ?></p>
    <div class="error-actions">
      <a class="btn btn-primary" href="<?= e(base_url('index.php')) ?>">Back home</a>
      <a class="btn btn-outline" href="<?= e(base_url('browse.php')) ?>">Browse services</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
