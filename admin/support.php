<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_helpers.php';
$me = require_admin();
require_admin_area($me, 'support');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $status = in_array((string) ($_POST['status'] ?? ''), ['open','pending','closed'], true) ? (string) $_POST['status'] : 'open';
    q('UPDATE support_tickets SET status=?, admin_reply=?, assigned_to=? WHERE id=?', [$status, trim((string) ($_POST['admin_reply'] ?? '')), (int) $me['id'], (int) $_POST['id']]);
    if ((int) $_POST['report_id'] > 0) {
        $reportStatus = in_array((string) ($_POST['report_status'] ?? ''), ['open','reviewing','resolved','dismissed'], true) ? (string) $_POST['report_status'] : 'reviewing';
        q('UPDATE platform_reports SET status=?, resolution=?, reviewed_by=? WHERE id=?', [$reportStatus, trim((string) ($_POST['resolution'] ?? '')), (int) $me['id'], (int) $_POST['report_id']]);
    }
    redirect('support.php');
}
$tickets = rows('SELECT t.*, u.full_name FROM support_tickets t LEFT JOIN users u ON u.id=t.user_id ORDER BY FIELD(t.status,"open","in_progress","resolved","closed"), t.id DESC');
$reports = rows('SELECT r.*, u.full_name AS reporter_name, target_user.full_name AS target_user_name
                 FROM platform_reports r
                 LEFT JOIN users u ON u.id=r.reporter_id
                 LEFT JOIN users target_user ON r.target_type="user" AND target_user.id=r.target_id
                 ORDER BY FIELD(r.status,"open","reviewing","resolved","dismissed"), r.id DESC');
$pageTitle = 'Support';
require __DIR__ . '/header.php';
?>
<section class="page-head">
  <div class="container">
    <span class="eyebrow">Customer care and trust</span>
    <h1>Support center</h1>
    <p class="muted">Respond to member questions and review trust-and-safety reports.</p>
  </div>
</section>
<section class="section-tight" style="padding-top:1rem">
  <div class="container">
    <?= admin_subnav('support') ?>

    <div class="panel card-pad">
      <div class="panel-head"><div><h2 style="font-size:1.2rem">Support tickets</h2><span class="ph-sub"><?= count($tickets) ?> <?= count($tickets) === 1 ? 'ticket' : 'tickets' ?></span></div></div>
      <?php if ($tickets === []): ?>
        <div class="card empty-state"><b>No support tickets</b><p class="muted">Everything is handled.</p></div>
      <?php else: ?>
        <div class="stack">
          <?php foreach ($tickets as $ticket): ?>
            <form method="post" class="card card-pad">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
              <input type="hidden" name="report_id" value="0">
              <b>#<?= (int) $ticket['id'] ?> · <?= e($ticket['subject']) ?></b>
              <small class="muted"><?= e((string) ($ticket['full_name'] ?? 'Guest')) ?> · <?= e((string) $ticket['priority']) ?></small>
              <p><?= nl2br(e((string) $ticket['message'])) ?></p>
              <label for="ticket-reply-<?= (int) $ticket['id'] ?>">Reply</label>
              <textarea class="input" id="ticket-reply-<?= (int) $ticket['id'] ?>" name="admin_reply" rows="2" placeholder="Reply to the user"><?= e((string) ($ticket['admin_reply'] ?? '')) ?></textarea>
              <div class="flex-between mt-1">
                <select class="input" name="status" aria-label="Status for ticket #<?= (int) $ticket['id'] ?>">
                  <?php foreach (['open','pending','closed'] as $status): ?><option <?= $ticket['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Save</button>
              </div>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel card-pad mt-3">
      <div class="panel-head"><div><h2 style="font-size:1.2rem">User reports</h2><span class="ph-sub"><?= count($reports) ?> <?= count($reports) === 1 ? 'report' : 'reports' ?></span></div></div>
      <?php if ($reports === []): ?>
        <div class="card empty-state"><b>No reports</b><p class="muted">No trust-and-safety reports require review.</p></div>
      <?php else: ?>
        <div class="stack">
          <?php foreach ($reports as $report): ?>
            <form method="post" class="card card-pad">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="0">
              <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
              <input type="hidden" name="report_status" value="resolved">
              <b>#<?= (int) $report['id'] ?> · <?= e($report['target_type']) ?> #<?= (int) $report['target_id'] ?><?= $report['target_type'] === 'user' && $report['target_user_name'] ? ' · ' . e((string) $report['target_user_name']) : '' ?></b>
              <small class="muted"><?= e((string) $report['reason']) ?> · Reported by <?= e((string) ($report['reporter_name'] ?? 'Member')) ?></small>
              <p><?= nl2br(e((string) $report['details'])) ?></p>
              <label for="report-resolution-<?= (int) $report['id'] ?>">Resolution note</label>
              <textarea class="input" id="report-resolution-<?= (int) $report['id'] ?>" name="resolution" rows="2" placeholder="Resolution note"><?= e((string) ($report['resolution'] ?? '')) ?></textarea>
              <button class="btn btn-primary btn-sm mt-1" type="submit">Resolve report</button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
