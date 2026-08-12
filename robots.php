<?php
/**
 * Owere & Associates — dynamic robots.txt.
 * Served as /robots.txt via .htaccess so the Sitemap line always matches the
 * domain the site is currently served from.
 */

declare(strict_types=1);

$isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$base = ($isHttps ? 'https' : 'http') . '://' . $host;

header('Content-Type: text/plain; charset=UTF-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "\n";
echo "# Admin area and internal files are already blocked by .htaccess\n";
echo "Disallow: /admin/\n";
echo "\n";
echo 'Sitemap: ' . $base . "/sitemap.xml\n";
