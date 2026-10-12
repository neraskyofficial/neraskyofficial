<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_admin();

$relative = ltrim(rawurldecode((string) ($_GET['path'] ?? '')), '/');
$allowedFolders = ['uploads/proofs/', 'uploads/cash-in-proofs/', 'uploads/consents/', 'uploads/admin-resumes/', 'uploads/admin-verification/'];
$allowed = false;
foreach ($allowedFolders as $folder) {
    if (str_starts_with($relative, $folder)) {
        $allowed = true;
        break;
    }
}

if (!$allowed || str_contains($relative, '..') || str_contains($relative, '\\')) {
    http_response_code(404);
    exit('Document not found.');
}

$root = realpath(MARKET_APP_ROOT . '/uploads');
$file = realpath(MARKET_APP_ROOT . '/' . $relative);
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
