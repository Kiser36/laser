<?php
/**
 * Owere & Associates — dynamic XML sitemap.
 * Served as /sitemap.xml via .htaccess. Uses the request host so it works on
 * any domain without editing. Add new public pages to the $paths array.
 */

declare(strict_types=1);

$isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host    = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$base    = ($isHttps ? 'https' : 'http') . '://' . $host;

/* Public pages to include (anchors are handled by the pages themselves). */
$paths = [
    '/',
    '/services.php',
];

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($paths as $path): ?>
  <url>
    <loc><?= htmlspecialchars($base . $path, ENT_XML1, 'UTF-8') ?></loc>
    <changefreq>weekly</changefreq>
    <priority><?= $path === '/' ? '1.0' : '0.8' ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
