<?php
declare(strict_types=1);

$gcashQrUrl = $gcashAccount['qr'] !== '' ? base_url($gcashAccount['qr']) : '';
?>
<section class="gcash-payment-card" aria-label="GCash payment details">
  <div class="gcash-payment-copy">
    <span class="gcash-payment-kicker"><?= ui_icon('shield', 14) ?> InstaPay · Manual payment</span>
    <h3>Scan to pay with GCash</h3>
    <p><?= e($gcashInstructions) ?></p>
  </div>
  <div class="gcash-payment-details">
    <?php if ($gcashQrUrl !== ''): ?>
      <a class="gcash-qr-link" href="<?= e($gcashQrUrl) ?>" target="_blank" rel="noopener" aria-label="Open the GCash payment QR at full size">
        <img class="gcash-qr-image" src="<?= e($gcashQrUrl) ?>" alt="InstaPay QR code for the Nerasky GCash recipient">
        <span>Tap to view full size</span>
      </a>
    <?php endif; ?>
    <div class="gcash-recipient">
      <span class="gcash-recipient-label">GCash recipient number</span>
      <strong class="gcash-recipient-number"><?= e($gcashAccount['number']) ?></strong>
      <button class="gcash-copy-button" type="button" data-copy="<?= e($gcashAccount['number']) ?>" aria-label="Copy GCash number">
        <?= ui_icon('copy', 14) ?> Copy number
      </button>
      <span class="gcash-recipient-name">Account name <b><?= e($gcashAccount['name']) ?></b></span>
    </div>
  </div>
</section>
