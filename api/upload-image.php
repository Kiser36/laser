<?php
/**
 * Owere & Associates — AJAX image upload for the CMS content editor.
 * Authenticated, CSRF-protected, validated. Returns JSON.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Your session has expired. Please log in again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid security token. Refresh the page and try again.']);
    exit;
}

$upload = upload_image(
    $_FILES['file'] ?? [],
    'assets/images/content',
    ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']
);

if ($upload['ok']) {
    log_admin_activity('media_upload', 'Uploaded ' . basename((string)($_FILES['file']['name'] ?? '')) . ' (content editor)');
}

echo json_encode([
    'ok'      => $upload['ok'],
    'path'    => $upload['path'] ?? '',
    'message' => $upload['error'] ?? '',
]);
