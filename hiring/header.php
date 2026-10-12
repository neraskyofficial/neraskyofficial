<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Nerasky Careers';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="index,follow">
  <title><?= e($pageTitle) ?> · Nerasky Careers</title>
  <link rel="icon" type="image/png" href="<?= e(base_url('assets/nerasky-logo.png')) ?>">
  <link rel="apple-touch-icon" href="<?= e(base_url('assets/nerasky-logo.png')) ?>">
  <link rel="stylesheet" href="<?= e(hiring_url('assets/css/style.css')) ?>">
  <style>
    .hiring-site-topbar { position:sticky; top:0; z-index:20; border-bottom:1px solid #e5e7f2; background:rgb(255 255 255 / .94); backdrop-filter:blur(14px); }
    .hiring-site-topbar-inner { display:flex; min-height:64px; align-items:center; justify-content:space-between; gap:1rem; }
    .hiring-site-brand { display:inline-flex; align-items:center; gap:.55rem; color:#151a36; font-size:1.08rem; font-weight:850; letter-spacing:-.02em; white-space:nowrap; }
    .hiring-site-nav { display:flex; align-items:center; justify-content:flex-end; gap:.55rem; }
    .hiring-site-nav .btn { white-space:nowrap; }
    @media (max-width:560px) {
      .hiring-site-topbar-inner { min-height:60px; }
      .hiring-site-brand { font-size:.98rem; }
      .hiring-site-nav .btn:first-child { display:none; }
    }
  </style>
</head>
<body>
<header class="hiring-site-topbar">
  <div class="container hiring-site-topbar-inner">
    <a class="hiring-site-brand" href="<?= e(hiring_url('careers.php')) ?>"><?= logo_svg(30) ?> Nerasky Careers</a>
    <nav class="hiring-site-nav" aria-label="Careers navigation">
      <a class="btn btn-outline btn-sm" style="background:#fff" href="<?= e(market_url('')) ?>">Visit marketplace</a>
      <a class="btn btn-outline btn-sm" style="background:#fff" href="<?= e(hiring_url('status.php')) ?>">Check status</a>
      <a class="btn btn-primary btn-sm" href="<?= e(hiring_url('apply.php')) ?>">Apply now</a>
    </nav>
  </div>
</header>
<main class="admin-main">
