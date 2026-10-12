<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

$me = require_admin();
if (admin_role($me) !== 'super_admin') {
    http_response_code(403);
    exit('Only a Super admin can manage staff applications.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $staffUserId = (int) ($_POST['staff_user_id'] ?? 0);
    if ($action === 'set_role' || $action === 'deactivate' || $action === 'activate') {
        if ($staffUserId === (int) $me['id']) {
            flash('bad', 'You cannot change your own Super admin access.');
        } elseif (!$staffUserId || !row("SELECT id FROM users WHERE id = ? AND role = 'admin'", [$staffUserId])) {
            flash('bad', 'That staff account could not be found.');
        } elseif ($action === 'set_role') {
            $role = (string) ($_POST['staff_role'] ?? '');
            if (!in_array($role, ['moderator', 'finance_admin'], true)) {
                flash('bad', 'Choose a valid limited staff role.');
            } else {
                q('UPDATE admin_staff SET staff_role = ?, active = 1 WHERE user_id = ?', [$role, $staffUserId]);
                audit_log($me['id'], 'change_role', 'admin_staff', $staffUserId, ['new_role' => $role]);
                flash('ok', 'Staff role updated.');
            }
        } else {
            $isActivating = $action === 'activate';
            q('UPDATE admin_staff SET active = ? WHERE user_id = ?', [$isActivating ? 1 : 0, $staffUserId]);
            audit_log($me['id'], $isActivating ? 'activate' : 'deactivate', 'admin_staff', $staffUserId);
            flash('ok', $isActivating ? 'Staff access restored.' : 'Staff access deactivated.');
        }
        redirect('staff.php');
    }

    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $note = trim((string) ($_POST['reviewer_notes'] ?? ''));
    $interviewAt = trim((string) ($_POST['interview_at'] ?? ''));
    $interviewAt = $interviewAt !== '' ? str_replace('T', ' ', $interviewAt) . (strlen($interviewAt) === 16 ? ':00' : '') : null;
    $interviewLocation = trim((string) ($_POST['interview_location'] ?? ''));
    $application = row("SELECT * FROM admin_applications WHERE id = ? AND status NOT IN ('approved', 'rejected', 'withdrawn')", [$applicationId]);

    if (!$application) {
        flash('bad', 'That application is no longer available for review.');
    } elseif (in_array($action, ['submitted', 'reviewing', 'interview', 'reject', 'save_notes'], true)) {
        $nextStatus = $action === 'reject' ? 'rejected' : ($action === 'save_notes' ? ($application['status'] === 'pending' ? 'submitted' : $application['status']) : $action);
        q(
            'UPDATE admin_applications SET status = ?, reviewer_notes = ?, interview_at = ?, interview_location = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
            [$nextStatus, $note, $interviewAt, $interviewLocation, $me['id'], $applicationId]
        );
        audit_log($me['id'], $action, 'application', $applicationId, ['status' => $nextStatus]);
        flash($nextStatus === 'rejected' ? 'info' : 'ok', $nextStatus === 'rejected' ? 'Application rejected.' : 'Application review updated.');
    } elseif ($action === 'approve') {
        $email = (string) ($application['contact_email'] ?: $application['email']);
        $birthDateValue = (string) ($application['date_of_birth'] ?? '');
        $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDateValue);
        $birthDateErrors = DateTimeImmutable::getLastErrors();
        $birthDateValid = $birthDate !== false
            && ($birthDateErrors === false || ($birthDateErrors['warning_count'] === 0 && $birthDateErrors['error_count'] === 0))
            && $birthDate->format('Y-m-d') === $birthDateValue;
        $age = $birthDate ? (int) $birthDate->diff(new DateTimeImmutable('today'))->y : 0;
        $minimumExperience = $application['requested_role'] === 'finance_admin' ? 80 : 40;
        if (!in_array((string) $application['requested_role'], ['moderator', 'finance_admin'], true)) {
            flash('bad', 'That application requests an unsupported staff role.');
        } elseif (!$application['resume_file'] || !$application['reference_contact'] || !(int) ($application['applicant_declaration'] ?? 0) || mb_strlen((string) $application['experience']) < $minimumExperience || mb_strlen((string) $application['motivation']) < 40 || mb_strlen((string) ($application['cover_letter'] ?? '')) < 80 || !$birthDateValid || $birthDate > new DateTimeImmutable('today') || $age < 20 || row('SELECT id FROM users WHERE email = ?', [$email])) {
            flash('bad', 'The application does not meet the worker requirements or the contact email is already in use.');
        } else {
            $inviteToken = bin2hex(random_bytes(32));
            $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            $db = db();
            $db->beginTransaction();
            try {
                insert(
                    'INSERT INTO users (full_name, email, password_hash, role, account_status, verification_file, date_of_birth, id_type) VALUES (?,?,?,?,?,?,?,?)',
                    [$application['full_name'], $email, $passwordHash, 'admin', 'verified', $application['verification_file'], $application['date_of_birth'], 'government']
                );
                $account = row('SELECT id FROM users WHERE email = ?', [$email]);
                if (!$account) {
                    throw new RuntimeException('Approved worker account was not created.');
                }
                q("INSERT INTO admin_staff (user_id, staff_role, active) VALUES (?,?,1)", [$account['id'], $application['requested_role']]);
                q('INSERT INTO admin_invites (user_id, token_hash, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 48 HOUR))', [$account['id'], hash('sha256', $inviteToken)]);
                q("UPDATE admin_applications SET status = 'approved', reviewer_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?", [$note, $me['id'], $applicationId]);
                audit_log($me['id'], 'approve', 'application', $applicationId, ['new_user_id' => $account['id'], 'role' => $application['requested_role'], 'email' => $email]);
                $db->commit();
                notify((int) $account['id'], 'account', 'Your Nerasky admin application was approved. You can now use the admin portal.', ADMIN_BASE_URL . '/');
                flash('ok', 'Application approved. Send the worker this one-time setup link: ' . ADMIN_BASE_URL . '/invite.php?token=' . $inviteToken);
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                flash('bad', 'Could not approve this application. No access was granted.');
            }
        }
    }
    redirect('staff.php');
}

$applications = rows('SELECT a.*, u.account_status, u.role AS account_role FROM admin_applications a LEFT JOIN users u ON u.email = a.email ORDER BY a.id DESC LIMIT 100');
$staff = rows("SELECT u.id, u.full_name, u.email, s.staff_role, s.active, s.created_at
               FROM admin_staff s JOIN users u ON u.id = s.user_id
               WHERE u.role = 'admin' ORDER BY s.active DESC, u.full_name ASC");
$pageTitle = 'Staff applications';
require __DIR__ . '/header.php';
?>
<section class="page-head">
  <div class="container">
    <span class="eyebrow">Super admin</span>
    <h1>Staff applications</h1>
    <p class="muted">Review worker applications and issue access only after approval. Limited roles never receive tools outside their assigned work.</p>
  </div>
</section>
<section class="section-tight" style="padding-top:1rem">
  <div class="container">
    <?= admin_subnav('staff') ?>
    <div class="panel card-pad" style="margin-bottom:1.2rem">
      <div class="panel-head"><div><h2 style="font-size:1.2rem">Active staff access</h2><span class="ph-sub">Only the primary Super admin can change these assignments.</span></div></div>
      <?php if ($staff === []): ?>
        <p class="muted" style="margin:0">No limited staff accounts have been approved yet.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Staff member</th><th>Role</th><th>Status</th><th>Access</th></tr></thead>
            <tbody>
              <?php foreach ($staff as $member): ?>
                <tr>
                  <td><b><?= e($member['full_name']) ?></b><small class="muted"><?= e($member['email']) ?></small></td>
                  <td>
                    <?php if ((int) $member['id'] === (int) $me['id']): ?>
                      <b>Super admin</b>
                    <?php else: ?>
                      <form method="post" class="flex" style="gap:.4rem;align-items:center"><?= csrf_field() ?><input type="hidden" name="action" value="set_role"><input type="hidden" name="staff_user_id" value="<?= (int) $member['id'] ?>"><select class="input" name="staff_role" aria-label="Role for <?= e($member['full_name']) ?>"><option value="moderator" <?= $member['staff_role'] === 'moderator' ? 'selected' : '' ?>>Moderator</option><option value="finance_admin" <?= $member['staff_role'] === 'finance_admin' ? 'selected' : '' ?>>Finance admin</option></select><button class="btn btn-outline btn-sm" type="submit">Save</button></form>
                    <?php endif; ?>
                  </td>
                  <td><?= (int) $member['active'] === 1 ? '<span class="pill pill-ok">Active</span>' : '<span class="pill pill-muted">Inactive</span>' ?></td>
                  <td>
                    <?php if ((int) $member['id'] !== (int) $me['id']): ?>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= (int) $member['active'] === 1 ? 'deactivate' : 'activate' ?>"><input type="hidden" name="staff_user_id" value="<?= (int) $member['id'] ?>"><button class="btn btn-sm <?= (int) $member['active'] === 1 ? 'btn-danger' : 'btn-outline' ?>" type="submit"><?= (int) $member['active'] === 1 ? 'Deactivate' : 'Reactivate' ?></button></form>
                    <?php else: ?>
                      <span class="muted">Owner account</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($applications === []): ?>
      <div class="card empty-state"><div class="es-ic"><?= ui_icon('users') ?></div><b>No applications yet</b><p class="muted">Approved staff will appear here after someone applies.</p></div>
    <?php else: ?>
      <div class="stack">
        <?php foreach ($applications as $application): ?>
          <?php
            $applicationBirthDate = date_create_immutable((string) ($application['date_of_birth'] ?? ''));
            $applicationAge = $applicationBirthDate ? (int) $applicationBirthDate->diff(new DateTimeImmutable('today'))->y : 0;
            $applicationEmail = (string) ($application['contact_email'] ?: $application['email']);
            $verificationChecks = [
                'Identity proof uploaded' => trim((string) $application['verification_file']) !== '',
                'Resume uploaded' => trim((string) ($application['resume_file'] ?? '')) !== '',
                'Age 20+' => $applicationBirthDate && $applicationBirthDate <= new DateTimeImmutable('today') && $applicationAge >= 20,
                'Credentials issued after approval' => true,
                'Login email available' => !row('SELECT id FROM users WHERE email = ?', [$applicationEmail]),
                'Reference provided' => trim((string) ($application['reference_contact'] ?? '')) !== '',
                'Applicant declaration accepted' => (int) ($application['applicant_declaration'] ?? 0) === 1,
            ];
            $applicationReady = !in_array(false, $verificationChecks, true);
          ?>
          <article class="card card-pad staff-application">
            <div class="flex-between">
              <div><h2 style="font-size:1.1rem;margin:0"><?= e($application['full_name']) ?></h2><p class="muted" style="margin:0">Login/contact: <?= e((string) ($application['contact_email'] ?: $application['email'])) ?> · <?= e(admin_role_label((string) $application['requested_role'])) ?></p></div>
              <?= sb((string) $application['status']) ?>
            </div>
            <div class="admin-application-meta"><div><b>Worker track:</b> <?= e(admin_role_label((string) $application['requested_role'])) ?></div><div><b>Submitted:</b> <?= time_ago((string) $application['created_at']) ?></div></div>
            <div class="admin-application-meta">
              <?php foreach ($verificationChecks as $checkLabel => $checkPassed): ?><div><?= $checkPassed ? '✓' : '✕' ?> <?= e($checkLabel) ?></div><?php endforeach; ?>
            </div>
            <p><b>Experience</b><br><?= nl2br(e($application['experience'])) ?></p>
            <div class="admin-application-meta">
              <div><b>Phone:</b> <?= e((string) $application['phone']) ?></div>
              <div><b>Location:</b> <?= e((string) $application['location']) ?></div>
              <div><b>Availability:</b> <?= e(ucwords(str_replace('_', ' ', (string) $application['availability']))) ?></div>
              <?php if (!empty($application['interview_at'])): ?><div><b>Interview:</b> <?= e((string) $application['interview_at']) ?><?= !empty($application['interview_location']) ? ' · ' . e((string) $application['interview_location']) : '' ?></div><?php endif; ?>
            </div>
            <p><b>Education</b><br><?= nl2br(e((string) $application['education'])) ?></p>
            <p><b>Work history</b><br><?= nl2br(e((string) $application['work_history'])) ?></p>
            <p><b>Skills</b><br><?= nl2br(e((string) $application['skills'])) ?></p>
            <p><b>Motivation</b><br><?= nl2br(e($application['motivation'])) ?></p>
            <p><b>Cover letter</b><br><?= nl2br(e((string) $application['cover_letter'])) ?></p>
            <p><b>Reference:</b> <?= e((string) $application['reference_name']) ?> · <?= e((string) $application['reference_contact']) ?></p>
            <div class="flex" style="flex-wrap:wrap">
              <?php if (trim((string) $application['resume_file']) !== ''): ?><a class="btn btn-outline btn-sm" href="<?= e(admin_document_url((string) $application['resume_file'])) ?>" target="_blank">Open resume / CV ↗</a><?php endif; ?>
              <a class="btn btn-outline btn-sm" href="<?= e(admin_document_url((string) $application['verification_file'])) ?>" target="_blank">Open identity proof ↗</a>
            </div>
            <?php if (!in_array($application['status'], ['approved', 'rejected', 'withdrawn'], true)): ?>
              <form method="post" class="stack" style="margin-top:1rem">
                <?= csrf_field() ?>
                <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
                <label for="reviewer_notes_<?= (int) $application['id'] ?>"><b>Internal reviewer notes</b></label>
                <textarea class="input" id="reviewer_notes_<?= (int) $application['id'] ?>" name="reviewer_notes" rows="2" placeholder="Record interview notes, verification findings, or follow-up items."><?= e((string) ($application['reviewer_notes'] ?? '')) ?></textarea>
                <div class="form-grid-2">
                  <div class="field"><label for="interview_at_<?= (int) $application['id'] ?>">Interview date and time</label><input class="input" type="datetime-local" id="interview_at_<?= (int) $application['id'] ?>" name="interview_at" value="<?= !empty($application['interview_at']) ? e(date('Y-m-d\TH:i', strtotime((string) $application['interview_at']))) : '' ?>"></div>
                  <div class="field"><label for="interview_location_<?= (int) $application['id'] ?>">Interview location / link</label><input class="input" id="interview_location_<?= (int) $application['id'] ?>" name="interview_location" value="<?= e((string) ($application['interview_location'] ?? '')) ?>"></div>
                </div>
                <div class="admin-verdict">
                  <button class="btn btn-outline" name="action" value="save_notes" type="submit">Save notes</button>
                  <button class="btn btn-outline" name="action" value="submitted" type="submit">Mark submitted</button>
                  <button class="btn btn-outline" name="action" value="reviewing" type="submit">Mark reviewing</button>
                  <button class="btn btn-outline" name="action" value="interview" type="submit">Schedule interview</button>
                  <?php if ($applicationReady): ?><button class="btn btn-primary" name="action" value="approve" type="submit" data-confirm="Approve this worker and create limited admin access?">Approve &amp; issue access</button><?php endif; ?>
                  <button class="btn btn-danger" name="action" value="reject" type="submit">Reject</button>
                </div>
              </form>
            <?php elseif (trim((string) ($application['reviewer_notes'] ?? '')) !== ''): ?>
              <p class="muted"><b>Reviewer notes:</b><br><?= nl2br(e((string) $application['reviewer_notes'])) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
