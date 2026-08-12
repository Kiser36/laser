<?php
/**
 * Owere & Associates — WhatsApp link router.
 * Builds a pre-filled wa.me deep link from the configured business number and
 * an optional message, then redirects. Also available as a JSON endpoint
 * (?format=json) for the front end.
 *
 * Usage:
 *   api/whatsapp-router.php
 *   api/whatsapp-router.php?text=Hello
 *   api/whatsapp-router.php?number=256700000000&text=Hello
 *   api/whatsapp-router.php?format=json
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

// Normalise: strip everything except leading + and digits.
$number = preg_replace('/[^\d]/', '', (string)($_GET['number'] ?? ''));
if ($number === '') {
    $number = preg_replace('/[^\d]/', '', get_setting('whatsapp_number', DEFAULT_SETTINGS['whatsapp_number']));
}


$text  = mb_substr((string)($_GET['text'] ?? ''), 0, 1000);
if ($text === '') {
    $text = get_setting('whatsapp_welcome_msg', DEFAULT_SETTINGS['whatsapp_welcome_msg']);
}

$link = 'https://wa.me/' . $number;
if ($text !== '') {
    $link .= '?text=' . rawurlencode($text);
}

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['number' => $number, 'url' => $link]);
    exit;
}

header('Location: ' . $link);
exit;
