<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$me = require_admin();
if (admin_role($me) !== 'super_admin') {
    http_response_code(403);
    exit('Access denied');
}
if (isset($_GET['download'])) {
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="nerasky-backup-' . date('Y-m-d-His') . '.sql"');
    $db = db();
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $create = $db->query('SHOW CREATE TABLE `' . str_replace('`', '``', (string) $table . '`'))->fetch(PDO::FETCH_ASSOC);
        echo "DROP TABLE IF EXISTS `" . $table . "`;\n" . $create['Create Table'] . ";\n";
        foreach ($db->query('SELECT * FROM `' . str_replace('`', '``', (string) $table) . '`')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $values = array_map(static fn($value): string => $value === null ? 'NULL' : $db->quote((string) $value), array_values($row));
            echo 'INSERT INTO `' . $table . '` VALUES (' . implode(',', $values) . ");\n";
        }
    }
    exit;
}
$pageTitle = 'Backup';
require __DIR__ . '/header.php';
?>
<section class="section"><div class="container" style="max-width:760px"><?php admin_subnav('index'); ?><span class="eyebrow">Recovery</span><h1>Database backup</h1><p class="muted">Download a complete SQL export. Store it securely because it contains private platform data.</p><a class="btn btn-primary" href="<?= e(base_url('backup.php?download=1')) ?>">Download SQL backup</a></div></section>
<?php require __DIR__ . '/footer.php'; ?>
