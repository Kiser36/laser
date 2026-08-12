<?php
/**
 * Global site header & navigation.
 * Expects $pageTitle (string), $activeNav (string) and optionally
 * $seoKey ('home' | 'services' | …) to be set before include.
 */
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Home';
$activeNav = $activeNav ?? '';
$seoKey    = $seoKey ?? '';

$brandName = content_text('brand.name', 'Owere & Associates');
$brandMark = content_text('brand.mark', 'OA');
$brandTag  = content_text('brand.tagline', 'Tax · NGO · Corporate');
$brandLogo = content_text('brand.logo');

$navLinks  = content_arr('nav.links');
$navCta    = content_text('nav.cta', 'Book a Consultation');

$seoTitle  = $seoKey !== '' ? content_text('seo.' . $seoKey . '_title', $pageTitle) : $pageTitle;
$seoDesc   = $seoKey !== '' ? content_text('seo.' . $seoKey . '_description') : '';
if ($seoDesc === '') {
    $seoDesc = $brandName . ' — tax advisory, NGO financial management and corporate compliance in Uganda.';
}

$sitePhone = get_setting('phone_display');
$siteEmail = get_setting('notification_email');
$siteHours = get_setting('hours');
$waNumber  = get_setting('whatsapp_number');

/* ---------- SEO: canonical URL + base URL (detected from the request) ---------- */
$isHttps   = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host      = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$scheme    = $isHttps ? 'https' : 'http';
$baseUrl   = $scheme . '://' . $host;
$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$requestUri = explode('?', $requestUri)[0]; // drop query string
if ($requestUri === '' || $requestUri === '/index.php') {
    $requestUri = '/'; // one canonical homepage: / (matches the sitemap)
}
$canonical = $baseUrl . $requestUri;
$ogImage   = $brandLogo !== ''
    ? $baseUrl . '/' . ltrim($brandLogo, '/')
    : $baseUrl . '/assets/images/about-visual.svg';

/* ---------- Fonts: Google Fonts URL for the chosen pairing (Design & Theme) ---------- */
$fontPair = FONT_PAIRS[content_text('theme.fonts')] ?? FONT_PAIRS['classic'];
// Weights are restricted to 400/700 (plus italic 400) — the widest common
// subset across every pairing, so fonts like Merriweather (no 500/600) never
// make the Google Fonts request fail for the whole stylesheet.
$fontHref = 'https://fonts.googleapis.com/css2?family=' . urlencode($fontPair['display'])
    . ':ital,wght@0,400;0,700;1,400'
    . '&family=' . urlencode($fontPair['body'])
    . ':wght@400;600;700&display=swap';

$currentFile = basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'));

/** Small helper: is this nav link the current page? */
$isNavActive = static function (array $link) use ($currentFile, $activeNav): bool {
    $href = (string)($link['href'] ?? '');
    if ($href === '' || $href === '#') {
        return false;
    }
    if (str_contains($href, '#')) {
        // Anchor link — never the active page itself.
        return false;
    }
    return basename($href) === $currentFile || (($link['active'] ?? '') === $activeNav);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($seoTitle) ?> | <?= esc($brandName) ?></title>
    <meta name="description" content="<?= esc($seoDesc) ?>">
    <link rel="canonical" href="<?= esc($canonical) ?>">
    <meta name="theme-color" content="#0B192C">

    <!-- Open Graph (how the link previews on WhatsApp, Facebook, LinkedIn) -->
    <meta property="og:site_name" content="<?= esc($brandName) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= esc($seoTitle) ?> | <?= esc($brandName) ?>">
    <meta property="og:description" content="<?= esc($seoDesc) ?>">
    <meta property="og:url" content="<?= esc($canonical) ?>">
    <meta property="og:image" content="<?= esc($ogImage) ?>">
    <meta property="og:locale" content="en_UG">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= esc($seoTitle) ?> | <?= esc($brandName) ?>">
    <meta name="twitter:description" content="<?= esc($seoDesc) ?>">
    <meta name="twitter:image" content="<?= esc($ogImage) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?= esc($fontHref) ?>" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <style><?= theme_css_vars() ?></style>
    <link rel="icon" type="image/svg+xml" href="assets/images/icons/favicon.svg">
    <noscript><style>.reveal{opacity:1;transform:none;}</style></noscript>

    <!-- Structured data: helps Google understand the firm and show it in
         local results for searches like "tax consultants Uganda". -->
    <script type="application/ld+json"><?=
        json_encode([
            '@context' => 'https://schema.org',
            '@type'    => 'ProfessionalService',
            'name'     => $brandName,
            'description' => $seoDesc,
            'url'      => $canonical,
            'image'    => $ogImage,
            'telephone'=> $sitePhone,
            'email'    => $siteEmail,
            'priceRange' => '$$',
            'address'  => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => get_setting('address', 'Kampala, Uganda'),
                'addressLocality' => 'Kampala',
                'addressCountry'  => 'UG',
            ],
            'areaServed' => ['@type' => 'Country', 'name' => 'Uganda'],
            'openingHoursSpecification' => [
                '@type'       => 'OpeningHoursSpecification',
                'dayOfWeek'   => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'opens'       => '08:00',
                'closes'      => '18:00',
            ],
            'sameAs' => [],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    )?></script>

    <?php
    // FAQPage rich results: mirrors the accordion on the homepage so Google
    // can surface the Q&As directly in search.
    $faqItems = array_values(array_filter(
        content_arr('faq.items'),
        static fn(array $fq): bool => trim((string)($fq['question'] ?? '')) !== ''
    ));
    if (count($faqItems) > 0): ?>
    <script type="application/ld+json"><?=
        json_encode([
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(static function (array $fq): array {
                return [
                    '@type'          => 'Question',
                    'name'           => (string)($fq['question'] ?? ''),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => (string)($fq['answer'] ?? ''),
                    ],
                ];
            }, $faqItems),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    )?></script>
    <?php endif; ?>
</head>
<body>

<!-- ============ TOP BAR ============ -->
<div class="topbar">
    <div class="container topbar__inner">
        <div class="topbar__contact">
            <a href="tel:<?= esc(preg_replace('/\s+/', '', $sitePhone)) ?>">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <?= esc($sitePhone) ?>
            </a>
            <a href="mailto:<?= esc($siteEmail) ?>">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                <?= esc($siteEmail) ?>
            </a>
        </div>
        <div class="topbar__hours">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?= esc($siteHours) ?>
        </div>
    </div>
</div>

<!-- ============ HEADER / NAV ============ -->
<header class="site-header" id="siteHeader">
    <div class="container site-header__inner">
        <a href="index.php" class="brand" aria-label="<?= esc($brandName) ?> — home">
            <?php if ($brandLogo !== ''): ?>
                <img src="<?= esc($brandLogo) ?>" alt="<?= esc($brandName) ?>" class="brand__logo">
            <?php else: ?>
                <span class="brand__mark"><?= esc($brandMark) ?></span>
            <?php endif; ?>
            <span class="brand__text">
                <span class="brand__name"><?= esc($brandName) ?></span>
                <span class="brand__tag"><?= esc($brandTag) ?></span>
            </span>
        </a>

        <nav class="site-nav" id="siteNav" aria-label="Primary">
            <?php foreach ($navLinks as $link): ?>
                <?php $label = (string)($link['label'] ?? ''); ?>
                <?php if ($label === ''): continue; endif; ?>
                <a href="<?= esc((string)($link['href'] ?? '#')) ?>"
                   class="site-nav__link <?= $isNavActive($link) ? 'is-active' : '' ?>"><?= esc($label) ?></a>
            <?php endforeach; ?>
            <a href="#" class="btn btn--gold site-nav__cta" data-modal-open="booking"><?= esc($navCta) ?></a>
        </nav>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<main>
