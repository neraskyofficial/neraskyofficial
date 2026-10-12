<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_helpers.php';
$me = require_admin();
require_admin_area($me, 'reports');
$range = (string) ($_GET['range'] ?? '30d');
$days = ['today' => 0, '7d' => 7, '30d' => 30, '90d' => 90][$range] ?? 30;
$date = $days === 0 ? ' AND created_at >= CURDATE()' : ' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY)';
$stats = [
    'volume' => (float) scalar('SELECT COALESCE(SUM(price),0) FROM orders WHERE status="completed"' . $date),
    'fees' => (float) scalar('SELECT COALESCE(SUM(platform_fee),0) FROM orders WHERE status="completed"' . $date),
    'payouts' => (float) scalar('SELECT COALESCE(SUM(seller_payout),0) FROM orders WHERE status="completed"' . $date),
    'orders' => (int) scalar('SELECT COUNT(*) FROM orders WHERE status="completed"' . $date),
];
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nerasky-finance-report.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Metric', 'Amount']);
    foreach ($stats as $key => $value) fputcsv($out, [$key, $value]);
    fclose($out);
    exit;
}
$pageTitle = 'Finance reports';
require __DIR__ . '/header.php';
?>
<section class="page-head">
  <div class="container">
    <span class="eyebrow">Operations</span>
    <h1>Finance reports</h1>
    <p class="muted">Revenue, fees, payouts, and completed orders by period.</p>
  </div>
</section>
<section class="section-tight" style="padding-top:1rem">
  <div class="container">
    <?= admin_subnav('reports') ?>
    <div class="panel card-pad">
      <div class="panel-head">
        <div><h2 style="font-size:1.2rem">Marketplace performance</h2><span class="ph-sub">Review completed activity across the selected period.</span></div>
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('reports.php?range='.$range.'&export=csv')) ?>">Export CSV</a>
      </div>
      <div class="seg"><?php foreach (['today'=>'Today','7d'=>'7 days','30d'=>'30 days','90d'=>'90 days'] as $key=>$label): ?><a class="<?= $range === $key ? 'on' : '' ?>" href="<?= e(base_url('reports.php?range='.$key)) ?>"><?= e($label) ?></a><?php endforeach; ?></div>
      <div class="grid-4 mt-3"><?php foreach ([['Sales volume',$stats['volume']],['Platform fees',$stats['fees']],['Seller payouts',$stats['payouts']],['Completed orders',$stats['orders']]] as $metric): ?><div class="card card-pad"><small class="muted"><?= e($metric[0]) ?></small><h2><?= is_numeric($metric[1]) && $metric[0] !== 'Completed orders' ? money((float) $metric[1]) : number_format((int) $metric[1]) ?></h2></div><?php endforeach; ?></div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
