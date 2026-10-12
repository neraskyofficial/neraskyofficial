<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Finishing sign-in';
$currentNav = 'supabase_callback';
$pageMeta = 'Completing your secure Nerasky sign-in.';
require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="container center" style="max-width:600px;padding-block:4rem">
    <div class="card" id="supabaseCallback" style="padding:2rem">
      <span class="eyebrow">Secure sign-in</span>
      <h1 style="font-size:1.7rem">Finishing up…</h1>
      <p class="muted" data-callback-status>Confirming your e-mail. This should only take a moment.</p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
