<?php
declare(strict_types=1);
$me = get_user();
?>
</main>

<div class="confirm-backdrop hidden" id="confirmBackdrop" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
  <div class="confirm-dialog">
    <h3 id="confirmTitle">Are you sure?</h3>
    <p id="confirmText">This action cannot be undone.</p>
    <div class="confirm-actions">
      <button class="btn btn-ghost" id="confirmCancel" type="button">Cancel</button>
      <button class="btn btn-primary" id="confirmOk" type="button">Continue</button>
    </div>
  </div>
</div>

<?php if ($me): ?>
  <a class="mobile-fab" href="<?= e(base_url('sell.php')) ?>" aria-label="Post a service"><?= ui_icon('plus', 18) ?><span>Post service</span></a>
  <nav class="mobile-bottom-nav" aria-label="Quick navigation">
    <a class="<?= $currentNav === 'home' ? 'active' : '' ?>" href="<?= e(base_url('index.php')) ?>"<?= $currentNav === 'home' ? ' aria-current="page"' : '' ?>><?= ui_icon('home', 17) ?><span>Home</span></a>
    <a class="<?= $currentNav === 'browse' ? 'active' : '' ?>" href="<?= e(base_url('browse.php')) ?>"<?= $currentNav === 'browse' ? ' aria-current="page"' : '' ?>><?= ui_icon('search', 17) ?><span>Browse</span></a>
    <a class="<?= $currentNav === 'messages' ? 'active' : '' ?>" href="<?= e(base_url('messages.php')) ?>"<?= $currentNav === 'messages' ? ' aria-current="page"' : '' ?>><?= ui_icon('message', 17) ?><span>Messages</span></a>
    <a class="<?= $currentNav === 'orders' ? 'active' : '' ?>" href="<?= e(base_url('orders.php')) ?>"<?= $currentNav === 'orders' ? ' aria-current="page"' : '' ?>><?= ui_icon('box', 17) ?><span>Orders</span></a>
    <a class="<?= $currentNav === 'profile' ? 'active' : '' ?>" href="<?= e(base_url('profile.php?id=' . (int) $me['id'])) ?>"<?= $currentNav === 'profile' ? ' aria-current="page"' : '' ?>><?= ui_icon('users', 17) ?><span>Profile</span></a>
  </nav>
<?php endif; ?>

<footer class="site-footer">
  <div class="footer-aurora" aria-hidden="true"></div>
  <div class="container footer-hero reveal">
    <div class="footer-hero-copy">
      <span class="footer-kicker">Built for student-led work</span>
      <h2>Turn your skills into your next opportunity.</h2>
      <p>Find trusted student services, or start earning from what you already know.</p>
    </div>
    <div class="footer-hero-actions">
      <a class="btn btn-white" href="<?= e(base_url('browse.php')) ?>">Explore services <?= ui_icon('arrow-right', 16) ?></a>
      <a class="footer-text-link" href="<?= e(base_url($me ? 'sell.php' : 'register.php')) ?>"><?= $me ? 'Post a service' : 'Join Nerasky' ?> <?= ui_icon('external', 15) ?></a>
    </div>
  </div>
  <div class="container footer-grid" data-stagger="90">
    <div class="footer-brand reveal">
      <a class="footer-logo-link" href="<?= e(base_url('index.php')) ?>" aria-label="<?= e(APP_NAME) ?> home"><?= logo_svg(40) ?><span>Nerasky</span></a>
      <p class="footer-tag">A marketplace for student talent.</p>
      <p class="footer-note">Built for students who want to share what they know, find trusted help, and create their next opportunity.</p>
      <div class="footer-socials" aria-label="Nerasky social links">
        <a class="footer-social" href="https://www.instagram.com/nerasky_official" aria-label="Nerasky on Instagram" title="Instagram" target="_blank" rel="noopener noreferrer"><?= ui_icon('instagram', 17) ?></a>
        <a class="footer-social" href="https://facebook.com/nerasky" aria-label="Nerasky on Facebook" title="Facebook" target="_blank" rel="noopener noreferrer"><?= ui_icon('facebook', 17) ?></a>
        <a class="footer-social" href="<?= e(base_url('support.php')) ?>" aria-label="Contact Nerasky support" title="Support"><?= ui_icon('mail', 17) ?></a>
      </div>
    </div>
    <div class="footer-column reveal reveal-delay-1">
      <h4>Discover</h4>
      <a href="<?= e(base_url('browse.php')) ?>">Browse services</a>
      <a href="<?= e(base_url('about.php')) ?>">How it works</a>
      <a href="<?= e(base_url('guide.php#faq')) ?>">FAQ &amp; support</a>
      <a href="<?= e(HIRING_SITE_URL) ?>">Careers at Nerasky</a>
    </div>
    <div class="footer-column reveal reveal-delay-2">
      <h4>For students</h4>
      <a href="<?= e(base_url('sell.php')) ?>">Offer a skill</a>
      <a href="<?= e(base_url('premium.php')) ?>">Nerasky Pro</a>
      <?php if (!$me): ?><a href="<?= e(base_url('support.php')) ?>">Contact Nerasky</a><?php endif; ?>
    </div>
    <div class="footer-column reveal reveal-delay-3">
      <h4><?= $me ? 'Your account' : 'Get started' ?></h4>
      <?php if ($me): ?>
        <a href="<?= e(base_url('dashboard.php')) ?>">Dashboard</a>
        <a href="<?= e(base_url('orders.php')) ?>">Orders</a>
        <a href="<?= e(base_url('wallet.php')) ?>">Wallet</a>
      <?php else: ?>
        <a href="<?= e(base_url('login.php')) ?>">Log in</a>
        <a href="<?= e(base_url('register.php')) ?>">Create an account</a>
      <?php endif; ?>
    </div>
    <div class="footer-column footer-trust-column reveal reveal-delay-3">
      <h4>Trust &amp; safety</h4>
      <div class="footer-trust-card footer-card-pop">
        <p class="footer-safe"><span class="footer-safe-icon"><?= ui_icon('shield', 13) ?></span> Manual account verification</p>
        <p class="footer-safe"><span class="footer-safe-icon"><?= ui_icon('check', 13) ?></span> PayMongo confirmation &amp; GCash review</p>
        <p class="footer-safe"><span class="footer-safe-icon"><?= ui_icon('sparkle', 13) ?></span> Sellers keep 95%</p>
        <a class="footer-safe-link" href="<?= e(base_url('privacy.php')) ?>">Read our privacy promise <span class="footer-safe-arrow"><?= ui_icon('arrow-right', 13) ?></span></a>
      </div>
    </div>
  </div>
  <div class="container footer-bottom reveal">
    <p>© <?= date('Y') ?> <?= e(APP_NAME) ?>. Made for student ambition.</p>
    <div class="footer-legal">
      <a href="<?= e(base_url('terms.php')) ?>">Terms</a>
      <a href="<?= e(base_url('privacy.php')) ?>">Privacy</a>
      <a href="<?= e(base_url('refund.php')) ?>">Refunds &amp; disputes</a>
    </div>
  </div>
</footer>

<script src="<?= e(base_url('assets/js/select-ui.js?v=' . (is_file(APP_ROOT . '/assets/js/select-ui.js') ? filemtime(APP_ROOT . '/assets/js/select-ui.js') : '1'))) ?>"></script>
<script src="<?= e(base_url('assets/js/file-ui.js?v=' . (is_file(APP_ROOT . '/assets/js/file-ui.js') ? filemtime(APP_ROOT . '/assets/js/file-ui.js') : '1'))) ?>"></script>
<script src="<?= e(base_url('assets/js/anim.js?v=' . (is_file(APP_ROOT . '/assets/js/anim.js') ? filemtime(APP_ROOT . '/assets/js/anim.js') : '1'))) ?>" defer></script>
<script src="<?= e(base_url('assets/js/main.js?v=' . (is_file(APP_ROOT . '/assets/js/main.js') ? filemtime(APP_ROOT . '/assets/js/main.js') : '1'))) ?>"></script>
<?php echo supabase_auth_script(); ?>
</body>
</html>