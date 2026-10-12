<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$me = require_user();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $priority = in_array((string) ($_POST['priority'] ?? ''), ['normal', 'high', 'urgent'], true) ? (string) $_POST['priority'] : 'normal';
    if (mb_strlen($subject) < 4 || mb_strlen($body) < 10) {
        $errors[] = 'Add a subject and explain the issue in at least 10 characters.';
    } else {
        $ticketId = insert('INSERT INTO support_tickets (user_id,subject,message,priority) VALUES (?,?,?,?)', [(int) $me['id'], $subject, $body, $priority]);
        notify_admins('support', 'New support ticket #' . $ticketId . ': ' . $subject, 'support.php');
        flash('ok', 'Support ticket submitted.');
        redirect('support.php');
    }
}
$tickets = rows('SELECT * FROM support_tickets WHERE user_id = ? ORDER BY id DESC LIMIT 20', [(int) $me['id']]);
$pageTitle = 'Help and support';
$currentNav = '';
require __DIR__ . '/includes/header.php';
?>
<section class="section support-page"><div class="container" style="max-width:980px">
  <div class="support-hero reveal">
    <div><span class="eyebrow">Help center</span><h1>Clear answers. Human support.</h1><p class="muted">Ask a question or report an order, PayMongo QR Ph or GCash payment, wallet, Pro, or account issue. Your request stays connected to your Nerasky account.</p></div>
    <div class="support-hero-orbit" aria-hidden="true"><?= ui_icon('chat', 30) ?></div>
  </div>
  <div class="support-quick-grid" data-stagger="75">
    <a class="support-quick-card reveal" href="<?= e(base_url('guide.php#faq')) ?>"><span><?= ui_icon('file', 18) ?></span><b>Read the guide</b><small>Find quick answers before opening a ticket.</small></a>
    <a class="support-quick-card reveal" href="<?= e(base_url('refund.php')) ?>"><span><?= ui_icon('shield', 18) ?></span><b>Order protection</b><small>Review refunds, disputes, and safe next steps.</small></a>
    <div class="support-quick-card reveal"><span><?= ui_icon('clock', 18) ?></span><b>Payment or wallet help</b><small>Include the checkout or order number, payment method, amount, and date. If PayMongo charged you but Nerasky did not update, mention that. Never include your password or one-time code.</small></div>
  </div>
  <?php foreach ($errors as $error): ?><div class="banner banner-bad"><span class="b-ic">!</span><?= e($error) ?></div><?php endforeach; ?>
  <form method="post" class="card card-pad support-form reveal">
    <?= csrf_field() ?>
    <div class="grid-2"><div class="field"><label for="subject">Subject</label><input class="input" id="subject" name="subject" required maxlength="160"></div><div class="field"><label for="priority">Priority</label><select class="input" id="priority" name="priority"><option>normal</option><option>high</option><option>urgent</option></select></div></div>
    <div class="field"><label for="body">Message</label><textarea class="input" id="body" name="body" rows="5" required minlength="10" placeholder="For a payment issue, include the checkout/order number, amount, and date. Never include passwords, secret keys, or one-time codes."></textarea></div>
    <button class="btn btn-primary" type="submit">Send to support</button>
  </form>
  <div class="support-tickets-heading mt-4"><div><span class="eyebrow">Your history</span><h2>Your tickets</h2></div><span class="support-status-dot">● Support inbox active</span></div>
  <?php if ($tickets === []): ?><div class="card empty-state"><b>No tickets yet</b><p class="muted">Your support conversations will appear here.</p></div><?php else: ?>
    <div class="stack"><?php foreach ($tickets as $ticket): ?><article class="card card-pad"><div class="flex-between"><b>#<?= (int) $ticket['id'] ?> · <?= e($ticket['subject']) ?></b><?= sb((string) $ticket['status']) ?></div><p><?= nl2br(e((string) $ticket['message'])) ?></p><?php if (!empty($ticket['admin_reply'])): ?><div class="banner banner-good"><b>Support:</b> <?= nl2br(e((string) $ticket['admin_reply'])) ?></div><?php endif; ?></article><?php endforeach; ?></div>
  <?php endif; ?>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
