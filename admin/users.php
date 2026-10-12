<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$me = require_admin();
require_admin_area($me, 'users');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $userId = (int) ($_POST['user_id'] ?? 0);

    $target = $userId > 0 ? row('SELECT * FROM users WHERE id = ?', [$userId]) : null;
    if (!$target) {
        flash('bad', 'User not found.');
        redirect('users.php');
    }
    if ((string) $target['role'] === 'admin') {
        flash('bad', 'You cannot moderate another admin.');
        redirect('users.php');
    }

    switch ($action) {
        case 'verify':
            q("UPDATE users SET account_status = 'verified' WHERE id = ?", [$userId]);
            audit_log($me['id'], 'verify', 'user', $userId, ['email' => $target['email'], 'full_name' => $target['full_name']]);
            notify($userId, 'account', 'Your account was verified — you can now post services and place orders!', 'dashboard.php');
            flash('ok', 'Account verified for ' . $target['full_name'] . '. Welcome to the market!');
            break;
        case 'reject':
            q("UPDATE users SET account_status = 'rejected' WHERE id = ?", [$userId]);
            audit_log($me['id'], 'reject', 'user', $userId, ['email' => $target['email'], 'full_name' => $target['full_name']]);
            flash('info', 'Account review closed for ' . $target['full_name'] . '.');
            break;
        case 'consent_approve':
            q("UPDATE users SET consent_status = 'approved' WHERE id = ?", [$userId]);
            audit_log($me['id'], 'approve', 'consent', $userId, ['email' => $target['email'], 'full_name' => $target['full_name']]);
            flash('ok', 'Parental consent approved for ' . $target['full_name'] . '.');
            break;
        case 'consent_reject':
            q("UPDATE users SET consent_status = 'rejected' WHERE id = ?", [$userId]);
            audit_log($me['id'], 'reject', 'consent', $userId, ['email' => $target['email'], 'full_name' => $target['full_name']]);
            flash('info', 'Parental consent rejected for ' . $target['full_name'] . '. They can upload a corrected form on registration.');
            break;
        case 'undo':
            q("UPDATE users SET account_status = 'pending' WHERE id = ?", [$userId]);
            audit_log($me['id'], 'undo', 'user', $userId);
            flash('info', 'Account moved back to pending.');
            break;
    }
    redirect('users.php');
}

$tab    = (string) ($_GET['tab'] ?? 'users');
$review = (int) ($_GET['review'] ?? 0);
$reviewUser = null;

if ($review > 0) {
    $reviewUser = row('SELECT * FROM users WHERE id = ?', [$review]);
}

if ($tab === 'wallets') {
    $list = rows(
        "SELECT u.id, u.full_name, u.email, u.wallet, u.profile_frame, u.profile_accent,
                COALESCE((SELECT SUM(o.seller_payout) FROM orders o WHERE o.seller_id = u.id AND o.status = 'completed'), 0) AS lifetime_earned,
                COALESCE((SELECT SUM(w.amount) FROM withdrawals w WHERE w.user_id = u.id AND w.status = 'pending'), 0) AS pending_withdrawals
         FROM users u WHERE u.role <> 'admin' ORDER BY u.wallet DESC, u.id DESC LIMIT 100"
    );
} elseif ($tab === 'consents') {
    $list = rows("SELECT * FROM users WHERE consent_status = 'submitted' ORDER BY id DESC LIMIT 60");
} else {
    $list = rows("SELECT * FROM users WHERE account_status = 'pending' ORDER BY id DESC LIMIT 60");
}

$pageTitle = 'Verify accounts';
require __DIR__ . '/header.php';
?>
<section class="page-head">
  <div class="container">
    <div class="flex-between">
      <div>
        <span class="eyebrow">Moderation</span>
        <h1>Manual verification</h1>
        <p class="muted">Every account and consent form is reviewed by a human. That's the Nerasky difference.</p>
      </div>
      <div class="seg">
        <a href="<?= e(base_url('users.php?tab=users')) ?>" class="<?= $tab === 'users' ? 'on' : '' ?>">Accounts (<?= admin_counts()['users'] ?>)</a>
        <a href="<?= e(base_url('users.php?tab=consents')) ?>" class="<?= $tab === 'consents' ? 'on' : '' ?>">Consent forms (<?= admin_counts()['consents'] ?>)</a>
        <a href="<?= e(base_url('users.php?tab=wallets')) ?>" class="<?= $tab === 'wallets' ? 'on' : '' ?>">Wallet balances</a>
      </div>
    </div>
  </div>
</section>

<section class="section-tight" style="padding-top:1rem">
  <div class="container">
    <div class="dash-grid" style="grid-template-columns:220px 1fr;align-items:start">
      <?php echo admin_subnav('users'); ?>

      <div class="dash-main">
        <?php if ($reviewUser): ?>
          <?php $u = $reviewUser; ?>
          <div class="stack">
            <div class="card card-pad">
              <div class="flex-between mb-2">
                <h2 style="font-size:1.3rem;margin:0"><?= e($u['full_name']) ?></h2>
                <?= sb((string) $u['account_status']) ?>
              </div>
              <dl class="kv"><dt>Email</dt><dd><?= e($u['email']) ?></dd></dl>
              <dl class="kv"><dt>Minor</dt><dd><?= $u['is_minor'] ? 'Yes — consent required' : 'No' ?></dd></dl>
              <dl class="kv"><dt>Consent status</dt><dd><?= sb((string) $u['consent_status']) ?></dd></dl>
              <dl class="kv"><dt>Presence</dt><dd><?= admin_presence_badge($u) ?></dd></dl>
              <dl class="kv"><dt>Profile note</dt><dd><?= trim((string) ($u['profile_note'] ?? '')) !== '' ? e($u['profile_note']) : '<span class="muted">No note</span>' ?></dd></dl>
              <dl class="kv" style="border-bottom:0"><dt>Joined</dt><dd><?= date('M j, Y g:i A', strtotime((string) $u['created_at'])) ?></dd></dl>
            </div>

            <div class="card card-pad">
              <h3 style="font-size:1rem;margin-bottom:.8rem"><?= ui_icon('graduation') ?> Proof of enrollment</h3>
              <?php if ($u['verification_file']): ?>
                <a target="_blank" href="<?= e(admin_document_url((string) $u['verification_file'])) ?>">
                  <img class="photo-thumb-lg" src="<?= e(admin_document_url((string) $u['verification_file'])) ?>" alt="Verification document" onerror="this.outerHTML='<div class=&quot;filebox&quot;><b>Can&apos;t preview</b><a class=&quot;btn btn-sm btn-primary&quot; href=&quot;<?= e(admin_document_url((string) $u['verification_file'])) ?>&quot; target=&quot;_blank&quot;>Open file ↗</a></div>'">
                </a>
              <?php else: ?>
                <p class="muted" style="font-size:var(--fs-sm)">No verification file uploaded.</p>
              <?php endif; ?>
            </div>
<div class="card card-pad">
              <div class="flex-between mb-1">
                <h3 style="font-size:1rem;margin:0"><?= ui_icon('users') ?> Parental consent</h3>
                <?= sb((string) $u['consent_status']) ?>
              </div>
              <?php if ($u['is_minor']): ?>
                <?php if ($u['consent_file']): ?>
                  <a target="_blank" href="<?= e(admin_document_url((string) $u['consent_file'])) ?>">
                    <img class="photo-thumb-lg" src="<?= e(admin_document_url((string) $u['consent_file'])) ?>" alt="Consent document" onerror="this.outerHTML='<div class=&quot;filebox&quot;><b>Can&apos;t preview</b><a class=&quot;btn btn-sm btn-primary&quot; href=&quot;<?= e(admin_document_url((string) $u['consent_file'])) ?>&quot; target=&quot;_blank&quot;>Open file ↗</a></div>'">
                  </a>
                <?php else: ?>
                  <p class="muted" style="font-size:var(--fs-sm)">No consent file uploaded.</p>
                <?php endif; ?>
                <div class="admin-verdict">
                  <?php if ($u['consent_status'] !== 'approved'): ?>
                    <form method="post" action="<?= e(base_url('users.php')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="consent_approve">
                      <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                      <button class="btn btn-primary btn-sm" type="submit">✓ Approve consent</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($u['consent_status'] !== 'rejected'): ?>
                    <form method="post" action="<?= e(base_url('users.php')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="consent_reject">
                      <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit">✕ Reject consent</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <p class="muted" style="font-size:var(--fs-sm)">This student is not a minor — no consent required.</p>
              <?php endif; ?>
            </div>

            <div class="card card-pad">
              <h3 style="font-size:1rem;margin-bottom:.8rem"><?= ui_icon('shield') ?> Account verdict</h3>
              <div class="admin-verdict">
                <?php if ($u['account_status'] === 'pending'): ?>
                  <form method="post" action="<?= e(base_url('users.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-primary" type="submit">✓ Verify account</button>
                  </form>
                  <form method="post" action="<?= e(base_url('users.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-danger" type="submit" data-confirm="Reject this account? They will not be able to log in.">✕ Reject</button>
                  </form>
                <?php elseif ($u['account_status'] === 'verified'): ?>
                  <form method="post" action="<?= e(base_url('users.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="undo">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-ghost" type="submit">↩ Move back to pending</button>
                  </form>
                <?php elseif ($u['account_status'] === 'rejected'): ?>
                  <form method="post" action="<?= e(base_url('users.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="undo">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-ghost" type="submit">↩ Re-open as pending</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>

        <?php elseif ($tab === 'wallets'): ?>
          <?php if (count($list) > 0): ?>
            <div class="table-wrap">
              <table class="tbl admin-wallets-table">
                <thead><tr><th>User</th><th>Wallet balance</th><th>Lifetime earned</th><th>Pending withdrawal</th><th></th></tr></thead>
                <tbody>
                  <?php foreach ($list as $u): ?>
                    <tr>
                      <td data-label="User"><div class="row-title" style="display:flex;align-items:center;gap:.55rem"><?= avatar_of($u, 30) ?><div><b><?= e($u['full_name']) ?></b><small class="muted"><?= e($u['email']) ?></small></div></div></td>
                      <td data-label="Wallet balance"><b><?= money((float) $u['wallet']) ?></b></td>
                      <td data-label="Lifetime earned" class="admin-amount-positive"><?= money((float) $u['lifetime_earned']) ?></td>
                      <td data-label="Pending withdrawal" class="muted"><?= money((float) $u['pending_withdrawals']) ?></td>
                      <td data-label="Action"><a class="btn btn-outline btn-sm" href="<?= e(base_url('withdrawals.php')) ?>">Payouts →</a></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="card empty-state"><div class="es-ic"><?= ui_icon('wallet') ?></div><b>No user wallets</b><p class="muted">Wallet balances will appear here.</p></div>
          <?php endif; ?>
        <?php elseif (count($list) > 0): ?>
          <div class="table-wrap">
            <table class="tbl">
              <thead>
                <tr><th>Student</th><th>Presence</th><th>Note</th><th>Email</th><th>Minor</th><th>Consent</th><th>Joined</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($list as $u): ?>
                  <tr>
                    <td><div class="row-title" style="display:flex;align-items:center;gap:.55rem"><?= avatar_of($u, 30) ?><b><?= e($u['full_name']) ?></b></div></td>
                    <td><?= admin_presence_badge($u) ?></td>
                    <td class="muted" style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= trim((string) ($u['profile_note'] ?? '')) !== '' ? e($u['profile_note']) : '—' ?></td>
                    <td class="muted"><?= e($u['email']) ?></td>
                    <td><?= $u['is_minor'] ? '<span class="pill pill-warn">Minor</span>' : '<span class="muted" style="font-size:var(--fs-xs)">Adult</span>' ?></td>
                    <td><?= sb((string) $u['consent_status']) ?></td>
                    <td class="muted" style="font-size:var(--fs-xs)"><?= time_ago((string) $u['created_at']) ?></td>
                    <td><a class="btn btn-primary btn-sm" href="<?= e(base_url('users.php?review=' . (int) $u['id'])) ?>">Review →</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="card empty-state">
            <div class="es-ic"><?= ui_icon('sparkle') ?></div>
            <b>Queue is clear!</b>
            <p class="muted">No <?= $tab === 'consents' ? 'consent forms' : 'account verifications' ?> waiting right now.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>