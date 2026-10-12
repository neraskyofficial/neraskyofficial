<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$pageTitle  = '';
$currentNav = 'home';
$pageMeta   = APP_TAGLINE;

/* Query data defensively so the landing still renders pre-install. */
$featured   = [];
$svcCount   = 0;
$sellerCnt  = 0;
$completes  = 0;
$categoryRecommendations = [];
try {
    $featured  = rows('SELECT s.*, u.full_name, u.consent_status, u.premium_until, u.profile_frame, u.profile_accent
                       FROM services s JOIN users u ON u.id = s.seller_id
                       WHERE s.status = "active"
                       ORDER BY (u.premium_until IS NOT NULL AND u.premium_until > NOW()) DESC, s.id DESC LIMIT 6');
    $svcCount  = (int) scalar('SELECT COUNT(*) FROM services WHERE status = "active"');
    $sellerCnt = (int) scalar('SELECT COUNT(DISTINCT seller_id) FROM services WHERE status = "active"');
    $completes = (int) scalar("SELECT COUNT(*) FROM orders WHERE status = 'completed'");
    $categoryRecommendations = rows(
        'SELECT category, COUNT(*) AS service_count
         FROM services
         WHERE status = "active"
         GROUP BY category
         ORDER BY service_count DESC, category ASC
         LIMIT 4'
    );
} catch (Throwable $e) {
    /* Database not installed yet — sections will show the install CTA. */
}
$homeUser = get_user();
if ($homeUser) {
    try {
        refresh_order_notifications((int) $homeUser['id']);
    } catch (Throwable $e) {
        /* Notifications remain optional when the database is being installed. */
    }
}

require __DIR__ . '/includes/header.php';
?>

<!-- ============================ HERO ============================ -->
<section class="hero">
  <div class="container">
    <div class="hero-copy reveal">
      <span class="hero-badge"><span class="pulse"></span> Built by students · for students</span>
      <h1>Your talent deserves <span class="grad-text">more than just a hobby.</span></h1>
      <svg class="home-title-underline" viewBox="0 0 420 18" preserveAspectRatio="none" aria-hidden="true">
        <path class="home-doodle-line" d="M3 13C74 6 111 8 174 10c62 2 126-1 241-8" />
      </svg>
      <p class="lead">Turn what you love doing into opportunities that pay.</p>
      <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="<?= e(base_url('browse.php')) ?>">Browse services</a>
        <a class="btn btn-white btn-lg" href="<?= e(base_url('sell.php')) ?>">Offer a skill →</a>
      </div>
      <div class="hero-proofline">
        <span><?= ui_icon('shield', 14) ?> A student-first marketplace</span>
        <i aria-hidden="true"></i>
        <span><?= ui_icon('cash', 14) ?> Sellers keep 95% of every order</span>
      </div>

      <div class="hero-stats">
        <div><strong data-count="<?= $svcCount ?>">0</strong><span>Active services</span></div>
        <div><strong data-count="<?= $sellerCnt ?>">0</strong><span>Student sellers</span></div>
        <div><strong>95<span style="font-size:.9rem">%</span></strong><span>Seller keeps earnings</span></div>
        <div><strong>5<span style="font-size:.9rem">%</span></strong><span>Flat platform fee</span></div>
      </div>
    </div>

    <div class="hero-art reveal reveal-delay-2">
      <div class="orbit-blob b1" data-parallax="0.14"></div>
      <div class="orbit-blob b2" data-parallax="0.26"></div>
      <svg class="home-hero-doodle" viewBox="0 0 220 150" fill="none" aria-hidden="true">
        <path class="home-doodle-line" d="M12 92C39 42 86 24 124 45c31 17 35 53 11 70-24 17-62 6-64-16-2-17 21-30 38-21" />
        <path class="home-doodle-line home-doodle-spark" d="m166 27 5 14 14 5-14 5-5 14-5-14-14-5 14-5 5-14Z" />
        <path class="home-doodle-line home-doodle-tail" d="M153 111c17 2 27 9 34 21" />
      </svg>
      <div class="hero-art-stamp"><?= ui_icon('sparkle', 14) ?> A marketplace made for students</div>

      <div class="mock-card mock-main" data-tilt>
        <div class="mc-top"><strong>Illustrative workflow · sample only</strong><span>Not a real order</span></div>
        <div class="mc-top">
          <strong>Example: Algebra tutoring</strong>
          <span class="<?= $homeUser ? '' : 'price-guest-blur' ?>" <?= $homeUser ? '' : 'title="Log in to view the exact price"' ?>><?= e(marketplace_price(250, $homeUser)) ?></span>
        </div>
        <div class="mock-row">
          <span class="m-ic"><?= ui_icon('pen', 15) ?></span>
          <div><b>Buyer</b><small>Example student order</small></div>
        </div>
        <div class="mock-row">
          <span class="m-ic" style="background:rgb(16 185 129/.18);color:#8ee0b8"><?= ui_icon('check', 15) ?></span>
          <div><b>Payment status</b><small>Shown after a payment is confirmed</small></div>
        </div>
        <div class="mock-row">
          <span class="m-ic"><?= ui_icon('zap', 15) ?></span>
          <div><b>Seller works on the order</b><small>Delivery timing is set by the listing</small></div>
          <span class="m-price">95% to seller</span>
        </div>
        <div class="mc-progress"><i></i></div>
      </div>

      <div class="mock-card mock-mini">
        <div class="mc-top"><strong>Example payout</strong><span>Illustrative only</span></div>
        <b style="font-size:1.3rem;color:#fff">₱237.50</b>
        <div class="mt-1">
          <span class="mc-chip amber">Example amount · not a wallet balance</span>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="section-tight role-start-section">
  <div class="container">
    <div class="section-head center reveal">
      <span class="eyebrow">Start with your goal</span>
      <h2>What brings you to Nerasky?</h2>
      <p>Choose a path and get to the right place faster.</p>
    </div>
    <div class="role-start-grid" data-stagger="90">
      <a class="role-start-card reveal" href="<?= e(base_url('browse.php')) ?>">
        <span class="role-start-icon"><?= ui_icon('search', 22) ?></span>
        <span><b>I need help with something</b><small>Find a trusted student for tutoring, design, writing, coding, and more.</small></span>
        <span class="role-start-arrow">Explore services <?= ui_icon('arrow-right', 15) ?></span>
      </a>
      <a class="role-start-card role-start-card-accent reveal" href="<?= e(base_url('sell.php')) ?>">
        <span class="role-start-icon"><?= ui_icon('sparkle', 22) ?></span>
        <span><b>I want to earn from my skills</b><small>Publish a service, meet classmates, and keep 95% of every completed order.</small></span>
        <span class="role-start-arrow">Offer a skill <?= ui_icon('arrow-right', 15) ?></span>
      </a>
    </div>
  </div>
</section>
<?php if ($homeUser && $navNotifUnread > 0): ?>
<section class="section-tight" style="background:var(--surface)">
  <div class="container">
    <div class="section-head flex-between reveal">
      <div><span class="eyebrow">Stay on track</span><h2>Your latest reminders</h2><p>Reviews, service updates and order actions that need your attention.</p></div>
      <a class="btn btn-outline btn-sm" href="#featured">Explore services</a>
    </div>
    <div class="home-notification-grid">
      <?php foreach (array_slice($navNotifs, 0, 3) as $notification): ?>
        <a class="home-notification<?= empty($notification['is_read']) ? ' unread' : '' ?>" href="<?= e(base_url((string) $notification['link'])) ?>">
          <span class="notif-ic"><?= notif_icon((string) $notification['kind']) ?></span>
          <span><b><?= e((string) $notification['text']) ?></b><small><?= e(time_ago((string) $notification['created_at'])) ?></small></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<!-- ====================== HOW IT WORKS ====================== -->
<section class="section">
  <div class="container">
    <div class="section-head center reveal">
      <span class="eyebrow">How it works</span>
      <h2>From skill to peso, in four steps</h2>
      <p>No onboarding maze. Post what you can do, get hired, and get paid — all inside the platform.</p>
    </div>

    <div class="grid-4 home-step-grid how-it-works-timeline" data-stagger="90">
      <div class="step-card reveal">
        <span class="step-num">1</span>
        <div class="s-ic"><?= ui_icon('plus', 24) ?></div>
        <h4>Post a skill</h4>
        <p>Describe what you offer, set your price (min ₱50), and choose a delivery window. Your listing goes up after a quick admin review.</p>
      </div>
      <div class="step-card reveal reveal-delay-1">
        <span class="step-num">2</span>
        <div class="s-ic"><?= ui_icon('shield', 24) ?></div>
        <h4>Get verified</h4>
        <p>Accounts are manually verified so buyers know they are working with a real student. Minors add approved parental consent.</p>
      </div>
      <div class="step-card reveal reveal-delay-2">
        <span class="step-num">3</span>
        <div class="s-ic"><?= ui_icon('chat', 24) ?></div>
        <h4>Get hired</h4>
        <p>A classmate books your service, shares their brief, and pays through the available protected payment flow.</p>
      </div>
      <div class="step-card reveal reveal-delay-3">
        <span class="step-num">4</span>
        <div class="s-ic"><?= ui_icon('cash', 24) ?></div>
        <h4>Get paid</h4>
        <p>Deliver the work, get rated, and the payout lands in your wallet. You keep 95% of every completed order.</p>
      </div>
    </div>
  </div>
</section>

<!-- ======================== CATEGORIES ======================== -->
<section class="section-tight">
  <div class="container">
    <div class="section-head center reveal">
      <span class="eyebrow">Categories</span>
      <h2>What can students help with?</h2>
    </div>
    <div class="grid-4 home-category-grid reveal" data-stagger="55">
      <?php foreach (CATEGORIES as $i => $cat): ?>
        <a class="cat-tile" href="<?= e(base_url('browse.php?category=' . urlencode($cat))) ?>" style="animation-delay:<?= $i * 30 ?>ms">
          <span class="ct-ic"><?= cat_icon($cat) ?></span>
          <b><?= e($cat) ?></b>
          <small>Hire a classmate →</small>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (count($categoryRecommendations) > 0): ?>
<section class="section-tight" style="background:var(--surface)">
  <div class="container">
    <div class="section-head center reveal">
      <span class="eyebrow">A good place to start</span>
      <h2>Recommendations for your next project</h2>
      <p>Explore the categories students are using to learn, collaborate, and get things done.</p>
    </div>
    <div class="grid-4 reveal">
      <?php foreach ($categoryRecommendations as $recommendation): ?>
        <a class="cat-tile" href="<?= e(base_url('browse.php?category=' . urlencode((string) $recommendation['category']))) ?>">
          <span class="ct-ic"><?= cat_icon((string) $recommendation['category']) ?></span>
          <b><?= e((string) $recommendation['category']) ?></b>
          <small><?= (int) $recommendation['service_count'] ?> active service<?= (int) $recommendation['service_count'] === 1 ? '' : 's' ?> →</small>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ==================== WHAT MAKES US DIFFERENT ==================== -->
<section class="section" style="background:var(--surface)"> 
  <div class="container">
    <div class="section-head center reveal">
      <span class="eyebrow">Why Nerasky</span>
      <h2>Different by design</h2>
      <p>Built around one belief: student-to-student help should be safe, affordable and instant.</p>
    </div>
    <div class="grid-4 home-feature-grid" data-stagger="90">
      <div class="feature-card reveal">
        <div class="f-ic fc-1"><?= ui_icon('cash', 22) ?></div>
        <h3>Student-first pricing</h3>
        <p>A flat 5% commission keeps services affordable while sellers keep <b>95%</b> of every completed order.</p>
      </div>
      <div class="feature-card reveal reveal-delay-1">
        <div class="f-ic fc-2"><?= ui_icon('shield', 22) ?></div>
        <h3>Safety by design</h3>
        <p>Accounts are manually verified, minors must submit signed parental consent reviewed by an admin, and chats stay in-platform.</p>
      </div>
      <div class="feature-card reveal reveal-delay-2">
        <div class="f-ic fc-3"><?= ui_icon('eye', 22) ?></div>
        <h3>Transparent payments</h3>
        <p>Every order uses a protected wallet payment. Nothing is marked complete until the buyer accepts the work.</p>
      </div>
      <div class="feature-card reveal reveal-delay-3">
        <div class="f-ic fc-4"><?= ui_icon('lock', 22) ?></div>
        <h3>Works offline</h3>
        <p>Designed with a privacy-first foundation: no tracking pixels, no unnecessary data sharing, and no hidden platform dependencies.</p>
      </div>
    </div>
  </div>
</section>
<!-- ==================== FEATURED SERVICES ==================== -->
<section class="section" id="featured">
  <div class="container">
    <div class="section-head flex-between reveal">
      <div>
        <span class="eyebrow">Featured on Nerasky</span>
        <h2>Featured services</h2>
      </div>
      <a class="btn btn-outline" href="<?= e(base_url('browse.php')) ?>">Browse all →</a>
    </div>

    <?php if (count($featured) > 0): ?>
      <div class="grid-3 home-service-grid" data-stagger="80">
        <?php foreach ($featured as $sv): $rating = seller_rating((int) $sv['seller_id']); ?>
          <article class="card card-hover service-card reveal<?= premium_card_class($sv) ?>">
            <div class="sv-body">
              <div class="sv-top">
                <span class="sv-cat"><?= cat_icon($sv['category']) ?> <?= e($sv['category']) ?></span>
                <span class="sv-delivery"><?= ui_icon('box') ?> <?= (int) $sv['delivery_days'] ?> day<?= (int) $sv['delivery_days'] > 1 ? 's' : '' ?></span>
              </div>
              <h3><a href="<?= e(base_url('service.php?id=' . (int) $sv['id'])) ?>"><?= e($sv['title']) ?></a></h3>
              <p class="sv-desc"><?= e($sv['description']) ?></p>
              <div class="sv-seller">
                <?= avatar_of($sv, 30) ?>
                <span><?= e(member_name($sv)) ?></span>
                <?php if (is_premium($sv)): ?><?= premium_badge() ?><?php endif; ?>
                <?php if ($rating !== null): ?><small><?= star_row($rating) ?> <?= number_format($rating, 1) ?></small><?php endif; ?>
              </div>
            </div>
            <div class="sv-foot">
              <span class="sv-price<?= $homeUser ? '' : ' price-guest-blur' ?>" <?= $homeUser ? '' : 'title="Log in to view the exact price"' ?>><?= e(marketplace_price((float) $sv['price'], $homeUser)) ?></span>
              <a class="btn btn-primary btn-sm" href="<?= e(base_url('service.php?id=' . (int) $sv['id'])) ?>">View &amp; order</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="card empty-state">
        <div class="es-ic"><?= ui_icon('grid') ?></div>
        <b>Nothing here yet</b>
        <p class="muted">There are no active listings yet. Be the first student to share a skill and start the marketplace moving.</p>
        <p><a class="btn btn-ghost" href="<?= e(base_url('sell.php')) ?>">Post a service</a></p>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ==================== MISSION BAND ==================== -->
<section class="dark-band section home-mission">
  <div class="container">
    <div class="grid-2" style="align-items:center">
      <div class="reveal">
        <span class="eyebrow" style="color:#a5b4fc">Our mission</span>
        <h2>More of every order stays with the person doing the work.</h2>
        <p class="muted" style="color:#a8b0d0;font-size:1.06rem">
          Every student has something valuable to offer. Nerasky keeps the platform simple, verifies activity
          with care, and keeps the focus on fair opportunities for the people doing the work.
        </p>
        <div class="flex gap-2 mt-3">
          <?php if ($homeUser): ?>
            <a class="btn btn-primary" href="<?= e(base_url('sell.php')) ?>">Offer your skills</a>
            <a class="btn btn-white" href="<?= e(base_url('browse.php')) ?>">Browse services</a>
          <?php else: ?>
            <a class="btn btn-primary" href="<?= e(base_url('about.php')) ?>">Read the story</a>
            <a class="btn btn-white" href="<?= e(base_url('register.php')) ?>">Join as a student</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="reveal reveal-delay-2" style="background:rgb(255 255 255/.05);border:1px solid var(--dark-line);border-radius:var(--r-lg);padding:1.8rem">
        <div class="kv"><dt>Platform commission</dt><dd>5% flat</dd></div>
        <div class="kv"><dt>Seller share</dt><dd>95% of every order</dd></div>
        <div class="kv"><dt>Account verification</dt><dd>Manual, by humans</dd></div>
        <div class="kv"><dt>Minor participation</dt><dd>Signed parental consent</dd></div>
        <div class="kv"><dt>Payment verification</dt><dd>PayMongo-confirmed QR Ph</dd></div>
        <div class="kv"><dt>Comments &amp; chat</dt><dd>Always inside Nerasky</dd></div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== FINAL CTA ==================== -->
<section class="section home-final-cta">
  <div class="container center">
    <div class="reveal" style="max-width:640px;margin-inline:auto">
      <span class="eyebrow" style="justify-content:center"><?= $homeUser ? 'Keep your momentum going' : 'Ready when you are' ?></span>
      <h2><?= $homeUser ? 'Your next opportunity is waiting.' : 'Have a skill? Someone is looking for it.' ?></h2>
      <p class="muted" style="font-size:1.06rem"><?= $homeUser ? 'Share your skills, find the help you need, and keep everything moving in one place.' : 'Create your profile, explore the marketplace, and take your next step with confidence.' ?></p>
      <div class="flex cta-actions" style="justify-content:center;margin-top:1.6rem">
        <?php if ($homeUser): ?>
          <a class="btn btn-primary btn-lg" href="<?= e(base_url('sell.php')) ?>">Post a service</a>
          <a class="btn btn-outline btn-lg" href="<?= e(base_url('browse.php')) ?>">Browse services</a>
          <a class="btn btn-ghost btn-lg" href="<?= e(base_url('wallet.php')) ?>">Open your wallet</a>
        <?php else: ?>
          <a class="btn btn-primary btn-lg" href="<?= e(base_url('register.php')) ?>">Create free account</a>
          <a class="btn btn-outline btn-lg" href="<?= e(base_url('browse.php')) ?>">Just browse first</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-lg" href="<?= e(base_url('support.php')) ?>">Contact Nerasky</a>
        <a class="btn btn-ghost btn-lg" href="<?= e(HIRING_SITE_URL) ?>">Join our team</a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>