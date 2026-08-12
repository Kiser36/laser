<?php
/**
 * Owere & Associates — shared helpers.
 * Security sanitisation, sessions, DB access, content & settings,
 * file uploaders, native SMTP mail and template helpers.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

/** Absolute filesystem path to the project root (Windows-safe). */
define('BASE_PATH', dirname(__DIR__));

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ===========================================================================
 * OUTPUT & INPUT SECURITY
 * ========================================================================= */

/**
 * Polyfills for PHP 8 string helpers, so the app also runs on PHP 7.4.
 * On PHP 8+ these are no-ops (the native functions are used instead).
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

function esc($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape for output, then re-enable a tiny whitelist of safe rich-text tags.
 * Used for fields such as the hero title that may contain <em>/<strong>.
 */
function rich_text($value): string
{
    $value = esc($value);
    $value = preg_replace(
        '/&lt;(\/?(?:em|strong|br(?:\s*\/)?))&gt;/i',
        '<$1>',
        (string)$value
    ) ?? $value;
    return $value;
}

/**
 * Render a single-line text field, converting *asterisk* phrases into gold
 * italic <em> accents (the admin never needs to type HTML). Any legacy <em>
 * tags are stripped first so old content keeps working.
 */
function render_emphasis(?string $value): string
{
    $value = esc($value);
    $value = preg_replace('/&lt;\/?em&gt;/i', '', (string)$value) ?? (string)$value;
    return preg_replace('/\*([^*\n]+)\*/', '<em>$1</em>', $value) ?? $value;
}

/**
 * Render a long-form field. HTML input is sanitised with a strict tag
 * whitelist; plain text is escaped and split into paragraphs on blank lines.
 */
function render_rich(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (strpos($value, '<') !== false) {
        return sanitize_rich_text($value);
    }
    $paragraphs = preg_split('/\n\s*\n/', $value) ?: [];
    $out = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }
        $out .= '<p>' . nl2br(esc($paragraph)) . '</p>';
    }
    return $out;
}

/**
 * Strict sanitisation of rich-text HTML. Unwraps disallowed tags, strips
 * event handlers and non-whitelisted attributes, and forces links to open
 * safely in a new tab. Falls back to a regex pass if ext-dom is unavailable.
 */
function sanitize_rich_text(?string $html): string
{
    $html = trim((string)$html);
    if ($html === '') {
        return '';
    }

    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'a', 'span', 'img'];

    if (!class_exists('DOMDocument')) {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><h4><a><span><img>');
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('#\s(?:href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\1#i', '', $html) ?? $html;
        return trim($html);
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="utf-8" ?><div id="cms-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    // Remove HTML comments entirely.
    $xpath    = new DOMXPath($doc);
    $comments = [];
    foreach ($xpath->query('//comment()') as $comment) {
        $comments[] = $comment;
    }
    foreach ($comments as $comment) {
        if ($comment->parentNode !== null) {
            $comment->parentNode->removeChild($comment);
        }
    }

    $elements = [];
    foreach ($doc->getElementsByTagName('*') as $el) {
        $elements[] = $el;
    }

    foreach ($elements as $el) {
        $tag = strtolower($el->nodeName);

        // Never unwrap the wrapper element itself.
        if (($el->getAttribute('id') ?? '') === 'cms-root') {
            continue;
        }

        // contenteditable produces <div> blocks on Enter in Chrome/Edge.
        // Normalise them to paragraphs so line breaks survive on the site.
        if ($tag === 'div') {
            $p = $doc->createElement('p');
            while ($el->firstChild) {
                $p->appendChild($el->firstChild);
            }
            if ($el->parentNode !== null) {
                $el->parentNode->replaceChild($p, $el);
            }
            $el = $p;
            $tag = 'p';
        }

        if (!in_array($tag, $allowed, true)) {
            // Unwrap: move children up, drop the element itself.
            while ($el->firstChild && $el->parentNode !== null) {
                $el->parentNode->insertBefore($el->firstChild, $el);
            }
            if ($el->parentNode !== null) {
                $el->parentNode->removeChild($el);
            }
            continue;
        }

        $attrNames = [];
        foreach ($el->attributes as $attr) {
            $attrNames[] = $attr->nodeName;
        }
        foreach ($attrNames as $name) {
            $nameLower = strtolower($name);
            if (str_starts_with($nameLower, 'on')) {
                $el->removeAttribute($name);
                continue;
            }
            if ($tag === 'img') {
                // Keep only src (a safe relative asset path or http/https)
                // and alt text on images.
                if ($nameLower === 'src') {
                    $src = trim((string)$el->getAttribute('src'));
                    if (sanitize_asset_path($src) === '' && preg_match('#^https?://#i', $src) !== 1) {
                        $el->removeAttribute('src');
                    }
                } elseif ($nameLower !== 'alt') {
                    $el->removeAttribute($name);
                }
                continue;
            }
            if ($tag !== 'a' || !in_array($nameLower, ['href', 'target', 'rel'], true)) {
                $el->removeAttribute($name);
            }
        }

        // An image without a usable source is useless — drop it.
        if ($tag === 'img' && !$el->hasAttribute('src')) {
            if ($el->parentNode !== null) {
                $el->parentNode->removeChild($el);
            }
            continue;
        }

        if ($tag === 'a') {
            $href = trim((string)$el->getAttribute('href'));
            if ($href === '' || preg_match('#^\s*(javascript|data|vbscript):#i', $href)) {
                $el->removeAttribute('href');
            } else {
                $el->setAttribute('rel', 'noopener');
                $el->setAttribute('target', '_blank');
            }
        }
    }

    $root = $doc->getElementById('cms-root');
    $out = '';
    if ($root) {
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
    }
    return trim($out);
}

/** Validate a link destination (relative paths, anchors, http/https/mailto/tel). */
function sanitize_href(string $href): string
{
    $href = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $href) ?? $href);
    if ($href === '' || $href === '#') {
        return $href;
    }
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $href, $m)) {
        if (!in_array(strtolower($m[0]), ['http:', 'https:', 'mailto:', 'tel:'], true)) {
            return '#';
        }
    }
    if (str_contains($href, '..')) {
        return '#';
    }
    return $href;
}

/** Validate a relative asset path (e.g. assets/images/content/abc.png). */
function sanitize_asset_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, '\\')) {
        return '';
    }
    return preg_match('#^[a-z0-9_\-]+(?:/[a-z0-9_\-\.]+)*$#i', $path) === 1 ? $path : '';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token. Please go back and try again.');
    }
}

/* ===========================================================================
 * REDIRECTS & FLASH MESSAGES
 * ========================================================================= */

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function consume_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/* ===========================================================================
 * SETTINGS (system_settings table)
 * ========================================================================= */

const DEFAULT_SETTINGS = [
    'whatsapp_number'     => '+256 701 700 461',
    'whatsapp_welcome_msg'=> 'Hello Owere & Associates, I would like to inquire about your advisory services.',
    'notification_email'  => 'info@owereassociates.com',
    'phone_display'       => '+256 701 700 461',
    'address'             => 'Kampala, Uganda',
    'hours'               => 'Mon – Fri, 8:00 AM – 6:00 PM (EAT)',
    'smtp_host'           => 'smtp.zoho.com',
    'smtp_port'           => '465',
    'smtp_user'           => '',
    'smtp_pass'           => '',
    'mail_from'           => 'info@owereassociates.com',
    'mail_from_name'      => 'Owere & Associates',
];

/**
 * Build a pre-filled WhatsApp deep link via the router.
 * Used for the floating widget and per-service contextual messages.
 */
function whatsapp_link(string $message, string $number = ''): string
{
    // A per-service number (set in the CMS) overrides the main business number.
    if ($number !== '') {
        $number = preg_replace('/\D+/', '', $number);
    } else {
        $number = preg_replace('/\D+/', '', get_setting('whatsapp_number'));
    }
    $link = 'api/whatsapp-router.php?text=' . rawurlencode($message);
    if ($number !== '') {
        $link .= '&number=' . $number;
    }
    return $link;
}

/** Contextual WhatsApp message for a given service pillar. */
function whatsapp_service_message(string $serviceTitle): string
{
    return 'Hello Owere & Associates, I am interested in your '
        . $serviceTitle
        . ' offering and would like to inquire about a consultation.';
}

function get_setting(string $key, ?string $default = null): string
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM system_settings') as $row) {
                $cache[$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (PDOException) {
            // Table may not exist yet — fall back to defaults.
        }
    }

    return $cache[$key] ?? $default ?? (DEFAULT_SETTINGS[$key] ?? '');
}

/** Upsert a single system setting. */
function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

/* ===========================================================================
 * SITE CONTENT (content/site-content.json — edited via admin/content.php)
 *
 * Every editable string on the public site lives here. Values not present
 * in the JSON file fall back to the built-in defaults below, so a partial
 * or hand-edited file can never break the site.
 * ========================================================================= */

const CONTENT_SCHEMA_VERSION = 2;

const DEFAULT_CONTENT = [
    'brand' => [
        'name'    => 'Owere & Associates',
        'mark'    => 'OA',
        'tagline' => 'Tax · NGO · Corporate',
        'logo'    => '', // Optional company logo image — replaces the initials mark in the header/footer
    ],
    'seo' => [
        'home_title'         => 'Tax, NGO & Corporate Advisory',
        'home_description'   => 'Owere & Associates — tax advisory & URA compliance, NGO financial management & grant audits, and corporate advisory & URSB registration in Uganda. Book a confidential consultation today.',
        'services_title'     => 'Our Services',
        'services_description' => 'Explore our three practice pillars: Tax Advisory & Compliance (URA), NGO Financial Management & Grant Audits, and Corporate Advisory & Business Registration (URSB).',
    ],
    'theme' => [
        'preset'     => 'navy-gold',
        'primary'    => '#0B192C',
        'accent'     => '#D4AF37',
        'background' => '#F8FAFC',
        'fonts'      => 'classic',
        'sections'   => [
            // Per-band backgrounds — '' means “use the theme's main colour”.
            'hero'   => '',
            'stats'  => '',
            'cta'    => '',
            'footer' => '',
        ],
    ],
    'nav' => [
        'links' => [
            ['label' => 'Home',     'href' => 'index.php'],
            ['label' => 'Services', 'href' => 'services.php'],
            ['label' => 'About',    'href' => 'index.php#about'],
            ['label' => 'Contact',  'href' => 'index.php#contact'],
        ],
        'cta' => 'Book a Consultation',
    ],
    'hero' => [
        'eyebrow'       => 'Chartered Tax & Financial Advisory',
        'title'         => 'Clarity for every *decision*. Precision in every *return*.',
        'subtitle'      => 'Owere & Associates is a premier tax advisory, auditing, corporate compliance and NGO financial management practice based in Uganda, serving ambitious organisations with rigour and discretion.',
        'primary_cta'   => 'Book a Consultation',
        'secondary_cta' => 'Explore Services',
        'card_label'    => 'Why clients choose us',
        'badge'         => "Est.\n2011",
        'image'         => '',
        'stats'         => [
            ['value' => '15',  'suffix' => '+', 'label' => 'Years of practice'],
            ['value' => '420', 'suffix' => '+', 'label' => 'Organisations served'],
            ['value' => '98',  'suffix' => '%', 'label' => 'Client retention rate'],
        ],
    ],
    'logos' => [
        'heading'    => 'Trusted by boards, funds & NGOs',
        'empty_note' => 'Client logos will appear here once added from the admin panel.',
    ],
    'services_head' => [
        'eyebrow'     => 'What We Do',
        'title'       => 'Three pillars. One standard of excellence.',
        'card_cta'    => 'Book Consultation',
        'card_wa'     => 'WhatsApp',
        'learn_more'  => 'Learn more',
        'page_lede'   => 'Three consolidated practice areas, delivered to one uncompromising standard. Choose a pillar to learn more, book a consultation, or message us directly on WhatsApp.',
    ],
    'services' => [
        'items' => [
            [
                'key'             => 'tax',
                'num'             => '01',
                'whatsapp'        => '+256 702 345 678',
                'icon'            => 'assets/images/icons/briefcase.svg',
                'title'           => 'Tax Advisory & Compliance',
                'card_body'       => 'Corporate & individual tax planning, URA tax health checks, EFRIS setup & integration, and reliable filing of monthly, quarterly and annual returns.',
                'page_lede'       => 'Complete peace of mind with the Uganda Revenue Authority — from day-to-day compliance to EFRIS integration and strategic tax planning.',
                'page_image'      => 'assets/images/about-visual.svg',
                'page_image_alt'  => 'Tax advisory and compliance',
                'items'           => [
                    ['heading' => 'Corporate & individual tax planning',          'text' => 'Structures that minimise exposure, legitimately.'],
                    ['heading' => 'URA tax health checks',                        'text' => 'Identify and resolve risk before the authority does.'],
                    ['heading' => 'EFRIS setup & integration',                    'text' => 'Streamlined electronic fiscal invoicing.'],
                    ['heading' => 'Filing of monthly, quarterly & annual returns','text' => 'Always on time, always accurate.'],
                ],
            ],
            [
                'key'             => 'ngo',
                'num'             => '02',
                'whatsapp'        => '+256 772 456 789',
                'icon'            => 'assets/images/icons/globe.svg',
                'title'           => 'NGO Financial Management & Grant Audits',
                'card_body'       => 'Donor financial reporting, grant accountability frameworks, project fund tracking and donor-compliant accounting systems (QuickBooks/Xero).',
                'page_lede'       => 'Financial management built for donor accountability — so your funders, board and beneficiaries have complete confidence in every shilling.',
                'page_image'      => 'assets/images/about-visual.svg',
                'page_image_alt'  => 'NGO financial management and grant audits',
                'items'           => [
                    ['heading' => 'Donor financial reporting',            'text' => 'Reports that satisfy every grant agreement.'],
                    ['heading' => 'Grant accountability frameworks',      'text' => 'Clear, auditable use of funds.'],
                    ['heading' => 'Project fund tracking',                'text' => 'Real-time visibility across every project.'],
                    ['heading' => 'Donor-compliant systems (QuickBooks / Xero)', 'text' => 'Set up and maintained for you.'],
                ],
            ],
            [
                'key'             => 'corporate',
                'num'             => '03',
                'whatsapp'        => '+256 783 567 890',
                'icon'            => 'assets/images/icons/building.svg',
                'title'           => 'Corporate Advisory & Business Registration',
                'card_body'       => 'Company incorporation with URSB, annual returns, corporate secretarial services, bookkeeping and financial modelling for decisive growth.',
                'page_lede'       => 'From incorporating your company with URSB to keeping your books and filings in order, we handle the corporate administration that keeps you trading compliantly.',
                'page_image'      => 'assets/images/about-visual.svg',
                'page_image_alt'  => 'Corporate advisory and business registration',
                'items'           => [
                    ['heading' => 'Company incorporation (URSB)',            'text' => 'Registered, compliant and ready to trade.'],
                    ['heading' => 'Annual returns & corporate secretarial',  'text' => 'Statutory obligations handled on time.'],
                    ['heading' => 'Bookkeeping',                             'text' => 'Clean, current and audit-ready records.'],
                    ['heading' => 'Financial modelling',                     'text' => 'Decisions grounded in defensible numbers.'],
                ],
            ],
        ],
    ],
    'about' => [
        'eyebrow'     => 'Who We Are',
        'title'       => 'A practice built on rigour, discretion and results.',
        'body'        => '<p>Owere & Associates is a premier tax advisory, auditing, corporate compliance and NGO financial management practice based in Uganda. We guide founders, boards, finance teams and non-profit leaders through the complexities of taxation, compliance and donor accountability.</p><p>Every engagement is led personally by a partner — never delegated down. We combine technical mastery with commercial instinct, so the advice we give protects you today and positions you for growth tomorrow. Discretion is absolute; deadlines are sacred.</p>',
        'image'       => 'assets/images/about-visual.svg',
        'image_alt'   => 'Owere & Associates — financial advisory practice',
        'quote'       => '“The best advice is quiet, thorough and unshakeable. That is what we deliver, every engagement.”',
        'quote_cite'  => '— The Partners, Owere & Associates',
        'points'      => [
            ['heading' => 'Partner-led service',      'text' => 'Senior expertise on every file, never delegated down.'],
            ['heading' => 'URA & URSB fluency',       'text' => 'Regulators handled with confidence and precision.'],
            ['heading' => 'Donor-compliant systems',  'text' => 'Accounting frameworks trusted by grant funders.'],
            ['heading' => 'Deadlines honoured',       'text' => 'Filing calendars managed so you never miss a date.'],
        ],
        'cta'         => 'Book a Consultation',
    ],
    'stats' => [
        'items' => [
            ['value' => '1200', 'suffix' => '+', 'label' => 'Returns filed'],
            ['value' => '45',   'suffix' => '+', 'label' => 'Grant audits'],
            ['value' => '120',  'suffix' => '+', 'label' => 'Compliance reviews'],
            ['value' => '300',  'suffix' => '+', 'label' => 'Companies registered'],
        ],
    ],
    'process' => [
        'eyebrow' => 'How we work',
        'title'   => 'A clear path from *first call* to delivered work.',
        'steps'   => [
            ['num' => '01', 'label' => 'Consultation'],
            ['num' => '02', 'label' => 'Scoping & proposal'],
            ['num' => '03', 'label' => 'Engagement'],
            ['num' => '04', 'label' => 'Delivery & support'],
        ],
    ],
    'gallery' => [
        'eyebrow' => 'Inside the firm',
        'title'   => 'A look at how we *work*.',
        'items'   => [],
    ],
    'faq' => [
        'eyebrow' => 'Common Questions',
        'title'   => 'Answers to the questions we *hear most*.',
        'lede'    => 'A few quick answers. If yours is not here, message us on WhatsApp or book a consultation.',
        'items'   => [
            ['question' => 'How much does a consultation cost?', 'answer' => 'The first consultation is free and confidential. We then scope the engagement and confirm a fixed fee before any work begins — no hidden charges, no surprises.'],
            ['question' => 'How quickly will I hear back after booking?', 'answer' => 'A partner — not a call centre — will respond within one business day of your enquiry.'],
            ['question' => 'Do you work with NGOs and funders based outside Uganda?', 'answer' => 'Yes. We build donor-compliant financial systems and audit-ready reports for NGOs, funds and boards across East Africa, following the accounting standards each grant requires.'],
            ['question' => 'Can you make my business EFRIS compliant?', 'answer' => 'Absolutely. We handle EFRIS setup, integration and ongoing filing so your invoices and returns stay compliant with URA — on time, every time.'],
            ['question' => 'Is my information kept confidential?', 'answer' => 'Discretion is absolute. Every engagement is covered by professional confidentiality, and client details are never shared.'],
        ],
    ],
    'cta_band' => [
        'eyebrow' => 'Ready when you are',
        'title'   => "Uncertainty is expensive.\nLet's *remove* it.",
        'lede'    => 'Book a confidential consultation. A partner — not a call centre — will respond within one business day.',
        'cta'     => 'Book a Consultation',
    ],
    'contact' => [
        'eyebrow'             => 'Start a Conversation',
        'title'               => "Let's discuss your *next move*.",
        'lede'                => 'Book a confidential consultation — a partner will respond within one business day.',
        'cards'               => [
            ['heading' => 'Visit us'],
            ['heading' => 'Call us'],
            ['heading' => 'Email us'],
            ['heading' => 'Office hours'],
        ],
        'form_heading'        => 'Request a consultation',
        'form_lede'           => "Complete the form and we'll be in touch within one business day.",
        'submit'              => 'Submit Enquiry',
        'service_placeholder' => 'Select a service…',
        'general_option'      => 'General Enquiry',
    ],
    'booking' => [
        'eyebrow' => 'Book a Consultation',
        'title'   => 'Tell us about your *needs*.',
        'lede'    => 'A partner will respond within one business day. All enquiries are treated in strict confidence.',
        'submit'  => 'Submit Enquiry',
        'note'    => 'Prefer to chat? {link}.',
    ],
    'footer' => [
        'blurb'           => 'A trusted financial, tax advisory and corporate compliance practice serving ambitious organisations across East Africa with rigour, discretion and results.',
        'col_services'    => 'Services',
        'col_firm'        => 'Firm',
        'col_contact'     => 'Contact',
        'services_links'  => [
            ['label' => 'Tax Advisory & Compliance',                 'href' => 'services.php#tax'],
            ['label' => 'NGO Financial Management & Grant Audits',   'href' => 'services.php#ngo'],
            ['label' => 'Corporate Advisory & Business Registration','href' => 'services.php#corporate'],
        ],
        'firm_links'      => [
            ['label' => 'About Us',           'href' => 'index.php#about',    'modal' => false],
            ['label' => 'Our Clients',        'href' => 'index.php#partners', 'modal' => false],
            ['label' => 'Contact',            'href' => 'index.php#contact',  'modal' => false],
            ['label' => 'Book a Consultation','href' => '#',                  'modal' => true],
        ],
        'socials'         => [
            ['network' => 'linkedin', 'href' => '#', 'label' => 'LinkedIn'],
            ['network' => 'x',        'href' => '#', 'label' => 'X / Twitter'],
            ['network' => 'whatsapp', 'href' => 'wa', 'label' => 'WhatsApp'],
        ],
        'bottom_left'     => '© {year} Owere & Associates. All rights reserved.',
        'bottom_links'    => [
            ['label' => 'Staff Login',   'href' => 'admin/index.php'],
            ['label' => 'Privacy Policy', 'href' => '#'],
        ],
    ],
];

function content_file(): string
{
    return __DIR__ . '/../content/site-content.json';
}

/** True when the array uses string keys (an associative map, not a list). */
function is_assoc(array $value): bool
{
    return array_keys($value) !== range(0, count($value) - 1);
}

/**
 * Merge saved content over the defaults. Associative sections merge
 * key-by-key (so new default fields appear for older files); lists
 * (services, links, stats…) are replaced wholesale.
 */
function deep_merge_content(array $defaults, array $overrides): array
{
    $out = $defaults;
    foreach ($overrides as $key => $value) {
        if (is_array($value)
            && isset($out[$key]) && is_array($out[$key])
            && is_assoc($value) && is_assoc($out[$key])) {
            $out[$key] = deep_merge_content($out[$key], $value);
        } else {
            $out[$key] = $value;
        }
    }
    return $out;
}

/** Load the content tree once per request, merging file over defaults. */
function load_content_tree(): array
{
    static $tree = null;

    if ($tree === null) {
        $file = [];
        $raw  = @file_get_contents(content_file());
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $file = $decoded;
            }
        }
        $tree = deep_merge_content(DEFAULT_CONTENT, $file);
    }

    return $tree;
}

/** Fetch a value by dot path, e.g. content_get('hero.title'). */
function content_get(string $path, $default = null)
{
    $node = load_content_tree();
    foreach (explode('.', $path) as $segment) {
        if (!is_array($node) || !array_key_exists($segment, $node)) {
            return $default;
        }
        $node = $node[$segment];
    }
    return $node;
}

/** Fetch a scalar string by dot path. */
function content_text(string $path, string $default = ''): string
{
    $value = content_get($path, $default);
    return is_scalar($value) ? (string)$value : $default;
}

/** Fetch an array by dot path (lists of links, stats, services…). */
function content_arr(string $path, array $default = []): array
{
    $value = content_get($path, $default);
    return is_array($value) ? $value : $default;
}

/** Backward-compatible wrapper: get_content('hero', 'title'). */
function get_content(string $section, string $key, ?string $default = null): string
{
    return content_text($section . '.' . $key, (string)($default ?? ''));
}

/**
 * Persist the full content tree atomically and keep timestamped backups.
 * Writes to a temp file first, then renames over the live file so a
 * crash mid-save can never corrupt the site content.
 */
function save_content(array $tree): bool
{
    $dir = dirname(content_file());
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return false;
    }

    $tree['_meta'] = [
        'schema_version' => CONTENT_SCHEMA_VERSION,
        'updated_at'     => date('c'),
    ];

    $json = json_encode(
        $tree,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if ($json === false) {
        return false;
    }

    $tmp = content_file() . '.tmp';
    if (@file_put_contents($tmp, $json) === false) {
        return false;
    }
    if (!@rename($tmp, content_file())) {
        @unlink($tmp);
        return false;
    }

    // Timestamped backup, pruned to the newest 20.
    $backupDir = $dir . '/backups';
    if (is_dir($backupDir) || @mkdir($backupDir, 0755, true)) {
        @file_put_contents($backupDir . '/site-content-' . date('Ymd-His') . '.json', $json);
        $backups = glob($backupDir . '/site-content-*.json') ?: [];
        rsort($backups, SORT_STRING);
        while (count($backups) > 20) {
            @unlink(array_pop($backups));
        }
    }

    return true;
}

/** Remove saved values for the given sections so they fall back to defaults. */
function reset_content_sections(array $sections): bool
{
    $tree = load_content_tree();
    foreach ($sections as $section) {
        unset($tree[$section]);
    }
    return save_content($tree);
}

/** Service titles shown in booking forms, built from the editable services. */
function booking_service_options(): array
{
    $options = [];
    foreach (content_arr('services.items') as $service) {
        $title = trim((string)($service['title'] ?? ''));
        if ($title !== '') {
            $options[] = $title;
        }
    }
    $options[] = content_text('contact.general_option', 'General Enquiry');
    return array_values(array_unique($options));
}

/* ===========================================================================
 * THEME — no-code colours (edited via Website Content → Design & Theme)
 * ========================================================================= */

/** Ready-made palettes shown as cards in the admin panel. */
const THEME_PRESETS = [
    'navy-gold'   => ['label' => 'Executive Navy & Gold',   'primary' => '#0B192C', 'accent' => '#D4AF37', 'background' => '#F8FAFC'],
    'royal-blue'  => ['label' => 'Royal Blue & Gold',       'primary' => '#1B3A6B', 'accent' => '#D4AF37', 'background' => '#F6F8FC'],
    'forest'      => ['label' => 'Deep Green & Sand',       'primary' => '#123B2E', 'accent' => '#C9A227', 'background' => '#F7F5F0'],
    'wine'        => ['label' => 'Wine & Champagne',        'primary' => '#5B1A2A', 'accent' => '#E3C88F', 'background' => '#FBF8F3'],
    'charcoal'    => ['label' => 'Charcoal & Steel',        'primary' => '#23282E', 'accent' => '#9FB3C8', 'background' => '#F4F6F8'],
    'midnight'    => ['label' => 'Midnight Violet',         'primary' => '#2D1B4E', 'accent' => '#C9A227', 'background' => '#F8F7FB'],
    'ocean'       => ['label' => 'Ocean Teal',              'primary' => '#0F4C5C', 'accent' => '#B0C4DE', 'background' => '#F4F8FA'],
    'burgundy'    => ['label' => 'Burgundy & Cream',        'primary' => '#6E1423', 'accent' => '#E8D5B7', 'background' => '#FDF9F4'],
    'emerald'     => ['label' => 'Emerald & Gold',          'primary' => '#0B3D2E', 'accent' => '#D4AF37', 'background' => '#F7FAF8'],
    'slate'       => ['label' => 'Slate & Gold',            'primary' => '#334155', 'accent' => '#D4AF37', 'background' => '#F8FAFC'],
];

/**
 * Ready-made heading + body font pairings (Google Fonts).
 * Keys are stored in theme.fonts and used to build the <link> + CSS stacks.
 */
const FONT_PAIRS = [
    'classic'    => ['label' => 'Classic Elegance',    'display' => 'Playfair Display', 'body' => 'Inter'],
    'editorial'  => ['label' => 'Editorial',           'display' => 'Merriweather',     'body' => 'Source Sans 3'],
    'literary'   => ['label' => 'Literary',            'display' => 'Lora',             'body' => 'Karla'],
    'modern'     => ['label' => 'Modern Corporate',    'display' => 'Montserrat',       'body' => 'Open Sans'],
    'luxe'       => ['label' => 'Luxury Serif',        'display' => 'Cormorant Garamond','body' => 'Jost'],
    'friendly'   => ['label' => 'Friendly & Rounded',  'display' => 'Poppins',          'body' => 'Roboto'],
];

/** Accept only strict #RRGGBB hex colours — anything else falls back. */
function sanitize_hex_color(string $value, string $fallback = '#0B192C'): string
{
    $value = trim($value);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtoupper($value) : $fallback;
}

/** Convert #RRGGBB to [h, s, l] in 0..1 ranges. */
function theme_hsl(string $hex): array
{
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    $d = $max - $min;
    if ($d == 0.0) {
        return [0.0, 0.0, $l];
    }
    $s = $d / (1 - abs(2 * $l - 1));
    switch ($max) {
        case $r: $h = 60 * fmod((($g - $b) / $d), 6); break;
        case $g: $h = 60 * ((($b - $r) / $d) + 2);   break;
        default: $h = 60 * ((($r - $g) / $d) + 4);
    }
    if ($h < 0) {
        $h += 360;
    }
    return [$h, $s, $l];
}

/** Convert [h, s, l] (0..1) back to #RRGGBB. */
function theme_hsl_to_hex(float $h, float $s, float $l): string
{
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;
    if ($h < 60)      { $r = $c; $g = $x; $b = 0; }
    elseif ($h < 120) { $r = $x; $g = $c; $b = 0; }
    elseif ($h < 180) { $r = 0; $g = $c; $b = $x; }
    elseif ($h < 240) { $r = 0; $g = $x; $b = $c; }
    elseif ($h < 300) { $r = $x; $g = 0; $b = $c; }
    else              { $r = $c; $g = 0; $b = $x; }
    $toHex = static function (float $v) use ($m): string {
        return str_pad(dechex((int)round(max(0, min(1, $v + $m)) * 255)), 2, '0', STR_PAD_LEFT);
    };
    return '#' . strtoupper($toHex($r) . $toHex($g) . $toHex($b));
}

/** Lighten (+) or darken (−) a hex colour by a lightness delta (−1..1). */
function theme_shade(string $hex, float $delta): string
{
    [$h, $s, $l] = theme_hsl($hex);
    return theme_hsl_to_hex($h, $s, max(0.0, min(1.0, $l + $delta)));
}

/**
 * CSS font stacks for the chosen pairing: [displayStack, bodyStack].
 * Falls back to the classic pair for unknown keys.
 */
function theme_font_stacks(): array
{
    $key = content_text('theme.fonts');
    $pair = FONT_PAIRS[$key] ?? FONT_PAIRS['classic'];
    return [
        "'" . $pair['display'] . "', Georgia, 'Times New Roman', serif",
        "'" . $pair['body'] . "', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
    ];
}

/**
 * CSS variable overrides for the chosen theme. Injected into the page after
 * main.css so the public site re-colours without touching any code. The
 * derived shades (700/900/300) are generated so every theme stays coherent.
 */
function theme_css_vars(): string
{
    $primary    = sanitize_hex_color(content_text('theme.primary'), '#0B192C');
    $accent     = sanitize_hex_color(content_text('theme.accent'), '#D4AF37');
    $background = sanitize_hex_color(content_text('theme.background'), '#F8FAFC');
    [$displayStack, $bodyStack] = theme_font_stacks();

    $vars = ':root{'
        . '--primary:' . $primary . ';'
        . '--primary-700:' . theme_shade($primary, 0.08) . ';'
        . '--primary-900:' . theme_shade($primary, -0.04) . ';'
        . '--surface:' . theme_shade($primary, 0.07) . ';'
        . '--accent:' . $accent . ';'
        . '--accent-300:' . theme_shade($accent, 0.11) . ';'
        . '--accent-700:' . theme_shade($accent, -0.11) . ';'
        . '--bg:' . $background . ';'
        . '--font-display:' . $displayStack . ';'
        . '--font-body:' . $bodyStack . ';';

    // Optional per-section backgrounds. Emitted ONLY when set, so a section
    // with no custom colour keeps its built-in default (the theme colour).
    foreach (['hero', 'stats', 'cta', 'footer'] as $key) {
        $hex = sanitize_hex_color(content_text('theme.sections.' . $key), '');
        if ($hex !== '') {
            $vars .= '--section-' . $key . '-bg:' . $hex . ';';
            // Stat tiles get a slightly lighter shade of the band colour so
            // the number strip stays coherent when re-coloured.
            if ($key === 'stats') {
                $vars .= '--section-stats-tile:' . theme_shade($hex, 0.06) . ';';
            }
        }
    }

    return $vars . '}';
}

/* ===========================================================================
 * MEDIA LIBRARY — placing images on the site
 * ========================================================================= */

/**
 * Every image spot on the public site that the media library can fill.
 * Each slot has a dot-path into the content tree (target) plus a human
 * label/group used by the admin picker.
 */
function media_slots(): array
{
    // Note: brand.logo is intentionally LAST so the "Place on site" dialog
    // keeps "Hero background image" as its default selection.
    $slots = [
        [
            'key'    => 'hero.image',
            'target' => 'hero.image',
            'group'  => 'Homepage',
            'label'  => 'Hero background image',
            'path'   => content_text('hero.image'),
        ],
        [
            'key'    => 'about.image',
            'target' => 'about.image',
            'group'  => 'Homepage',
            'label'  => 'About section photo',
            'path'   => content_text('about.image'),
        ],
        [
            'key'    => 'brand.logo',
            'target' => 'brand.logo',
            'group'  => 'Branding',
            'label'  => 'Company logo (header & footer)',
            'path'   => content_text('brand.logo'),
        ],
    ];

    foreach (content_arr('services.items') as $i => $svc) {
        $title = trim((string)($svc['title'] ?? ('Pillar ' . ((int)$i + 1))));
        $slots[] = [
            'key'    => 'services.items.' . $i . '.icon',
            'target' => 'services.items.' . $i . '.icon',
            'group'  => 'Services — ' . $title,
            'label'  => 'Card icon (homepage)',
            'path'   => (string)($svc['icon'] ?? ''),
        ];
        $slots[] = [
            'key'    => 'services.items.' . $i . '.page_image',
            'target' => 'services.items.' . $i . '.page_image',
            'group'  => 'Services — ' . $title,
            'label'  => 'Large photo (Services page)',
            'path'   => (string)($svc['page_image'] ?? ''),
        ];
    }

    return $slots;
}

/**
 * Map of image path → human labels of the site spots currently using it.
 * Powers the "Used in" badges on the media library page.
 */
function media_usage_map(): array
{
    $map = [];
    foreach (media_slots() as $slot) {
        $path = trim((string)$slot['path']);
        if ($path === '') {
            continue;
        }
        $map[$path][] = $slot['label'];
    }
    return $map;
}

/** Set a value at a dot path in the content tree, creating nodes as needed. */
function content_set_path(array &$tree, string $dotPath, $value): void
{
    $parts = explode('.', $dotPath);
    $lastIndex = count($parts) - 1;
    $node = &$tree;
    foreach ($parts as $i => $segment) {
        if ($i === $lastIndex) {
            $node[$segment] = $value;
            return;
        }
        if (!isset($node[$segment]) || !is_array($node[$segment])) {
            $node[$segment] = [];
        }
        $node = &$node[$segment];
    }
}

/**
 * Place an uploaded library image into a specific spot on the website.
 * Returns ['ok' => bool, 'message' => string].
 */
function media_place_image(string $slotKey, string $path): array
{
    $slot = null;
    foreach (media_slots() as $candidate) {
        if ($candidate['key'] === $slotKey) {
            $slot = $candidate;
            break;
        }
    }
    if ($slot === null) {
        return ['ok' => false, 'message' => 'That image spot no longer exists — refresh the page and try again.'];
    }

    $path = trim($path);
    if ($path === '' || sanitize_asset_path($path) === '') {
        return ['ok' => false, 'message' => 'That image is not a valid media file.'];
    }

    // Only allow files from the media library folder itself.
    if (!str_starts_with($path, 'assets/images/content/')) {
        return ['ok' => false, 'message' => 'Only images from the media library can be placed.'];
    }
    $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
        return ['ok' => false, 'message' => 'Only image files can be placed on the site.'];
    }
    $file = BASE_PATH . '/' . $path;
    if (!is_file($file)) {
        return ['ok' => false, 'message' => 'The image file is missing from the server.'];
    }

    $tree = load_content_tree();
    content_set_path($tree, $slot['target'], $path);
    if (!save_content($tree)) {
        return ['ok' => false, 'message' => 'Could not save the change — check that the content folder is writable.'];
    }

    return ['ok' => true, 'message' => 'Placed this image on the site: ' . $slot['label']];
}

/* ===========================================================================
 * PARTNERS (partner_logos table)
 * ========================================================================= */

function get_partners(): array
{
    static $cache = null;

    if ($cache === null) {
        try {
            $cache = db()
                ->query(
                    'SELECT id, org_name, logo_path, category, display_order
                     FROM partner_logos
                     WHERE is_active = 1
                     ORDER BY display_order ASC, id ASC'
                )
                ->fetchAll();
        } catch (PDOException) {
            $cache = [];
        }
    }

    return $cache;
}

/* ===========================================================================
 * ADMIN AUTH
 * ========================================================================= */

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('index.php');
    }

    // A deleted admin account must not keep working through its old session.
    // Only log out when the account is PROVABLY gone (the query succeeded and
    // returned 0 rows) — a temporary database outage must not log everyone out.
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM admin_users WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        if ((int)$stmt->fetchColumn() === 0) {
            $_SESSION = [];
            session_destroy();
            redirect('index.php');
        }
    } catch (PDOException) {
        // DB unavailable — let the request continue.
    }
}

/** Create the default admin (admin / admin123) the first time /admin is opened. */
function ensure_default_admin(): void
{
    ensure_admin_schema();
    try {
        $count = (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($count === 0) {
            $stmt = db()->prepare(
                'INSERT INTO admin_users (username, email, password_hash) VALUES (?, ?, ?)'
            );
            $stmt->execute([
                'admin',
                'admin@owereassociates.com',
                password_hash('admin123', PASSWORD_DEFAULT),
            ]);
        }
    } catch (PDOException) {
        // Database not reachable — the login form will surface the error.
    }
}

/**
 * Add the admin profile columns (display_name, avatar) to existing databases.
 * Runs before any admin query touches admin_users, so older installs created
 * from an earlier schema.sql upgrade themselves in place — no manual SQL needed.
 */
function ensure_admin_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $cols = db()->query('SHOW COLUMNS FROM admin_users')->fetchAll(PDO::FETCH_COLUMN);
        $cols = array_map('strval', $cols);
        if (!in_array('display_name', $cols, true)) {
            db()->exec('ALTER TABLE admin_users ADD COLUMN `display_name` VARCHAR(100) DEFAULT NULL AFTER `email`');
        }
        if (!in_array('avatar', $cols, true)) {
            db()->exec('ALTER TABLE admin_users ADD COLUMN `avatar` VARCHAR(255) DEFAULT NULL AFTER `display_name`');
        }
        if (!in_array('role', $cols, true)) {
            db()->exec('ALTER TABLE admin_users ADD COLUMN `role` ENUM(\'owner\',\'admin\') NOT NULL DEFAULT \'admin\' AFTER `avatar`');
        }
        // Backfill: the first-ever admin account becomes the owner if none exists.
        $ownerCount = (int)db()->query('SELECT COUNT(*) FROM admin_users WHERE role = \'owner\'')->fetchColumn();
        if ($ownerCount === 0) {
            $stmt = db()->prepare('SELECT id FROM admin_users ORDER BY id ASC LIMIT 1');
            $stmt->execute();
            $firstId = $stmt->fetchColumn();
            if ($firstId) {
                db()->prepare('UPDATE admin_users SET role = \'owner\' WHERE id = ?')->execute([$firstId]);
            }
        }
    } catch (PDOException) {
        // Table may not exist yet — schema.sql creates it with both columns.
    }
}

function current_admin(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    ensure_admin_schema();
    try {
        $stmt = db()->prepare('SELECT id, username, email, display_name, avatar FROM admin_users WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        return $admin ?: null;
    } catch (PDOException) {
        return null;
    }
}

/**
 * True when the logged-in admin is the original (owner) account.
 * Only the owner may create or manage other admin accounts.
 */
function is_owner(): bool
{
    if (!is_logged_in()) {
        return false;
    }
    ensure_admin_schema();
    try {
        $stmt = db()->prepare('SELECT role FROM admin_users WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        return (string)$stmt->fetchColumn() === 'owner';
    } catch (PDOException) {
        return false;
    }
}

/* ===========================================================================
 * ADMIN ACTIVITY LOG
 * ========================================================================= */

/**
 * Ensure the admin_activity table exists. Runs once per request; failures are
 * swallowed so an old database upgrades itself lazily without manual SQL.
 */
function ensure_activity_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS `admin_activity` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `admin_id` INT DEFAULT NULL,
                `username` VARCHAR(50) NOT NULL DEFAULT \'system\',
                `action` VARCHAR(50) NOT NULL,
                `details` VARCHAR(255) DEFAULT NULL,
                `ip` VARCHAR(45) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_activity_created` (`created_at`),
                INDEX `idx_activity_action` (`action`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (PDOException) {
        // Database not reachable — logging is best-effort.
    }
}

/**
 * Record an admin action in the activity log.
 * Never throws and never blocks the main action — failures are swallowed.
 *
 * @param string $action  Machine key, e.g. "content_save", "login", "media_delete".
 * @param string $details Human-readable short summary, e.g. "Deleted lead #12".
 */
function log_admin_activity(string $action, string $details = ''): void
{
    ensure_activity_table();
    try {
        $stmt = db()->prepare(
            'INSERT INTO admin_activity (admin_id, username, action, details, ip) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            !empty($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
            mb_substr((string)($_SESSION['admin_username'] ?? 'system'), 0, 50),
            mb_substr($action, 0, 50),
            mb_substr($details, 0, 250),
            mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    } catch (PDOException) {
        // Best-effort: never break the action being logged.
    }
}

/* ===========================================================================
 * FILE UPLOADS
 * ========================================================================= */

/** App-level ceiling for media library uploads (matches docker/php.ini). */
const MEDIA_MAX_UPLOAD_BYTES = 20 * 1024 * 1024; // 20 MB

/** Parse a PHP ini size value like "10M", "512K" or "2097152" into bytes. */
function ini_size_to_bytes(string $value): int
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return 0;
    }
    $unit = substr($value, -1);
    $num  = (float)$value;
    if ($unit === 'g') {
        $num *= 1024 * 1024 * 1024;
    } elseif ($unit === 'm') {
        $num *= 1024 * 1024;
    } elseif ($unit === 'k') {
        $num *= 1024;
    }
    return (int)$num;
}

/**
 * The real upload ceiling: PHP's own limits (upload_max_filesize,
 * post_max_size) may be lower than our app limit, so the effective cap
 * is the smallest of all three. Used for validation AND the UI copy.
 */
function effective_upload_limit(): int
{
    $phpUpload = ini_size_to_bytes((string)ini_get('upload_max_filesize'));
    $phpPost   = ini_size_to_bytes((string)ini_get('post_max_size'));
    $limit     = MEDIA_MAX_UPLOAD_BYTES;
    if ($phpUpload > 0 && $phpUpload < $limit) {
        $limit = $phpUpload;
    }
    if ($phpPost > 0 && $phpPost < $limit) {
        $limit = $phpPost;
    }
    return $limit;
}

/**
 * Human size label for a byte count, e.g. "20 MB" or "512 KB".
 * When called with no argument, uses the effective (global) upload limit.
 */
function format_upload_limit(?int $bytes = null): string
{
    if ($bytes === null) {
        $bytes = effective_upload_limit();
    }
    if ($bytes >= 1048576) {
        $mb = $bytes / 1048576;
        return rtrim(rtrim(number_format($mb, 1), '0'), '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return (int)ceil($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Validated image uploader for partner logos, avatars and CMS images.
 *
 * @param string $targetDirWeb Web-relative directory under the project root,
 *                             e.g. "assets/images/partners".
 * @return array{ok: bool, path?: string, error?: string} path is web-relative
 */
function upload_image(array $file, string $targetDirWeb, array $allowedExt = ['jpg','jpeg','png','webp','gif','svg'], ?int $maxBytes = null): array
{
    if ($maxBytes === null) {
        $maxBytes = effective_upload_limit();
    }
    if (empty($file['name'])) {
        return ['ok' => false, 'error' => 'No file selected.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE) {
            return ['ok' => false, 'error' => 'The file is larger than the server allows (' . format_upload_limit() . ').'];
        }
        return ['ok' => false, 'error' => 'Upload failed (error code ' . (int)$file['error'] . ').'];
    }
    if ((int)$file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'File exceeds the ' . format_upload_limit($maxBytes) . ' limit.'];
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'error' => 'File type .' . esc($ext) . ' is not allowed.'];
    }

    if ($ext === 'svg') {
        // Reject SVGs that could contain script payloads.
        $raw = (string)@file_get_contents($file['tmp_name']);
        if (stripos($raw, '<script') !== false
            || stripos($raw, 'onload=') !== false
            || stripos($raw, 'javascript:') !== false
            || stripos($raw, 'onerror=') !== false) {
            return ['ok' => false, 'error' => 'This SVG contains disallowed content and was rejected.'];
        }
    } else {
        $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($mime !== '' && isset($mimeMap[$ext]) && $mime !== $mimeMap[$ext]) {
                return ['ok' => false, 'error' => 'File content does not match its extension.'];
            }
        }
    }

    $targetDirWeb = trim($targetDirWeb, '/\\');
    $targetDir    = BASE_PATH . '/' . $targetDirWeb;
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
        return ['ok' => false, 'error' => 'Upload directory could not be created.'];
    }

    $name = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $targetDir . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
    }

    return ['ok' => true, 'path' => $targetDirWeb . '/' . $name];
}

/* ===========================================================================
 * MAIL — native SMTP client (no external dependencies)
 * ========================================================================= */

/**
 * Send an HTML email over SMTP (STARTTLS on 587, implicit TLS on 465).
 * Returns false when mail is not configured — the inquiry is still saved to
 * the database regardless.
 */
function send_mail(array $to, string $subject, string $html): bool
{
    $host     = get_setting('smtp_host', SMTP_HOST);
    $port     = (int)get_setting('smtp_port', (string)SMTP_PORT);
    $user     = get_setting('smtp_user', SMTP_USER);
    $pass     = get_setting('smtp_pass', SMTP_PASS);
    $from     = get_setting('mail_from', MAIL_FROM);
    $fromName = get_setting('mail_from_name', MAIL_FROM_NAME);

    if ($host === '' || $user === '' || $pass === '' || $from === '') {
        return false; // Not configured yet — DB record is the fallback.
    }

    // Port 465 uses implicit TLS from the very first byte; 587 upgrades via STARTTLS.
    $scheme = $port === 465 ? 'ssl' : 'tcp';
    $socket = @stream_socket_client("{$scheme}://{$host}:{$port}", $errno, $errstr, 15);
    if (!$socket) {
        return false;
    }
    stream_set_timeout($socket, 15);

    $read  = static function () use ($socket): string {
        return (string)fgets($socket, 515);
    };
    $ehlo  = static function () use ($socket): bool {
        fwrite($socket, "EHLO localhost\r\n");
        $last = '';
        while (($line = fgets($socket, 515)) !== false) {
            $last = $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break; // end of multi-line reply
            }
        }
        return preg_match('/^250/', $last) === 1;
    };

    $read(); // banner

    if (!$ehlo()) {
        fclose($socket);
        return false;
    }

    if ($port === 587) {
        fwrite($socket, "STARTTLS\r\n");
        if (preg_match('/^220/', $read()) !== 1) {
            fclose($socket);
            return false;
        }
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$ehlo()) {
            fclose($socket);
            return false;
        }
    }

    fwrite($socket, "AUTH LOGIN\r\n");
    if (preg_match('/^334/', $read()) !== 1) { fclose($socket); return false; }
    fwrite($socket, base64_encode($user) . "\r\n");
    if (preg_match('/^334/', $read()) !== 1) { fclose($socket); return false; }
    fwrite($socket, base64_encode($pass) . "\r\n");
    if (preg_match('/^235/', $read()) !== 1) { fclose($socket); return false; }

    fwrite($socket, "MAIL FROM:<{$from}>\r\n");
    if (preg_match('/^250/', $read()) !== 1) { fclose($socket); return false; }

    foreach ($to as $addr) {
        fwrite($socket, "RCPT TO:<{$addr}>\r\n");
        if (preg_match('/^250/', $read()) !== 1) { fclose($socket); return false; }
    }

    fwrite($socket, "DATA\r\n");
    if (preg_match('/^354/', $read()) !== 1) { fclose($socket); return false; }

    // Strip CR/LF from every header value to prevent header injection.
    $clean  = static function (string $v): string {
        return str_replace(["\r", "\n"], '', $v);
    };
    $from   = $clean($from);
    $fromName = $clean($fromName);
    $subject  = $clean($subject);
    $to      = array_map($clean, $to);

    $headers = [
        "From: {$fromName} <{$from}>",
        'To: ' . implode(', ', $to),
        "Subject: {$subject}",
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Date: ' . date('r'),
        'X-Mailer: Owere-Associates/1.0',
    ];

    fwrite(
        $socket,
        implode("\r\n", $headers) . "\r\n\r\n" . $html . "\r\n.\r\n"
    );
    $response = $read();

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return preg_match('/^250/', $response) === 1;
}

/* ===========================================================================
 * TEMPLATE HELPERS
 * ========================================================================= */

function format_date(?string $date, string $format = 'M j, Y'): string
{
    $ts = $date ? strtotime($date) : time();
    return $ts ? date($format, $ts) : '—';
}

function time_ago(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts  = strtotime($date);
    $diff = time() - $ts;
    if ($diff < 60)   return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $ts);
}

function truncate(string $text, int $length = 140): string
{
    $text = trim($text);
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    $cut = mb_substr($text, 0, $length);
    $pos = mb_strrpos($cut, ' ');
    return ($pos === false ? $cut : mb_substr($cut, 0, $pos)) . '…';
}
