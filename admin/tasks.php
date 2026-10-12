<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_helpers.php';
$me = require_admin();
require_admin_area($me, 'tasks');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create' && trim((string) ($_POST['title'] ?? '')) !== '') {
        insert('INSERT INTO admin_tasks (title,details,priority,due_at,created_by) VALUES (?,?,?,?,?)', [trim((string) $_POST['title']), trim((string) ($_POST['details'] ?? '')), (string) ($_POST['priority'] ?? 'normal'), ($_POST['due_at'] ?? '') ?: null, (int) $me['id']]);
    } elseif ($action === 'status') {
        $status = in_array((string) ($_POST['status'] ?? ''), ['open','in_progress','done'], true) ? (string) $_POST['status'] : 'open';
        q('UPDATE admin_tasks SET status = ?, completed_at = IF(? = "done", NOW(), NULL) WHERE id = ?', [$status, $status, (int) $_POST['id']]);
    }
    redirect('tasks.php');
}
$tasks = rows('SELECT t.*, u.full_name AS assignee FROM admin_tasks t LEFT JOIN users u ON u.id=t.assigned_to ORDER BY FIELD(t.priority,"urgent","high","normal","low"), t.id DESC');
$pageTitle = 'Admin tasks';
require __DIR__ . '/header.php';
?>
<section class="page-head">
  <div class="container">
    <span class="eyebrow">Operations</span>
    <h1>Admin task queue</h1>
    <p class="muted">Assign priorities and track operational work.</p>
  </div>
</section>
<section class="section-tight" style="padding-top:1rem">
  <div class="container">
    <?= admin_subnav('tasks') ?>
    <div class="panel card-pad">
      <div class="panel-head"><div><h2 style="font-size:1.2rem">Create a task</h2><span class="ph-sub">Add an item for the team to track.</span></div></div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="grid-2">
          <div class="field"><label for="task-title">Title</label><input class="input" id="task-title" name="title" required></div>
          <div class="field"><label for="task-due">Due</label><input class="input" id="task-due" type="datetime-local" name="due_at"></div>
        </div>
        <div class="field"><label for="task-details">Details</label><textarea class="input" id="task-details" name="details" rows="3"></textarea></div>
        <div class="field"><label for="task-priority">Priority</label><select class="input" id="task-priority" name="priority"><option>normal</option><option>high</option><option>urgent</option><option>low</option></select></div>
        <button class="btn btn-primary" type="submit">Create task</button>
      </form>
    </div>

    <div class="panel card-pad mt-3">
      <div class="panel-head"><div><h2 style="font-size:1.2rem">Task queue</h2><span class="ph-sub"><?= count($tasks) ?> <?= count($tasks) === 1 ? 'task' : 'tasks' ?></span></div></div>
      <?php if ($tasks === []): ?>
        <p class="muted" style="margin:0">No tasks yet.</p>
      <?php else: ?>
        <div class="stack">
          <?php foreach ($tasks as $task): ?>
            <div class="flex-between" style="gap:1rem;border-bottom:1px solid var(--line);padding:.8rem 0">
              <div>
                <b><?= e($task['title']) ?></b>
                <small class="muted"><?= e((string) $task['priority']) ?><?= $task['due_at'] ? ' · due '.e($task['due_at']) : '' ?></small>
              </div>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                <select class="input" name="status" aria-label="Status for <?= e($task['title']) ?>" onchange="this.form.submit()">
                  <?php foreach (['open','in_progress','done'] as $status): ?><option <?= $task['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
                </select>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
