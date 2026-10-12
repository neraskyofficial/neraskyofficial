<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$user = require_user();
$relative = ltrim(rawurldecode((string) ($_GET['path'] ?? '')), '/');
$privateFolders = ['uploads/proofs/', 'uploads/cash-in-proofs/', 'uploads/works/'];
$allowedFolder = false;
foreach ($privateFolders as $folder) {
    if (str_starts_with($relative, $folder)) {
        $allowedFolder = true;
        break;
    }
}

if (!$allowedFolder || str_contains($relative, '..') || str_contains($relative, '\\')) {
    http_response_code(404);
    exit('Document not found.');
}

$permitted = false;
if (str_starts_with($relative, 'uploads/proofs/')) {
    $permitted = (bool) row(
        'SELECT p.id FROM payments p WHERE p.screenshot_file = ? AND p.payer_id = ?',
        [$relative, (int) $user['id']]
    );
} elseif (str_starts_with($relative, 'uploads/cash-in-proofs/')) {
    $permitted = (bool) row(
        'SELECT id FROM cash_in_requests WHERE screenshot_file = ? AND user_id = ?',
        [$relative, (int) $user['id']]
    );
} else {
    $permitted = (bool) row(
        'SELECT o.id FROM orders o WHERE o.deliverable_file = ? AND (o.buyer_id = ? OR o.seller_id = ?)',
        [$relative, (int) $user['id'], (int) $user['id']]
    );
}

if (!$permitted) {
    http_response_code(403);
    exit('You are not allowed to access this document.');
}

$root = realpath(APP_ROOT . '/uploads');
$file = realpath(APP_ROOT . '/' . $relative);
if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit('Document not found.');
}

$mime = mime_content_type($file) ?: 'application/octet-stream';
$download = !in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . basename($file) . '"');
readfile($file);
