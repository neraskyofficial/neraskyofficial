<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$me  = require_admin();
require_admin_area($me, 'overview');
$uid = (int) $me['id'];

$range = (string) ($_GET['range'] ?? '30d');
$allowedRanges = ['today', '7d', '30d', '90d', 'custom'];
$range = in_array($range, $allowedRanges, true) ? $range : '30d';
$from = (string) ($_GET['from'] ?? '');
$to = (string) ($_GET['to'] ?? '');
$dateSql = '';
$dateParams = [];
if ($range === 'today') {
    $dateSql = ' AND created_at >= CURDATE()';
} elseif ($range !== 'custom') {
    $days = (int) substr($range, 0, -1);
    $dateSql = ' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY)';
} elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) && $from <= $to) {
    $dateSql = ' AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $dateParams = [$from, $to];
} else {
    $range = '30d';
    $dateSql = ' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
}
$metric = static function (string $query) use ($dateParams): mixed {
    return scalar($query, $dateParams);
};
$stats = [
    'users'     => (int) $metric('SELECT COUNT(*) FROM users WHERE 1=1' . $dateSql),
    'verified'  => (int) $metric("SELECT COUNT(*) FROM users WHERE account_status = 'verified'" . $dateSql),
    'services'  => (int) $metric("SELECT COUNT(*) FROM services WHERE status = 'active'" . $dateSql),
    'orders'    => (int) $metric('SELECT COUNT(*) FROM orders WHERE 1=1' . $dateSql),
    'completed' => (int) $metric("SELECT COUNT(*) FROM orders WHERE status = 'completed'" . $dateSql),
    'volume'    => (float) $metric("SELECT COALESCE(SUM(price),0) FROM orders WHERE status = 'completed'" . $dateSql),
    'fees'      => (float) $metric("SELECT COALESCE(SUM(platform_fee),0) FROM orders WHERE status = 'completed'" . $dateSql),
    'paid_out'  => (float) $metric("SELECT COALESCE(SUM(seller_payout),0) FROM orders WHERE status = 'completed'" . $dateSql),
    'online'    => (int) scalar("SELECT COUNT(*) FROM users WHERE profile_status = 'online'"),
    'away'      => (int) scalar("SELECT COUNT(*) FROM users WHERE profile_status = 'away'"),
    'dnd'       => (int) scalar("SELECT COUNT(*) FROM users WHERE profile_status = 'dnd'"),
    'offline'   => (int) scalar("SELECT COUNT(*) FROM users WHERE profile_status = 'offline'"),
    'wallets'   => (float) scalar('SELECT COALESCE(SUM(wallet),0) FROM users WHERE role <> "admin"'),
    'reserved'  => (float) scalar("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status = 'pending'"),
    'withdrawn' => (float) scalar("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status = 'paid'"),
];

$counts = admin_counts();
$canFinance = admin_can($me, 'withdrawals');
$attentionTotal = $counts['users'] + $counts['consents'] + $counts['services'] + $counts['payments'];
if ($canFinance) {
    $attentionTotal += $counts['withdrawals'] + $counts['cash_ins'];
}

$newUsers = rows('SELECT id, full_name, email, account_status, is_minor, consent_status, profile_status, profile_note, created_at, profile_frame, profile_accent FROM users ORDER BY id DESC LIMIT 6');

$newPayments = rows(
    'SELECT p.*, o.price, u.full_name, o.buyer_id
     FROM payments p
     JOIN orders o ON o.id = p.order_id
     JOIN users u  ON u.id = p.payer_id
     ORDER BY p.id DESC LIMIT 6'
);
$recentAudit = admin_role($me) === 'super_admin' ? get_audit_log(null, null, null, 5, 0) : [];
$exportUrl = base_url('index.php?range=' . rawurlencode($range) . '&from=' . rawurlencode($from) . '&to=' . rawurlencode($to) . '&export=finance');
if (($_GET['export'] ?? '') === 'finance' && $canFinance) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nerasky-finance-report.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Metric', 'Amount']);
    fputcsv($out, ['Sales volume', number_format($stats['volume'], 2, '.', '')]);
    fputcsv($out, ['Platform fees', number_format($stats['fees'], 2, '.', '')]);
    fputcsv($out, ['Seller payouts', number_format($stats['paid_out'], 2, '.', '')]);
    fputcsv($out, ['Pending withdrawals', number_format($stats['reserved'], 2, '.', '')]);
    fclose($out);
    exit;
}

$pageTitle = 'Overview';
require __DIR__ . '/header.php';
?>
<style>
  .overview-hero { background:linear-gradient(135deg,#0b1025 0%,#151e42 58%,#25206a 100%); color:#fff; padding:2.7rem 0 2.4rem; overflow:hidden; }
  .overview-hero-grid { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:end; gap:2rem; }
  .overview-hero h1 { color:#fff; margin:.45rem 0 .55rem; font-size:clamp(2rem,4vw,3rem); letter-spacing:-.04em; }
  .overview-hero p { max-width:650px; color:#b9c2e3; margin:0; }
  .overview-role { display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .7rem; border:1px solid #4b5a91; border-radius:999px; color:#c7d2fe; font-size:.75rem; font-weight:800; }
  .overview-hero-actions { display:flex; gap:.7rem; flex-wrap:wrap; justify-content:flex-end; }
  .overview-shell { margin-top:-1.1rem; position:relative; }
  .overview-nav { margin-bottom:1.25rem; padding:.85rem 1rem; background:#fff; border:1px solid var(--line); border-radius:var(--r-lg); box-shadow:var(--sh-sm); }
  .overview-nav .admin-subnav { margin:0 !important; }
  .overview-layout { display:grid; grid-template-columns:minmax(0,1fr) 280px; gap:1.25rem; align-items:start; }
  .overview-main { min-width:0; }
  .overview-rail { display:grid; gap:1rem; }
  .overview-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.85rem; margin-bottom:1.25rem; }
  .overview-kpi { display:flex; align-items:center; gap:.8rem; padding:1rem; border:1px solid var(--line); border-radius:var(--r); background:#fff; box-shadow:var(--sh-sm); min-width:0; }
  .overview-kpi .st-ic { flex:0 0 auto; }
  .overview-kpi b { display:block; font-size:1.35rem; line-height:1.1; }
  .overview-kpi small { display:block; margin-top:.25rem; color:var(--muted); font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; }
  .overview-kpi .kpi-link { margin-left:auto; color:var(--brand-600); font-size:.72rem; font-weight:800; }
  .overview-section { margin-bottom:1.25rem; }
  .overview-section .panel-head { padding-bottom:.9rem; }
  .overview-section .panel-head h2, .overview-section .panel-head h3 { margin:0; }
  .overview-section .panel-head h2 { font-size:1.1rem; }
  .overview-section .panel-head p { margin:.25rem 0 0; color:var(--muted); font-size:.78rem; }
  .attention-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.7rem; }
  .attention-card { display:flex; align-items:center; gap:.75rem; padding:.85rem; border:1px solid var(--line); border-radius:var(--r); color:var(--ink-900); background:var(--surface-2); transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease; }
  .attention-card:hover { transform:translateY(-2px); border-color:#c7d2fe; box-shadow:var(--sh-sm); }
  .attention-card .st-ic { width:38px; height:38px; flex:0 0 auto; }
  .attention-card b { display:block; font-size:1.15rem; }
  .attention-card span { display:block; color:var(--muted); font-size:.75rem; }
  .attention-card strong { margin-left:auto; color:var(--brand-600); font-size:.75rem; }
  .overview-health { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.85rem; }
  .overview-health .admin-health-card { margin:0; }
  .overview-rail .panel { margin:0; }
  .rail-callout { padding:1rem; border-radius:var(--r); color:#fff; background:var(--grad-brand); }
  .rail-callout h3 { color:#fff; font-size:1rem; margin:0 0 .35rem; }
  .rail-callout p { color:#e0e7ff; font-size:.78rem; margin:0 0 .8rem; }
  .rail-callout .btn-light { color:var(--brand-700); background:#fff; border-color:#fff; }
  .rail-list { display:grid; gap:.7rem; }
  .rail-list a { display:flex; justify-content:space-between; gap:.6rem; color:var(--ink-900); font-size:.8rem; }
  .rail-list a span { color:var(--muted); text-align:right; }
  .overview-table { overflow:hidden; }
  .overview-table .table-wrap { border:0; box-shadow:none; }
  @media (max-width:1050px) {
    .overview-layout { grid-template-columns:1fr; }
    .overview-rail { grid-template-columns:repeat(2,minmax(0,1fr)); }
  }
  @media (max-width:760px) {
    .overview-hero-grid { grid-template-columns:1fr; align-items:start; }
    .overview-hero-actions { justify-content:flex-start; }
    .overview-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .overview-health { grid-template-columns:1fr; }
    .overview-rail { grid-template-columns:1fr; }
  }
  @media (max-width:520px) {
    .overview-hero { padding:2.1rem 0 2rem; }
    .overview-kpis, .attention-grid { grid-template-columns:1fr; }
    .overview-nav { padding:.7rem; }
    .overview-nav .admin-subnav { overflow-x:auto; flex-wrap:nowrap !important; padding-bottom:.2rem; }
    .overview-nav .admin-subnav a { white-space:nowrap; }
  }
    .overview-filter { display:flex; align-items:end; gap:.55rem; flex-wrap:wrap; padding-top:.8rem; border-top:1px solid var(--line); }
    .overview-filter label { display:flex; flex-direction:column; gap:.2rem; color:var(--muted); font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
    .overview-filter select,.overview-filter input { min-height:34px; padding:.4rem .55rem; border:1px solid var(--line-2); border-radius:9px; color:var(--ink-800); background:var(--surface); font:inherit; text-transform:none; letter-spacing:normal; }
    .overview-filter .btn { align-self:end; }
    .audit-mini-list { display:grid; gap:.1rem; }
    .audit-mini-row { display:flex; align-items:center; gap:.65rem; padding:.65rem 0; border-top:1px solid var(--line); }
    .audit-mini-row:first-child { border-top:0; }
    .audit-mini-dot { width:8px; height:8px; flex:0 0 auto; border-radius:50%; background:var(--brand-500); box-shadow:0 0 0 4px color-mix(in srgb,var(--brand-500) 14%,transparent); }
    .audit-mini-row div { min-width:0; flex:1; }
    .audit-mini-row b,.audit-mini-row span { display:block; font-size:.78rem; }
    .audit-mini-row span { color:var(--muted); }
    .audit-mini-row time { color:var(--muted); font-size:.7rem; white-space:nowrap; }
    @media (max-width:520px) { .overview-filter label { flex:1 1 130px; } .overview-filter .btn { flex:1 1 auto; } }
</style>
<section class="overview-hero">
  <div class="container">
    <div class="overview-hero-grid">
      <div>
        <span class="overview-role"><?= ui_icon('shield', 14) ?> <?= e(admin_role_label(admin_role($me))) ?> workspace</span>
        <h1>Good to see you, <?= e((string) strtok((string) $me['full_name'], ' ')) ?>.</h1>
        <p>Keep the marketplace moving with a focused view of verification, trust, payments, and team operations.</p>
      </div>
      <div class="overview-hero-actions">
        <a class="btn btn-white" href="<?= e(market_url('')) ?>" target="_blank"><?= ui_icon('external') ?> Open marketplace</a>
        <?php if (admin_role($me) === 'super_admin'): ?><a class="btn btn-ghost" style="color:#fff" href="<?= e(base_url('audit.php')) ?>"><?= ui_icon('activity') ?> Audit log</a><?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="section-tight overview-shell">
  <div class="container">
    <div class="overview-nav">
      <?php echo admin_subnav('index'); ?>
      <form class="overview-filter" method="get">
        <label>Dashboard period
          <select name="range" onchange="this.form.submit()">
            <?php foreach (['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days', 'custom' => 'Custom range'] as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $range === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php if ($range === 'custom'): ?>
          <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
          <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
          <button class="btn btn-primary btn-sm" type="submit">Apply</button>
        <?php endif; ?>
        <?php if ($canFinance): ?><a class="btn btn-outline btn-sm" href="<?= e($exportUrl) ?>">Export finance CSV</a><?php endif; ?>
      </form>
    </div>
    <div class="overview-layout">
      <div class="overview-main">
        <div class="overview-kpis">
          <div class="overview-kpi"><div class="st-ic"><?= ui_icon('activity', 20) ?></div><div><b><?= number_format($attentionTotal) ?></b><small>Needs attention</small></div></div>
          <div class="overview-kpi"><div class="st-ic"><?= ui_icon('users', 20) ?></div><div><b><?= number_format($stats['users']) ?></b><small>Total users</small></div><a class="kpi-link" href="<?= e(base_url('users.php')) ?>">Open</a></div>
          <div class="overview-kpi"><div class="st-ic"><?= ui_icon('grid', 20) ?></div><div><b><?= number_format($stats['services']) ?></b><small>Live services</small></div><a class="kpi-link" href="<?= e(base_url('services.php?tab=active')) ?>">View</a></div>
          <div class="overview-kpi"><div class="st-ic"><?= ui_icon('check', 20) ?></div><div><b><?= number_format($stats['completed']) ?></b><small>Completed orders</small></div><a class="kpi-link" href="<?= e(base_url('orders.php')) ?>">View</a></div>
        </div>

        <div class="panel overview-section">
          <div class="panel-head"><div><h2>Priority queue</h2><p>Start with the items that can block users or payments.</p></div><span class="chip"><?= number_format($attentionTotal) ?> open</span></div>
          <div class="attention-grid">
            <a class="attention-card" href="<?= e(base_url('users.php')) ?>"><div class="st-ic"><?= ui_icon('users', 18) ?></div><div><b><?= $counts['users'] ?></b><span>Accounts to verify</span></div><strong>Review →</strong></a>
            <a class="attention-card" href="<?= e(base_url('users.php?tab=consents')) ?>"><div class="st-ic"><?= ui_icon('pen', 18) ?></div><div><b><?= $counts['consents'] ?></b><span>Consent forms</span></div><strong>Review →</strong></a>
            <a class="attention-card" href="<?= e(base_url('services.php')) ?>"><div class="st-ic"><?= ui_icon('grid', 18) ?></div><div><b><?= $counts['services'] ?></b><span>Services to review</span></div><strong>Review →</strong></a>
            <a class="attention-card" href="<?= e(base_url('payments.php')) ?>"><div class="st-ic"><?= ui_icon('card', 18) ?></div><div><b><?= $counts['payments'] ?></b><span>Payments to review</span></div><strong>View →</strong></a>
            <?php if ($canFinance): ?>
              <a class="attention-card" href="<?= e(base_url('withdrawals.php')) ?>"><div class="st-ic"><?= ui_icon('wallet', 18) ?></div><div><b><?= $counts['withdrawals'] ?></b><span>Withdrawals</span></div><strong>Review →</strong></a>
              <a class="attention-card" href="<?= e(base_url('payments.php')) ?>"><div class="st-ic"><?= ui_icon('plus', 18) ?></div><div><b><?= $counts['cash_ins'] ?></b><span>Cash-ins</span></div><strong>Review →</strong></a>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($canFinance): ?>
        <div class="panel admin-finance-panel">
          <div class="panel-head">
            <div><h3>Wallet reconciliation</h3><span class="ph-sub">Internal balances versus manual seller payouts</span></div>
            <a class="btn btn-outline btn-sm" href="<?= e(base_url('withdrawals.php')) ?>">Review payouts →</a>
          </div>
          <div class="admin-finance-grid">
            <div><span>Total user wallet balances</span><b><?= money($stats['wallets']) ?></b><small>Amount currently owed to users</small></div>
            <div><span>Pending withdrawals</span><b><?= money($stats['reserved']) ?></b><small>Reserved and waiting for admin payment</small></div>
            <div><span>Paid withdrawals</span><b><?= money($stats['withdrawn']) ?></b><small>Recorded as manually sent</small></div>
          </div>
          <p class="admin-finance-note">Before marking a withdrawal paid, confirm the external transfer to the seller's selected payout account and keep its transaction reference in your business records.</p>
        </div>
        <?php endif; ?>
        <?php
          $orderProgress = $stats['orders'] > 0 ? min(100, (int) round(($stats['completed'] / $stats['orders']) * 100)) : 0;
          $payoutProgress = $stats['volume'] > 0 ? min(100, (int) round(($stats['paid_out'] / $stats['volume']) * 100)) : 0;
          $userProgress = $stats['users'] > 0 ? min(100, (int) round(($stats['verified'] / $stats['users']) * 100)) : 0;
        ?>
        <div class="overview-health">
          <div class="panel admin-health-card">
            <div class="panel-head"><h3>Marketplace health</h3><span class="muted"><?= $orderProgress ?>% completed</span></div>
            <div class="admin-progress"><span style="width:<?= $orderProgress ?>%"></span></div>
            <div class="admin-health-meta"><span><?= number_format($stats['completed']) ?> completed orders</span><span><?= number_format($stats['orders']) ?> total orders</span></div>
          </div>
          <div class="panel admin-health-card">
            <div class="panel-head"><h3>Income &amp; payouts</h3><span class="muted"><?= $payoutProgress ?>% of volume</span></div>
            <div class="admin-progress admin-progress-green"><span style="width:<?= $payoutProgress ?>%"></span></div>
            <div class="admin-health-meta"><span><?= money($stats['paid_out']) ?> released to seller wallets</span><span><?= money($stats['volume']) ?> sales volume</span></div>
          </div>
          <div class="panel admin-health-card">
            <div class="panel-head"><h3>User verification</h3><span class="muted"><?= $userProgress ?>% verified</span></div>
            <div class="admin-progress admin-progress-cyan"><span style="width:<?= $userProgress ?>%"></span></div>
            <div class="admin-health-meta"><span><?= number_format($stats['verified']) ?> verified</span><span><?= number_format($stats['users']) ?> total users</span></div>
          </div>
        </div>
        <div class="panel admin-presence-panel overview-section">
          <div class="panel-head"><h3>User presence</h3><span class="muted">Current self-selected statuses</span></div>
          <div class="admin-presence-grid">
            <?php foreach (['online' => ['Online', 'green'], 'away' => ['Away', 'amber'], 'dnd' => ['Do not disturb', 'red'], 'offline' => ['Offline', 'gray']] as $key => [$label, $color]): ?>
              <div class="admin-presence-stat admin-presence-stat-<?= $color ?>"><b><?= number_format($stats[$key]) ?></b><span><?= e($label) ?></span></div>
            <?php endforeach; ?>
          </div>
        </div>
<!-- PANELS -->
        <div class="panel overview-section overview-table">
          <div class="panel-head"><h3>Recent signups</h3><a class="btn btn-ghost btn-sm" href="<?= e(base_url('users.php')) ?>">Verify →</a></div>
          <div class="table-wrap" style="border:0;box-shadow:none">
            <table class="tbl">
              <thead><tr><th>Student</th><th>Presence</th><th>Note</th><th>Email</th><th>Minor</th><th>Consent</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($newUsers as $u): ?>
                  <tr>
                    <td><div class="row-title" style="display:flex;align-items:center;gap:.55rem"><?= avatar_of($u, 30) ?><b><a href="<?= e(base_url('users.php?review=' . (int) $u['id'])) ?>"><?= e($u['full_name']) ?></a></b></div></td>
                    <td><?= admin_presence_badge($u) ?></td>
                    <td class="muted" style="max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= trim((string) ($u['profile_note'] ?? '')) !== '' ? e($u['profile_note']) : '—' ?></td>
                    <td class="muted"><?= e($u['email']) ?></td>
                    <td><?= $u['is_minor'] ? '<span class="pill pill-warn">Minor</span>' : '<span class="muted" style="font-size:var(--fs-xs)">Adult</span>' ?></td>
                    <td><?= sb((string) $u['consent_status']) ?></td>
                    <td><?= sb((string) $u['account_status']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($recentAudit !== []): ?>
          <div class="panel overview-section overview-audit">
            <div class="panel-head"><div><h3>Recent sensitive activity</h3><p>Latest actions recorded for the admin team.</p></div><a class="btn btn-ghost btn-sm" href="<?= e(base_url('audit.php')) ?>">View all →</a></div>
            <div class="audit-mini-list">
              <?php foreach ($recentAudit as $log): ?>
                <div class="audit-mini-row"><span class="audit-mini-dot"></span><div><b><?= e((string) $log['action']) ?></b><span><?= e((string) $log['entity_type']) ?><?= (int) $log['entity_id'] > 0 ? ' #' . (int) $log['entity_id'] : '' ?></span></div><time><?= e(time_ago((string) $log['created_at'])) ?></time></div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <div class="panel overview-section overview-table">
          <div class="panel-head"><h3>Payment proofs</h3><a class="btn btn-ghost btn-sm" href="<?= e(base_url('payments.php')) ?>">Review →</a></div>
          <div class="table-wrap" style="border:0;box-shadow:none">
            <table class="tbl">
              <thead><tr><th>Order</th><th>Payer</th><th>Provider</th><th>Amount</th><th>Status</th><th>Submitted</th></tr></thead>
              <tbody>
                <?php foreach ($newPayments as $p): ?>
                  <tr>
                    <td><div class="row-title"><b><a href="<?= e(base_url('payments.php?review=' . (int) $p['order_id'])) ?>">#<?= (int) $p['order_id'] ?></a></b></div></td>
                    <td class="muted"><?= e($p['full_name']) ?></td>
                    <td><?= e($p['provider']) ?></td>
                    <td><b><?= money((float) $p['price']) ?></b></td>
                    <td><?= sb((string) $p['status']) ?></td>
                    <td class="muted" style="font-size:var(--fs-xs)"><?= time_ago((string) $p['created_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <aside class="overview-rail">
        <div class="rail-callout"><h3>Keep the queue healthy</h3><p>Review urgent items before they delay a buyer, seller, or worker application.</p><a class="btn btn-light btn-sm" href="<?= e(base_url('orders.php')) ?>">Open orders</a></div>
        <div class="panel card-pad">
          <div class="panel-head"><h3>Quick links</h3></div>
          <div class="rail-list">
            <a href="<?= e(base_url('orders.php')) ?>">Order operations <span>Open →</span></a>
            <?php if (admin_role($me) === 'super_admin'): ?><a href="<?= e(base_url('staff.php')) ?>">Hiring &amp; staff <span>Open →</span></a><a href="<?= e(base_url('audit.php')) ?>">Audit history <span>Open →</span></a><?php endif; ?>
            <a href="<?= e(base_url('2fa.php')) ?>">Security settings <span>2FA →</span></a>
          </div>
        </div>
        <div class="panel card-pad">
          <div class="panel-head"><h3>Platform snapshot</h3></div>
          <div class="rail-list">
            <a href="<?= e(base_url('orders.php')) ?>">Sales volume <span><?= money($stats['volume']) ?></span></a>
            <a href="<?= e(base_url('orders.php')) ?>">Platform fees <span><?= money($stats['fees']) ?></span></a>
            <a href="<?= e(base_url('users.php')) ?>">Verified users <span><?= number_format($stats['verified']) ?></span></a>
          </div>
        </div>
      </aside>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>