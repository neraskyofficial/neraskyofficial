<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$pageTitle  = 'About';
$currentNav = 'about';
$aboutUser  = get_user();
require __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding:4.4rem 0 4.8rem">
  <div class="container">
    <div class="center reveal" style="max-width:760px;margin-inline:auto">
      <span class="hero-badge"><?= ui_icon('sparkle') ?> Our story</span>
      <h1 style="font-size:2.6rem">A better way to share<br><span class="grad-text">what students can do.</span></h1>
      <p class="lead" style="margin-inline:auto">Nerasky brings student skills and student needs together — with clear service listings, in-platform conversations, and a documented order process from payment to delivery.</p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid-2" style="grid-template-columns:1.05fr .95fr;align-items:start">
      <div class="stack reveal">
        <span class="eyebrow">The idea</span>
        <h2>A marketplace built around student trust</h2>
        <p>
          Students can offer tutoring, editing, design, coding help, and more — or find a classmate with the
          right skill. Account and service reviews help build trust, while in-platform messaging keeps
          expectations and order details together.
        </p>
        <p>
          Every order has a visible status and a record of its key steps. Buyers can review delivered work
          before accepting it, and sellers can follow their earnings and wallet activity in one place.
        </p>
      </div>

      <div class="reveal reveal-delay-1" style="background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);padding:1.9rem;box-shadow:var(--sh-md)">
        <span class="eyebrow" style="color:var(--green-600)">Our mission</span>
        <h3 style="font-size:1.3rem">Make student-to-student help safer, clearer, and more accessible.</h3>
        <p class="muted">
          From account review to order completion, Nerasky is designed to make each step easier to understand.
          PayMongo QR Ph payments are confirmed automatically; GCash payments remain available with manual admin verification. Wallet transactions are recorded in each member's account.
        </p>
        <div class="fee-break mt-3">
          <h4 style="font-size:.95rem">Where every peso goes</h4>
          <dl class="kv"><dt>You (the seller)</dt><dd><?= price(95) ?></dd></dl>
          <dl class="kv"><dt>Nerasky platform fee</dt><dd><?= price(5) ?></dd></dl>
          <p class="faint mt-1" style="font-size:.8rem">Standard rate. Eligible Nerasky Pro orders receive the membership rate.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section-tight" style="background:var(--surface)">
  <div class="container">
    <div class="section-head center reveal">
      <span class="eyebrow">What makes us different</span>
      <h2>Four promises, kept in code</h2>
    </div>
    <div class="grid-2">
      <div class="feature-card reveal">
        <div class="f-ic fc-1"><?= ui_icon('cash') ?></div>
        <h3>Student-first pricing</h3>
        <p>The standard platform fee is <b>5%</b>, so sellers keep <b>95%</b> of each completed order. Nerasky Pro offers a lower fee on a limited number of eligible orders each day.</p>
      </div>
      <div class="feature-card reveal reveal-delay-1">
        <div class="f-ic fc-2"><?= ui_icon('shield') ?></div>
        <h3>Safety by design</h3>
        <p>Accounts are <b>manually verified</b> against a submitted ID. Nerasky is open to students <b>15+</b>: ages 15–17 need a <b>signed parental consent form</b> that an admin reviews before they can sell. All chats stay inside the platform — no contact details are ever forced.</p>
      </div>
      <div class="feature-card reveal">
        <div class="f-ic fc-3"><?= ui_icon('search') ?></div>
        <h3>Transparent payments</h3>
        <p>Buyers pay from available wallet funds, PayMongo QR Ph, or the optional manual GCash route. The seller's payout is released after the buyer accepts the delivery.</p>
      </div>
      <div class="feature-card reveal reveal-delay-1">
        <div class="f-ic fc-4"><?= ui_icon('zap') ?></div>
        <h3>Fast QR payments</h3>
        <p>Wallet top-ups and order payments can use PayMongo QR Ph with automatic confirmation, or GCash with moderator review. Choose the payment option shown at checkout.</p>
      </div>
    </div>
  </div>
</section>

<section class="section-tight" style="background:var(--surface)">
  <div class="container">
    <div class="grid-2" style="align-items:stretch">
      <div class="reveal">
        <span class="eyebrow" style="color:#b45309">Membership</span>
        <h2>More visibility, more room to grow.</h2>
        <p class="muted">Nerasky Pro is an affordable membership, payable from your wallet or through PayMongo QR Ph, for students who want their services to be easier to discover while keeping the platform sustainable.</p>
        <a class="btn btn-primary mt-2" href="<?= e(base_url('premium.php')) ?>">Check your membership <?= ui_icon('arrow-right', 16) ?></a>
      </div>
      <div class="card card-pad pro-about-card reveal reveal-delay-1">
        <div class="flex-between mb-2">
          <b style="font-size:1.15rem">★ Nerasky Pro</b>
          <span class="pro-badge lg">Pro</span>
        </div>
        <p class="muted" style="font-size:var(--fs-sm)"><?= price((float) PREMIUM_PRICE) ?> / <?= (int) PREMIUM_DAYS ?> days, paid from your wallet or PayMongo QR Ph.</p>
        <div class="pro-about-feature"><span>✓</span><div><b>3% daily allowance</b><small>Keep 97% on your first <?= (int) PREMIUM_DAILY_DISCOUNTED_ORDERS ?> orders each day. The rate returns to 5% after that and resets tomorrow.</small></div></div>
        <div class="pro-about-feature"><span>✓</span><div><b>Boosted listings</b><small>Your services receive priority placement in Browse and on the landing page.</small></div></div>
        <div class="pro-about-feature"><span>✓</span><div><b>Pro identity</b><small>Stand out with a Pro badge, premium profile frames, layouts, and progress styles.</small></div></div>
        <div class="pro-about-feature"><span>✓</span><div><b>More messaging room</b><small>Send up to <?= (int) PREMIUM_DAILY_MESSAGES ?> messages per day with <?= (int) PREMIUM_MESSAGE_MAX_CHARS ?> characters per message.</small></div></div>
      </div>
    </div>
  </div>
</section>

<section class="dark-band section">
  <div class="container">
    <div class="center reveal" style="max-width:700px;margin-inline:auto">
      <h2 style="font-size:2rem">Student talent deserves real opportunity.</h2>
      <p style="color:#a8b0d0;font-size:1.14rem;margin-bottom:1.8rem">
        Share what you know, find the help you need, and build trust one clear order at a time.
      </p>
      <?php if ($aboutUser): ?>
        <a class="btn btn-primary btn-lg" href="<?= e(base_url('sell.php')) ?>">Post a service</a>
        <a class="btn btn-ghost btn-lg about-dark-cta" href="<?= e(base_url('browse.php')) ?>">Browse services</a>
      <?php else: ?>
        <a class="btn btn-primary btn-lg" href="<?= e(base_url('register.php')) ?>">Join the market</a>
        <a class="btn btn-ghost btn-lg about-dark-cta" href="<?= e(base_url('browse.php')) ?>">See services first</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>