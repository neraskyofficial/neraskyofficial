<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$me = require_admin();
if (admin_role($me) !== 'super_admin') {
    http_response_code(403);
    exit('<!doctype html><meta charset="utf-8"><title>Access denied</title><p style="font-family:sans-serif;padding:2rem">Only a Super admin can view the audit log.</p>');
}

$action = (string) ($_GET['action'] ?? '');
$entity = (string) ($_GET['entity'] ?? '');
$admin = (int) ($_GET['admin'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

$total = audit_log_count($action ?: null, $entity ?: null, $admin ?: null);
$logs = get_audit_log($action ?: null, $entity ?: null, $admin ?: null, $limit, $offset);
$pages = (int) ceil($total / $limit);

$pageTitle = 'Audit Log';
require __DIR__ . '/header.php';
?>

<section class="page-head" style="background:var(--dark);padding:2.6rem 0 2rem">
  <div class="container">
    <div style="max-width:100%">
      <span class="eyebrow" style="color:#a5b4fc">Compliance</span>
      <h1 style="color:#fff;font-size:1.9rem;margin-bottom:.5rem">Audit log</h1>
      <p style="color:#8b93b8;margin:0">Track all admin actions for security and compliance.</p>
    </div>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <?php echo admin_subnav('index'); ?>

    <div class="panel">
      <div class="panel-head">
        <h3>Admin actions</h3>
      </div>

      <form method="get" action="<?= e(base_url('audit.php')) ?>" class="form-grid-2" style="gap:1rem;margin-bottom:1.5rem">
        <div>
          <label for="action" style="display:block;font-size:var(--fs-sm);color:var(--muted);margin-bottom:.35rem">Action</label>
          <select name="action" id="action" class="input">
            <option value="">All actions</option>
            <option value="verify" <?= $action === 'verify' ? 'selected' : '' ?>>Verify</option>
            <option value="reject" <?= $action === 'reject' ? 'selected' : '' ?>>Reject</option>
            <option value="approve" <?= $action === 'approve' ? 'selected' : '' ?>>Approve</option>
            <option value="mark_paid" <?= $action === 'mark_paid' ? 'selected' : '' ?>>Mark paid</option>
            <option value="update_notes" <?= $action === 'update_notes' ? 'selected' : '' ?>>Update notes</option>
            <option value="change_role" <?= $action === 'change_role' ? 'selected' : '' ?>>Change role</option>
            <option value="deactivate" <?= $action === 'deactivate' ? 'selected' : '' ?>>Deactivate</option>
          </select>
        </div>
        <div>
          <label for="entity" style="display:block;font-size:var(--fs-sm);color:var(--muted);margin-bottom:.35rem">Entity</label>
          <select name="entity" id="entity" class="input">
            <option value="">All entities</option>
            <option value="user" <?= $entity === 'user' ? 'selected' : '' ?>>User</option>
            <option value="service" <?= $entity === 'service' ? 'selected' : '' ?>>Service</option>
            <option value="payment" <?= $entity === 'payment' ? 'selected' : '' ?>>Payment</option>
            <option value="withdrawal" <?= $entity === 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
            <option value="application" <?= $entity === 'application' ? 'selected' : '' ?>>Application</option>
            <option value="order" <?= $entity === 'order' ? 'selected' : '' ?>>Order</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="align-self:flex-end">Filter</button>
        <?php if ($action || $entity || $admin): ?>
          <a href="<?= e(base_url('audit.php')) ?>" class="btn btn-ghost" style="align-self:flex-end">Clear</a>
        <?php endif; ?>
      </form>

      <?php if ($logs): ?>
        <table class="table">
          <thead>
            <tr>
              <th style="text-align:left">Time</th>
              <th style="text-align:left">Admin</th>
              <th style="text-align:left">Action</th>
              <th style="text-align:left">Entity</th>
              <th style="text-align:left">IP</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
              <?php $admin_user = row('SELECT id, full_name FROM users WHERE id = ?', [(int) $log['admin_id']]); ?>
              <tr>
                <td style="font-size:var(--fs-sm);color:var(--muted)">
                  <span title="<?= e($log['created_at']) ?>"><?= e(time_ago($log['created_at'])) ?></span>
                </td>
                <td style="font-weight:600">
                  <?= $admin_user ? e($admin_user['full_name']) : '(deleted user)' ?>
                </td>
                <td>
                  <span class="chip" style="background:rgb(99 102 241 / 0.1);color:var(--brand-600)">
                    <?= e($log['action']) ?>
                  </span>
                </td>
                <td style="font-size:var(--fs-sm);color:var(--ink-700)">
                  <?= e($log['entity_type']) ?> #<?= (int) $log['entity_id'] ?>
                </td>
                <td style="font-size:var(--fs-xs);color:var(--muted);font-family:monospace">
                  <?= e(substr($log['ip_address'], 0, 20)) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($pages > 1): ?>
          <div class="flex" style="justify-content:center;gap:.5rem;margin-top:1.5rem">
            <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
              <?php if ($p === $page): ?>
                <span class="chip" style="background:var(--brand-500);color:#fff;padding:.4rem .8rem"><?= $p ?></span>
              <?php else: ?>
                <a href="<?= e(base_url('audit.php?page=' . $p . ($action ? '&action=' . urlencode($action) : '') . ($entity ? '&entity=' . urlencode($entity) : ''))) ?>" class="btn btn-sm btn-ghost">
                  <?= $p ?>
                </a>
              <?php endif; ?>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <p style="text-align:center;color:var(--muted);padding:2rem">No audit log entries found.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
