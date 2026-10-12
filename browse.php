<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$pageTitle  = 'Browse services';
$currentNav = 'browse';
$browseUser = get_user();

$category  = trim((string) ($_GET['category'] ?? ''));
$q         = trim((string) ($_GET['q'] ?? ''));
$sort      = in_array($_GET['sort'] ?? '', ['new', 'low', 'high'], true) ? (string) $_GET['sort'] : 'new';
$priceMin  = is_numeric($_GET['price_min'] ?? null) ? max(0, (float) $_GET['price_min']) : null;
$priceMax  = is_numeric($_GET['price_max'] ?? null) ? max(0, (float) $_GET['price_max']) : null;
$deliveryMax = filter_var($_GET['delivery_max'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$ratingMin  = is_numeric($_GET['rating_min'] ?? null) ? min(5, max(0, (float) $_GET['rating_min'])) : null;
$verified   = !empty($_GET['verified']);
if (!$browseUser) {
    $sort = 'new';
    $priceMin = null;
    $priceMax = null;
}
if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
    [$priceMin, $priceMax] = [$priceMax, $priceMin];
}
$filterActive = $priceMin !== null || $priceMax !== null || $deliveryMax !== null || $ratingMin !== null || $verified;
$preservedQuery = http_build_query(array_filter([
    'q' => $q, 'price_min' => $priceMin, 'price_max' => $priceMax,
    'delivery_max' => $deliveryMax, 'rating_min' => $ratingMin, 'verified' => $verified ? 1 : null,
], static fn ($value): bool => $value !== null && $value !== ''));
$visibleCategories = [];
if ($category !== '' && in_array($category, CATEGORIES, true)) {
    $visibleCategories[] = $category;
}
$hiddenCategories = array_values(array_diff(CATEGORIES, $visibleCategories));

$where  = ['s.status = "active"'];
$params = [];

if ($category !== '' && in_array($category, CATEGORIES, true)) {
    $where[] = 's.category = ?';
    $params[] = $category;
}
if ($q !== '') {
    $where[] = '(s.title LIKE ? OR s.description LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($priceMin !== null) { $where[] = 's.price >= ?'; $params[] = $priceMin; }
if ($priceMax !== null) { $where[] = 's.price <= ?'; $params[] = $priceMax; }
if ($deliveryMax !== null) { $where[] = 's.delivery_days <= ?'; $params[] = $deliveryMax; }
if ($ratingMin !== null && $ratingMin > 0) {
    $where[] = 'COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.reviewee_id = s.seller_id), 0) >= ?';
    $params[] = $ratingMin;
}
if ($verified) { $where[] = 'u.account_status = "verified"'; }

$order = 'CASE WHEN u.premium_until IS NOT NULL AND u.premium_until > NOW() THEN 0 ELSE 1 END ASC, '
    . match ($sort) {
        'low'  => 's.price ASC',
        'high' => 's.price DESC',
        default => 's.id DESC',
    };

$services = rows(
    'SELECT s.*, u.full_name, u.account_status, u.premium_until, u.profile_frame, u.profile_accent
     FROM services s JOIN users u ON u.id = s.seller_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY ' . $order . ' LIMIT 60',
    $params
);
$savedIds = $browseUser ? saved_service_ids((int) $browseUser['id']) : [];

$recentIds = recently_viewed_service_ids();
$recentServices = [];
if ($recentIds !== []) {
    $marks = implode(',', array_fill(0, count($recentIds), '?'));
    $recentServices = rows(
        'SELECT s.*, u.full_name, u.account_status, u.premium_until, u.profile_frame, u.profile_accent
         FROM services s JOIN users u ON u.id = s.seller_id
         WHERE s.status = "active" AND s.id IN (' . $marks . ')
         ORDER BY FIELD(s.id, ' . $marks . ')',
        array_merge($recentIds, $recentIds)
    );
}

require __DIR__ . '/includes/header.php';
?>

<section class="browse-hero">
  <div class="container browse-hero-inner">
    <div class="browse-hero-copy" data-stagger="85">
      <span class="eyebrow reveal"><?= ui_icon('sparkle', 13) ?> The Nerasky marketplace</span>
      <h1 class="reveal">Find the right help for <span>your next move.</span></h1>
      <p class="reveal">Discover trusted student services for learning, creating, and getting things done — all in one clear, human marketplace.</p>
      <div class="browse-hero-proof reveal"><span><?= ui_icon('shield', 14) ?> Verified students</span><span><?= ui_icon('sparkle', 14) ?> Fair pricing</span><span><?= ui_icon('arrow-right', 14) ?> Simple ordering</span></div>
    </div>
    <div class="browse-hero-orbit reveal reveal-delay-2" aria-hidden="true"><span></span><i><?= ui_icon('search', 28) ?></i><b>Explore<br>something useful</b></div>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <form class="browse-toolbar browse-toolbar-modern reveal" id="marketplace-search" method="get" action="<?= e(base_url('browse.php')) ?>">
      <div class="browse-search-row">
        <div class="search-box">
          <span class="s-ic"><?= ui_icon('search') ?></span>
          <input type="search" name="q" placeholder="Search services, e.g. algebra, resume, python…" value="<?= e($q) ?>">
        </div>
        <label class="browse-sort-label" for="browse-sort-control"><span>Sort by</span><select class="input browse-sort-select" id="browse-sort" name="sort" onchange="this.form.submit()">
          <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest first</option>
          <?php if ($browseUser): ?>
            <option value="low" <?= $sort === 'low' ? 'selected' : '' ?>>Price: low → high</option>
            <option value="high" <?= $sort === 'high' ? 'selected' : '' ?>>Price: high → low</option>
          <?php endif; ?>
        </select></label>
        <?php if ($category !== '' || $q !== '' || $filterActive): ?>
          <a class="btn btn-ghost btn-sm browse-clear" href="<?= e(base_url('browse.php')) ?>">✕ Clear</a>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm browse-search-submit" type="submit"><?= ui_icon('search', 15) ?> Search</button>
      </div>
      <details class="browse-filters" <?= $filterActive ? 'open' : '' ?>>
        <summary>More filters</summary>
        <div class="browse-filter-grid <?= $browseUser ? 'has-price-filters' : 'guest-filters' ?>">
          <?php if ($browseUser): ?>
            <label class="browse-filter-field" for="browse-price-min"><span>Min price</span><input class="input" id="browse-price-min" type="number" name="price_min" min="0" step="0.01" value="<?= $priceMin !== null ? e((string) $priceMin) : '' ?>" placeholder="₱0"></label>
            <label class="browse-filter-field" for="browse-price-max"><span>Max price</span><input class="input" id="browse-price-max" type="number" name="price_max" min="0" step="0.01" value="<?= $priceMax !== null ? e((string) $priceMax) : '' ?>" placeholder="No limit"></label>
          <?php endif; ?>
          <label class="browse-filter-field" for="browse-delivery-max"><span>Delivery within</span><span class="browse-filter-suffix"><input class="input" id="browse-delivery-max" type="number" name="delivery_max" min="1" step="1" value="<?= $deliveryMax !== null ? (int) $deliveryMax : '' ?>" placeholder="Any"><span>days</span></span></label>
          <label class="browse-filter-field" for="rating_min-control"><span>Rating</span><select class="input" id="rating_min" name="rating_min"><option value="">Any rating</option><?php for ($r = 4; $r >= 1; $r--): ?><option value="<?= $r ?>" <?= $ratingMin !== null && $ratingMin === (float) $r ? 'selected' : '' ?>><?= $r ?>★ & up</option><?php endfor; ?></select></label>
          <label class="filter-check"><input type="checkbox" name="verified" value="1" <?= $verified ? 'checked' : '' ?>><span>Verified sellers only</span></label>
          <button class="btn btn-primary btn-sm" type="submit">Apply filters</button>
        </div>
      </details>
    </form>
    <div class="browse-quick-searches" aria-label="Popular searches" data-stagger="55">
      <span class="reveal">Try a popular search</span>
      <a class="reveal" href="<?= e(base_url('browse.php?q=algebra')) ?>">Algebra</a>
      <a class="reveal" href="<?= e(base_url('browse.php?q=resume')) ?>">Resume help</a>
      <a class="reveal" href="<?= e(base_url('browse.php?q=design')) ?>">Design</a>
      <a class="reveal" href="<?= e(base_url('browse.php?q=python')) ?>">Python</a>
    </div>

    <?php if ($recentServices !== []): ?>
      <section class="recently-viewed" aria-labelledby="recent-heading">
        <div class="flex-between mb-2 reveal"><h2 id="recent-heading" style="font-size:1.1rem;margin:0">Recently viewed</h2><span class="muted" style="font-size:var(--fs-xs)">From this session</span></div>
        <div class="recent-grid" data-stagger="70">
          <?php foreach ($recentServices as $recent): $recentRating = seller_rating((int) $recent['seller_id']); ?>
            <a class="card recent-card reveal" href="<?= e(base_url('service.php?id=' . (int) $recent['id'])) ?>">
              <b><?= e($recent['title']) ?></b>
              <span><span class="<?= $browseUser ? '' : 'price-guest-blur' ?>" <?= $browseUser ? '' : 'title="Log in to view the exact price"' ?>><?= e(marketplace_price((float) $recent['price'], $browseUser)) ?></span> · <?= (int) $recent['delivery_days'] ?>d</span>
              <small><?= $recentRating !== null ? star_row($recentRating) . ' ' . number_format($recentRating, 1) : 'New seller' ?></small>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <div class="category-filter category-filter-modern" data-category-filter>
      <div class="category-filter-head reveal">
        <span class="filter-label">Browse by category <small class="category-filter-hint">Choose a category when you need one</small></span>
        <?php if ($hiddenCategories !== []): ?>
          <button class="btn btn-ghost btn-sm category-toggle" type="button" data-category-toggle aria-expanded="false" aria-controls="extraCategories">
            See all categories
          </button>
        <?php endif; ?>
      </div>
      <div class="flex category-chips" style="gap:.5rem;flex-wrap:wrap" data-stagger="45">
      <a class="chip reveal <?= $category === '' ? 'on' : '' ?>" href="<?= e(base_url('browse.php' . ($preservedQuery !== '' ? '?' . $preservedQuery : ''))) ?>">✦ All</a>
      <?php foreach ($visibleCategories as $cat): ?>
        <a class="chip reveal <?= $category === $cat ? 'on' : '' ?>"
           href="<?= e(base_url('browse.php?category=' . urlencode($cat) . ($preservedQuery !== '' ? '&' . $preservedQuery : ''))) ?>">
          <?= cat_icon($cat) ?> <?= e($cat) ?>
        </a>
      <?php endforeach; ?>
      <span class="category-extra" id="extraCategories" hidden>
        <?php foreach ($hiddenCategories as $cat): ?>
          <a class="chip reveal <?= $category === $cat ? 'on' : '' ?>"
             href="<?= e(base_url('browse.php?category=' . urlencode($cat) . ($preservedQuery !== '' ? '&' . $preservedQuery : ''))) ?>">
            <?= cat_icon($cat) ?> <?= e($cat) ?>
          </a>
        <?php endforeach; ?>
      </span>
      </div>
    </div>

    <div class="flex-between mb-2 browse-results-count reveal">
      <p class="muted" style="margin:0;font-size:var(--fs-sm)"><b style="color:var(--ink-900)"><?= count($services) ?></b> service<?= count($services) === 1 ? '' : 's' ?> available</p>
    </div>

    <?php if (count($services) > 0): ?>
      <div class="grid-3 browse-service-grid" data-stagger="75">
        <?php foreach ($services as $sv): $rating = seller_rating((int) $sv['seller_id']); $prem = is_premium($sv); ?>
          <article class="card card-hover service-card reveal<?= premium_card_class($sv) ?>">
            <div class="sv-body">
              <div class="sv-top">
                <span class="sv-cat"><?= cat_icon($sv['category']) ?> <?= e($sv['category']) ?></span>
                <span class="sv-delivery"><?= ui_icon('box') ?> <?= (int) $sv['delivery_days'] ?> day<?= (int) $sv['delivery_days'] > 1 ? 's' : '' ?></span>
              </div>
              <h3><a href="<?= e(base_url('service.php?id=' . (int) $sv['id'])) ?>"><?= e($sv['title']) ?></a></h3>
              <p class="sv-desc" title="<?= e($sv['description']) ?>"><?= e($sv['description']) ?></p>
              <div class="sv-seller">
                <?= avatar_of($sv, 30) ?>
                <span><?= e(member_name($sv)) ?></span>
                <?php if ($prem): ?><?= premium_badge() ?><?php endif; ?>
                <?php if ($rating !== null): ?><small><?= star_row($rating) ?> <?= number_format($rating, 1) ?></small><?php endif; ?>
                <?php if (($sv['account_status'] ?? '') === 'verified'): ?><span class="trust-badge">✓ Verified</span><?php endif; ?>
              </div>
            </div>
            <div class="sv-foot">
              <span class="sv-price<?= $browseUser ? '' : ' price-guest-blur' ?>" <?= $browseUser ? '' : 'title="Log in to view the exact price"' ?>><?= e(marketplace_price((float) $sv['price'], $browseUser)) ?></span>
              <small class="trust-meta"><?= seller_jobs_done((int) $sv['seller_id']) ?> completed</small>
              <a class="btn btn-primary btn-sm" href="<?= e(base_url('service.php?id=' . (int) $sv['id'])) ?>">View service</a>
              <?php if ($browseUser): ?>
                <form method="post" action="<?= e(base_url('service.php?id=' . (int) $sv['id'])) ?>" style="display:inline">
                  <?= csrf_field() ?><input type="hidden" name="return" value="browse">
                  <button class="btn btn-outline btn-sm" type="submit" name="toggle_save" value="1" aria-label="<?= in_array((int) $sv['id'], $savedIds, true) ? 'Remove saved service' : 'Save service' ?>"><?= in_array((int) $sv['id'], $savedIds, true) ? '★' : '☆' ?></button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="card empty-state reveal">
        <div class="es-ic"><?= ui_icon('search') ?></div>
        <b>No matches</b>
        <p class="muted">Nothing fits that search yet. Try different keywords — or post it yourself and be the first to offer it.</p>
        <a class="btn btn-primary" href="<?= e(base_url('sell.php')) ?>">Post this service</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>