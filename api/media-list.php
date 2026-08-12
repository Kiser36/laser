<?php
/**
 * Owere & Associates — Media Library: JSON list of uploaded images.
 * Used by the CMS "Choose from library" picker and the Media Library page.
 * Authenticated, returns web-relative paths.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not authorised.']);
    exit;
}

$dir = BASE_PATH . '/assets/images/content';
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
$images  = [];

if (is_dir($dir)) {
    $files = glob($dir . '/*.*') ?: [];
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $ext = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            continue;
        }
        $images[] = [
            'name'  => basename($file),
            'path'  => 'assets/images/content/' . basename($file),
            'size'  => (int)filesize($file),
            'mtime' => (int)filemtime($file),
        ];
    }
    usort($images, static function (array $a, array $b): int {
        return $b['mtime'] <=> $a['mtime'];
    });
}

echo json_encode(['ok' => true, 'images' => $images]);
