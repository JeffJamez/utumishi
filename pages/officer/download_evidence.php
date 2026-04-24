<?php

define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/utils/file_upload.php';

if (!in_array($_SESSION['role'] ?? '', [ROLE_OFFICER, ROLE_OCS])) {
    http_response_code(403);
    exit('Access denied');
}

$evidenceId = (int)($_GET['id'] ?? 0);

if (!$evidenceId) {
    http_response_code(400);
    exit('Invalid evidence ID');
}

$currentUser = getCurrentUser();
$result = serveEvidenceFile($evidenceId, $currentUser['id']);

if (!$result['success']) {
    http_response_code(404);
    exit($result['message'] ?? 'File not found');
}

$ext = strtolower($result['file_type']);
$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
];

$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $result['original_name'] . '"');
header('Content-Length: ' . filesize($result['file_path']));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Expires: 0');

readfile($result['file_path']);
exit;