<?php
declare(strict_types=1);

$checkoutPurpose = (string) ($checkoutPurpose ?? 'wallet');
$checkoutOrderId = (int) ($checkoutOrderId ?? 0);
$checkoutButton = (string) ($checkoutButton ?? 'Pay with QR Ph');
$checkoutInputAmount = (bool) ($checkoutInputAmount ?? false);
$checkoutAmount = (float) ($checkoutAmount ?? 0);
?>
<form class="paymongo-checkout-form mt-2"
      data-paymongo-checkout
      data-purpose="<?= e($checkoutPurpose) ?>"
      data-order-id="<?= $checkoutOrderId ?>"
      action="<?= e(base_url('paymongo.php')) ?>"
      method="post">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <?php if ($checkoutInputAmount): ?>
    <div class="field">
      <label for="paymongo_amount">Top-up amount</label>
      <div class="price-wrap"><span class="peso">₱</span><input class="input" type="number" step="0.01" min="100" max="5000" id="paymongo_amount" name="amount" required placeholder="500"></div>
      <small class="hint">₱100–₱5,000 per top-up.</small>
    </div>
  <?php endif; ?>
  <button class="btn btn-primary btn-block" type="submit" data-paymongo-submit <?= PAYMONGO_ENABLED ? '' : 'disabled' ?>>
    <?= e($checkoutButton) ?><?= !$checkoutInputAmount && $checkoutAmount > 0 ? ' · ' . e(price($checkoutAmount)) : '' ?>
  </button>
  <?php if (!PAYMONGO_ENABLED): ?>
    <p class="hint mt-1">QR Ph checkout is not configured yet. Add matching PayMongo test keys and a webhook signing secret in the private server config.</p>
  <?php else: ?>
    <p class="hint mt-1">Secure dynamic QR Ph · exact amount · expires after 30 minutes. Payment is confirmed automatically by PayMongo.</p>
  <?php endif; ?>
  <div class="paymongo-checkout-result" data-paymongo-result aria-live="polite" hidden>
    <p data-paymongo-message class="muted"></p>
    <img data-paymongo-qr alt="PayMongo QR Ph payment code" hidden style="display:block;width:min(100%,320px);margin:1rem auto;border-radius:12px;background:#fff">
  </div>
</form>
<script defer src="<?= e(base_url('assets/js/paymongo-checkout.js?v=' . (is_file(APP_ROOT . '/assets/js/paymongo-checkout.js') ? filemtime(APP_ROOT . '/assets/js/paymongo-checkout.js') : '1'))) ?>"></script>
