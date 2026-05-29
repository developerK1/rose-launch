<?php
$relative = trim((string)($_GET['path'] ?? ''));
if ($relative === '') {
    http_response_code(404);
    exit('Not found');
}

$base = realpath(__DIR__ . '/storage');
if ($base === false) {
    http_response_code(500);
    exit('Storage not available');
}

$target = realpath(__DIR__ . '/' . ltrim($relative, '/'));
if ($target === false || strpos($target, $base) !== 0 || !is_file($target)) {
    http_response_code(404);
    exit('Not found');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($target) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($target));
readfile($target);
exit;
