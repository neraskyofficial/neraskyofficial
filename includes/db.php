<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Returns the shared PDO connection (singleton).
 */
function db(): PDO
{
    static $pdo = null;
    static $schemaUpgraded = false;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('Nerasky database connection failed: ' . $e->getMessage());
        http_response_code(500);
        $error = e('Could not connect to the database. Check the hosting database values in includes/config.php and confirm the database has been created.');
        exit('<!doctype html><meta charset="utf-8"><title>Database unavailable</title>'
            .'<body style="font-family:Segoe UI,system-ui,sans-serif;background:#0B1020;color:#E7EAF3;display:grid;place-items:center;min-height:90vh;margin:0">'
            .'<div style="max-width:520px;text-align:center"><h1 style="font-size:22px">Nerasky needs MySQL</h1>'
            .'<p style="color:#9AA3B5">'.$error.'</p>'
            .'<p style="color:#9AA3B5">After saving the correct settings, refresh this page.</p></div></body>');
    }

    if (!$schemaUpgraded) {
        $usersTable = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdo->quote(DB_NAME) . " AND table_name = 'users'"
        )->fetchColumn();
        if ($usersTable > 0) {
            require_once __DIR__ . '/upgrade.php';
            upgrade_db($pdo);
        }
        $schemaUpgraded = true;
    }

    return $pdo;
}

/**
 * Run a prepared query and return the statement.
 */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Fetch a single row or null. */
function row(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Fetch all rows. */
function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** Insert and return the new id. */
function insert(string $sql, array $params = []): int
{
    q($sql, $params);
    return (int) db()->lastInsertId();
}

/** Simple scalar helper, e.g. SELECT COUNT(*). */
function scalar(string $sql, array $params = []): mixed
{
    return q($sql, $params)->fetchColumn();
}