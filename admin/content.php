<?php
/**
 * Owere & Associates — Website Content (full CMS).
 *
 * A no-code editor for the entire public site. Content is organised into
 * tabs that mirror the pages a visitor sees, with rich-text fields,
 * inline image uploads and repeatable lists. Saved to content/site-content.json
 * with automatic timestamped backups; every value falls back to built-in
 * defaults so the site can never be broken from here.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_login();

$pageTitle = 'Website Content';
$activeNav = 'content';

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
    || (($_GET['ajax'] ?? '') === '1');

/* ---------------------------------------------------------------------------
 * POST actions (handled before any HTML output so redirects always work)
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['cms_action'] ?? '');

    if ($action === 'save') {
        $tree    = build_content_tree($_POST);
        $saved   = save_content($tree);
        $message = $saved
            ? 'Website content saved — your changes are now live.'
            : 'Content could not be saved. Check that the content folder is writable.';
        if ($saved) {
            log_admin_activity('content_save', 'Saved website content');
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $saved, 'message' => $message]);
            exit;
        }
        flash($saved ? 'success' : 'error', $message);
        redirect('content.php');
    }

    if ($action === 'reset') {
        $sections = array_values(array_filter(array_map('strval', (array)($_POST['sections'] ?? []))));
        if ($sections !== []) {
            reset_content_sections($sections);
        }
        log_admin_activity('content_reset', 'Restored defaults: ' . implode(', ', $sections));
        $message = 'Section restored to its original default text.';

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'message' => $message]);
            exit;
        }
        flash('success', $message);
        redirect('content.php');
    }
}

/* ---------------------------------------------------------------------------
 * Build the full content tree from the submitted form
 * ------------------------------------------------------------------------- */
function build_content_tree(array $p): array
{
    $plain = static function (string $k, string $d = '') use ($p): string {
        return strip_tags(trim((string)($p[$k] ?? $d)));
    };
    $rich = static function (string $k, string $d = '') use ($p): string {
        return sanitize_rich_text((string)($p[$k] ?? $d));
    };
    $asset = static function (string $k, string $d = '') use ($p): string {
        return sanitize_asset_path((string)($p[$k] ?? $d));
    };
    $href = static function (string $k, string $d = '#') use ($p): string {
        return sanitize_href((string)($p[$k] ?? $d));
    };

    /** Map a list of indexed rows (name[0][field]) into clean records. */
    $list = static function (array $rows, callable $map): array {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mapped = $map($row);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }
        return $out;
    };

    $row2 = static function (array $r): ?array {
        if (trim((string)($r['label'] ?? '')) === '' && trim((string)($r['href'] ?? '')) === '') {
            return null;
        }
        return [
            'label' => strip_tags(trim((string)($r['label'] ?? ''))),
            'href'  => sanitize_href((string)($r['href'] ?? '#')),
        ];
    };
    $row3 = static function (array $r): ?array {
        if (trim((string)($r['value'] ?? '')) === '' && trim((string)($r['label'] ?? '')) === '') {
            return null;
        }
        return [
            'value' => preg_replace('/[^0-9]/', '', strip_tags(trim((string)($r['value'] ?? '')))),
            'suffix'=> strip_tags(trim((string)($r['suffix'] ?? ''))),
            'label' => strip_tags(trim((string)($r['label'] ?? ''))),
        ];
    };

    $services = [];
    foreach ((array)($p['service'] ?? []) as $svc) {
        if (!is_array($svc)) {
            continue;
        }
        $title = strip_tags(trim((string)($svc['title'] ?? '')));
        if ($title === '') {
            continue;
        }
        // Keep pillar names within the DB column limit (VARCHAR 100) so the
        // booking form can never fail because of a long service name.
        $title = mb_substr($title, 0, 100);
        $services[] = [
            'key'            => preg_replace('/[^a-z0-9_\-]/i', '', strtolower((string)($svc['key'] ?? ''))) ?: 'pillar',
            'num'            => strip_tags(trim((string)($svc['num'] ?? ''))),
            'icon'           => sanitize_asset_path((string)($svc['icon'] ?? '')),
            'title'          => $title,
            'card_body'      => strip_tags(trim((string)($svc['card_body'] ?? ''))),
            'page_lede'      => strip_tags(trim((string)($svc['page_lede'] ?? ''))),
            'page_image'     => sanitize_asset_path((string)($svc['page_image'] ?? '')),
            'page_image_alt' => strip_tags(trim((string)($svc['page_image_alt'] ?? ''))),
            'whatsapp'       => preg_replace('/[^\d+]/', '', strip_tags(trim((string)($svc['whatsapp'] ?? '')))),
            'items'          => $list((array)($svc['items'] ?? []), static function (array $r): ?array {
                if (trim((string)($r['heading'] ?? '')) === '' && trim((string)($r['text'] ?? '')) === '') {
                    return null;
                }
                return [
                    'heading' => strip_tags(trim((string)($r['heading'] ?? ''))),
                    'text'    => strip_tags(trim((string)($r['text'] ?? ''))),
                ];
            }),
        ];
    }

    return [
        'brand' => [
            'name'    => $plain('brand_name'),
            'mark'    => $plain('brand_mark'),
            'tagline' => $plain('brand_tagline'),
            'logo'    => $asset('brand_logo'),
        ],
        'seo' => [
            'home_title'          => $plain('seo_home_title'),
            'home_description'    => $plain('seo_home_description'),
            'services_title'      => $plain('seo_services_title'),
            'services_description'=> $plain('seo_services_description'),
        ],
        'theme' => [
            'preset'     => $plain('theme_preset'),
            'primary'    => sanitize_hex_color((string)($p['theme_primary'] ?? ''), '#0B192C'),
            'accent'     => sanitize_hex_color((string)($p['theme_accent'] ?? ''), '#D4AF37'),
            'background' => sanitize_hex_color((string)($p['theme_background'] ?? ''), '#F8FAFC'),
            'fonts'      => isset(FONT_PAIRS[$plain('theme_fonts')]) ? $plain('theme_fonts') : 'classic',
            'sections'   => [
                // '' keeps the section on the theme's main colour.
                'hero'   => sanitize_hex_color((string)($p['theme_section_hero'] ?? ''), ''),
                'stats'  => sanitize_hex_color((string)($p['theme_section_stats'] ?? ''), ''),
                'cta'    => sanitize_hex_color((string)($p['theme_section_cta'] ?? ''), ''),
                'footer' => sanitize_hex_color((string)($p['theme_section_footer'] ?? ''), ''),
            ],
        ],
        'nav' => [
            'links' => $list((array)($p['nav_links'] ?? []), $row2),
            'cta'   => $plain('nav_cta'),
        ],
        'hero' => [
            'eyebrow'       => $plain('hero_eyebrow'),
            'title'         => $plain('hero_title'),
            'subtitle'      => $plain('hero_subtitle'),
            'primary_cta'   => $plain('hero_primary_cta'),
            'secondary_cta' => $plain('hero_secondary_cta'),
            'card_label'    => $plain('hero_card_label'),
            'badge'         => $plain('hero_badge'),
            'image'         => $asset('hero_image'),
            'stats'         => $list((array)($p['hero_stats'] ?? []), $row3),
        ],
        'logos' => [
            'heading'    => $plain('logos_heading'),
            'empty_note' => $plain('logos_empty_note'),
        ],
        'services_head' => [
            'eyebrow'    => $plain('services_eyebrow'),
            'title'      => $plain('services_title'),
            'card_cta'   => $plain('services_card_cta'),
            'card_wa'    => $plain('services_card_wa'),
            'learn_more' => $plain('services_learn_more'),
            'page_lede'  => $plain('services_page_lede'),
        ],
        'services' => ['items' => $services],
        'about' => [
            'eyebrow'     => $plain('about_eyebrow'),
            'title'       => $plain('about_title'),
            'body'        => $rich('about_body'),
            'image'       => $asset('about_image'),
            'image_alt'   => $plain('about_image_alt'),
            'quote'       => $plain('about_quote'),
            'quote_cite'  => $plain('about_quote_cite'),
            'points'      => $list((array)($p['about_points'] ?? []), static function (array $r): ?array {
                if (trim((string)($r['heading'] ?? '')) === '' && trim((string)($r['text'] ?? '')) === '') {
                    return null;
                }
                return [
                    'heading' => strip_tags(trim((string)($r['heading'] ?? ''))),
                    'text'    => strip_tags(trim((string)($r['text'] ?? ''))),
                ];
            }),
            'cta'         => $plain('about_cta'),
        ],
        'stats' => [
            'items' => $list((array)($p['stats'] ?? []), $row3),
        ],
        'process' => [
            'eyebrow' => $plain('process_eyebrow'),
            'title'   => $plain('process_title'),
            'steps'   => $list((array)($p['process'] ?? []), static function (array $r): ?array {
                if (trim((string)($r['label'] ?? '')) === '') {
                    return null;
                }
                return [
                    'num'   => strip_tags(trim((string)($r['num'] ?? ''))),
                    'label' => strip_tags(trim((string)($r['label'] ?? ''))),
                ];
            }),
        ],
        'gallery' => [
            'eyebrow' => $plain('gallery_eyebrow'),
            'title'   => $plain('gallery_title'),
            'items'   => $list((array)($p['gallery'] ?? []), static function (array $r): ?array {
                $image = sanitize_asset_path((string)($r['image'] ?? ''));
                if ($image === '') {
                    return null;
                }
                return [
                    'image' => $image,
                    'alt'   => strip_tags(trim((string)($r['alt'] ?? ''))),
                ];
            }),
        ],
        'cta_band' => [
            'eyebrow' => $plain('cta_eyebrow'),
            'title'   => $plain('cta_title'),
            'lede'    => $plain('cta_lede'),
            'cta'     => $plain('cta_cta'),
        ],
        'faq' => [
            'eyebrow' => $plain('faq_eyebrow'),
            'title'   => $plain('faq_title'),
            'lede'    => $plain('faq_lede'),
            'items'   => $list((array)($p['faq'] ?? []), static function (array $r): ?array {
                if (trim((string)($r['question'] ?? '')) === '' && trim((string)($r['answer'] ?? '')) === '') {
                    return null;
                }
                return [
                    'question' => strip_tags(trim((string)($r['question'] ?? ''))),
                    'answer'   => strip_tags(trim((string)($r['answer'] ?? ''))),
                ];
            }),
        ],
        'contact' => [
            'eyebrow'             => $plain('contact_eyebrow'),
            'title'               => $plain('contact_title'),
            'lede'                => $plain('contact_lede'),
            'cards'               => $list((array)($p['contact_cards'] ?? []), static function (array $r): ?array {
                $heading = strip_tags(trim((string)($r['heading'] ?? '')));
                return $heading === '' ? null : ['heading' => $heading];
            }),
            'form_heading'        => $plain('contact_form_heading'),
            'form_lede'           => $plain('contact_form_lede'),
            'submit'              => $plain('contact_submit'),
            'service_placeholder' => $plain('contact_service_placeholder'),
            'general_option'      => $plain('contact_general_option'),
        ],
        'booking' => [
            'eyebrow' => $plain('booking_eyebrow'),
            'title'   => $plain('booking_title'),
            'lede'    => $plain('booking_lede'),
            'submit'  => $plain('booking_submit'),
            'note'    => $plain('booking_note'),
        ],
        'footer' => [
            'blurb'          => $plain('footer_blurb'),
            'col_services'   => $plain('footer_col_services'),
            'col_firm'       => $plain('footer_col_firm'),
            'col_contact'    => $plain('footer_col_contact'),
            'services_links' => $list((array)($p['footer_services'] ?? []), $row2),
            'firm_links'     => $list((array)($p['footer_firm'] ?? []), static function (array $r): ?array {
                if (trim((string)($r['label'] ?? '')) === '' && trim((string)($r['href'] ?? '')) === '') {
                    return null;
                }
                return [
                    'label' => strip_tags(trim((string)($r['label'] ?? ''))),
                    'href'  => sanitize_href((string)($r['href'] ?? '#')),
                    'modal' => !empty($r['modal']),
                ];
            }),
            'socials'        => $list((array)($p['footer_socials'] ?? []), static function (array $r): ?array {
                $network = strip_tags(trim((string)($r['network'] ?? '')));
                if (!in_array($network, ['linkedin', 'x', 'whatsapp'], true)) {
                    return null;
                }
                return [
                    'network' => $network,
                    'href'    => sanitize_href((string)($r['href'] ?? '#')),
                    'label'   => strip_tags(trim((string)($r['label'] ?? ''))),
                ];
            }),
            'bottom_left'    => $plain('footer_bottom_left'),
            'bottom_links'   => $list((array)($p['footer_bottom'] ?? []), $row2),
        ],
    ];
}

require_once __DIR__ . '/../includes/admin-header.php';

/* ---------------------------------------------------------------------------
 * Small CMS UI helpers
 * ------------------------------------------------------------------------- */

/** Rich-text editor field (bold/italic/lists/links, no HTML needed). */
function cms_rte(string $name, string $value, string $label, string $hint = ''): void
{
    $html = render_rich($value); // handles both stored HTML and plain text
    ?>
    <div class="form__field">
        <label><?= esc($label) ?></label>
        <div class="rte" data-rte>
            <div class="rte__toolbar" role="toolbar" aria-label="Formatting tools">
                <button type="button" data-rte-cmd="bold" title="Bold" tabindex="-1"><b>B</b></button>
                <button type="button" data-rte-cmd="italic" title="Italic" tabindex="-1"><i>I</i></button>
                <button type="button" data-rte-cmd="underline" title="Underline" tabindex="-1"><u>U</u></button>
                <span class="rte__sep" aria-hidden="true"></span>
                <button type="button" data-rte-cmd="h3" title="Subheading" tabindex="-1">H</button>
                <button type="button" data-rte-cmd="insertUnorderedList" title="Bullet list" tabindex="-1">• List</button>
                <button type="button" data-rte-cmd="insertOrderedList" title="Numbered list" tabindex="-1">1. List</button>
                <button type="button" data-rte-cmd="createLink" title="Add a link" tabindex="-1">🔗 Link</button>
                <button type="button" data-rte-cmd="insertImage" title="Insert an image from the library" tabindex="-1">🖼 Image</button>
                <button type="button" data-rte-cmd="removeFormat" title="Clear formatting" tabindex="-1">✕ Clear</button>
            </div>
            <?php
                // Image srcs are stored site-relative (assets/…). In the admin
                // editor the page lives in /admin/, so preview them with ../.
                $htmlPreview = str_replace('src="assets/', 'src="../assets/', $html);
            ?>
            <div class="rte__editor" contenteditable="true" data-rte-editor><?= $htmlPreview ?></div>
        </div>
        <input type="hidden" name="<?= esc($name) ?>" value="<?= esc($value) ?>" data-rte-input>
        <?php if ($hint !== ''): ?><p class="form__hint"><?= esc($hint) ?></p><?php endif; ?>
    </div>
    <?php
}

/** Inline image upload field with live preview. */
function cms_image(string $name, string $value, string $label, string $hint = ''): void
{
    ?>
    <div class="img-field" data-img-field>
        <span class="img-field__label"><?= esc($label) ?></span>
        <input type="hidden" name="<?= esc($name) ?>" value="<?= esc($value) ?>" data-img-input>
        <div class="img-field__preview" data-img-preview>
            <?php if ($value !== ''): ?>
                <img src="../<?= esc($value) ?>" alt="Current image preview">
            <?php else: ?>
                <span class="img-field__empty">No image — click “Upload image”</span>
            <?php endif; ?>
        </div>
        <div class="img-field__actions">
            <button type="button" class="btn btn--ghost btn--sm" data-img-upload>Upload image</button>
            <button type="button" class="btn btn--ghost btn--sm" data-img-library>Choose from library</button>
            <button type="button" class="btn btn--ghost btn--sm" data-img-remove>Remove</button>
            <input type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" hidden data-img-file>
        </div>
        <?php if ($hint !== ''): ?><p class="form__hint"><?= esc($hint) ?></p><?php endif; ?>
    </div>
    <?php
}

/**
 * Per-section background colour picker (Design & Theme tab).
 * An empty value means "use the theme's main colour" — the hex input posts
 * the empty string and theme_css_vars() simply skips that variable.
 */
function cms_section_color(string $key, string $label, string $hint = ''): void
{
    $saved    = content_text('theme.sections.' . $key);
    $fallback = content_text('theme.primary');
    ?>
    <div class="form__field">
        <label><?= esc($label) ?></label>
        <div class="theme-color" data-section-row>
            <input type="color" value="<?= esc($saved !== '' ? $saved : $fallback) ?>" data-section-color data-zone="<?= esc($key) ?>" aria-label="<?= esc($label) ?> colour">
            <input type="text" name="theme_section_<?= esc($key) ?>" value="<?= esc($saved) ?>" maxlength="7" pattern="#[0-9a-fA-F]{6}" placeholder="#RRGGBB" data-section-hex data-zone="<?= esc($key) ?>" aria-label="<?= esc($label) ?> hex code">
            <button type="button" class="btn btn--ghost btn--sm" data-section-reset title="Reset to the site's main colour">Use theme colour</button>
        </div>
        <?php if ($hint !== ''): ?><p class="form__hint"><?= esc($hint) ?></p><?php endif; ?>
    </div>
    <?php
}

/** Repeatable list editor. $base is the form name prefix for one record,
 *  e.g. "nav_links" or "service[0][items]". $fields is a list of
 *  [field, label, placeholder, cssClass]. */
function cms_list(string $base, array $rows, array $fields, string $addLabel): void
{
    ?>
    <div class="list-editor" data-list data-name="<?= esc($base) ?>" data-index="<?= count($rows) ?>">
        <div class="list-editor__rows" data-list-rows>
            <?php foreach ($rows as $idx => $row): ?>
                <div class="list-editor__row" data-list-row>
                    <?php foreach ($fields as $f): ?>
                        <?php [$fname, $flabel, $fph, $fcls] = $f; ?>
                        <input type="text"
                               name="<?= esc($base . '[' . $idx . '][' . $fname . ']') ?>"
                               value="<?= esc((string)($row[$fname] ?? '')) ?>"
                               placeholder="<?= esc($fph) ?>"
                               class="list-editor__input <?= esc($fcls) ?>"
                               aria-label="<?= esc($flabel) ?>">
                    <?php endforeach; ?>
                    <button type="button" class="icon-btn icon-btn--danger" data-list-remove title="Remove this row" aria-label="Remove row">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn--ghost btn--sm" data-list-add>+ <?= esc($addLabel) ?></button>
        <template data-list-template>
            <div class="list-editor__row" data-list-row>
                <?php foreach ($fields as $f): ?>
                    <?php [$fname, $flabel, $fph, $fcls] = $f; ?>
                    <input type="text"
                           name="<?= esc($base . '[__i__][' . $fname . ']') ?>"
                           placeholder="<?= esc($fph) ?>"
                           class="list-editor__input <?= esc($fcls) ?>"
                           aria-label="<?= esc($flabel) ?>">
                <?php endforeach; ?>
                <button type="button" class="icon-btn icon-btn--danger" data-list-remove title="Remove this row" aria-label="Remove row">&times;</button>
            </div>
        </template>
    </div>
    <?php
}

/**
 * Render ONE service pillar editor block. $i is the form index — an int for
 * existing pillars or the literal string '__i__' inside the new-pillar
 * template (admin.js replaces it with a real index when cloning).
 */
function cms_pillar(string $i, array $svc): void
{
    $title = (string)($svc['title'] ?? '');
    $num   = (string)($svc['num'] ?? '');
    ?>
    <div class="cms-group pillar-block" data-pillar-block>
        <div class="cms-group__head">
            <h3 class="cms-group__title">Pillar <span data-pillar-live-num><?= esc($num) ?></span> — <span data-pillar-live-title><?= esc($title !== '' ? $title : 'New pillar') ?></span></h3>
            <button type="button" class="btn btn--danger btn--sm" data-pillar-remove title="Remove this pillar">Remove pillar</button>
        </div>
        <input type="hidden" name="service[<?= esc($i) ?>][key]" value="<?= esc((string)($svc['key'] ?? '')) ?>" data-pillar-key>
        <div class="form">
            <div class="form__row">
                <div class="form__field">
                    <label for="svc<?= esc($i) ?>_title">Service name</label>
                    <input type="text" id="svc<?= esc($i) ?>_title" name="service[<?= esc($i) ?>][title]" value="<?= esc($title) ?>" maxlength="100" data-pillar-title>
                    <p class="form__hint">This name also appears in the booking form dropdown (max 100 characters).</p>
                </div>
                <div class="form__field">
                    <label for="svc<?= esc($i) ?>_num">Card number</label>
                    <input type="text" id="svc<?= esc($i) ?>_num" name="service[<?= esc($i) ?>][num]" value="<?= esc($num) ?>" maxlength="4" style="max-width:110px;" data-pillar-num-input>
                </div>
            </div>
            <div class="form__row">
                <div class="form__field">
                    <label for="svc<?= esc($i) ?>_card">Short description (on the homepage card)</label>
                    <textarea id="svc<?= esc($i) ?>_card" name="service[<?= esc($i) ?>][card_body]" rows="3" maxlength="400"><?= esc((string)($svc['card_body'] ?? '')) ?></textarea>
                </div>
                <div class="form__field">
                    <label for="svc<?= esc($i) ?>_lede">Intro paragraph (on the Services page)</label>
                    <textarea id="svc<?= esc($i) ?>_lede" name="service[<?= esc($i) ?>][page_lede]" rows="3" maxlength="400"><?= esc((string)($svc['page_lede'] ?? '')) ?></textarea>
                </div>
            </div>
            <div class="form__row">
                <div class="form__field">
                    <?php cms_image('service[' . $i . '][icon]', (string)($svc['icon'] ?? ''), 'Card icon', 'SVG icon shown on the homepage card.') ?>
                </div>
                <div class="form__field">
                    <?php cms_image('service[' . $i . '][page_image]', (string)($svc['page_image'] ?? ''), 'Large photo (Services page)', 'Landscape image, roughly 4:3.') ?>
                </div>
            </div>
            <div class="form__field">
                <label for="svc<?= esc($i) ?>_alt">Alt text for the large photo</label>
                <input type="text" id="svc<?= esc($i) ?>_alt" name="service[<?= esc($i) ?>][page_image_alt]" value="<?= esc((string)($svc['page_image_alt'] ?? '')) ?>" maxlength="200">
            </div>
            <div class="form__field">
                <label for="svc<?= esc($i) ?>_wa">WhatsApp number for this pillar</label>
                <input type="text" id="svc<?= esc($i) ?>_wa" name="service[<?= esc($i) ?>][whatsapp]" value="<?= esc((string)($svc['whatsapp'] ?? '')) ?>" maxlength="20" placeholder="+256 7XX XXX XXX">
                <p class="form__hint">Leave empty to use the main number from <a href="settings.php">Settings</a>. This pillar's WhatsApp button will open a chat with this number instead.</p>
            </div>
            <div class="form__field">
                <label>Checklist items — bold heading + short explanation</label>
                <?php
                    cms_list('service[' . $i . '][items]', (array)($svc['items'] ?? []), [
                        ['heading', 'Heading', 'e.g. EFRIS setup & integration', 'list-editor__input--wide'],
                        ['text',    'Text',    'e.g. Streamlined electronic fiscal invoicing.', ''],
                    ], 'Add checklist item');
                ?>
            </div>
        </div>
    </div>
    <?php
}

$svcs = content_arr('services.items');
$tabs = [
    'home'     => ['label' => 'Home',             'view' => '../index.php',        'sections' => 'hero,logos,services_head,stats'],
    'gallery'  => ['label' => 'Gallery',          'view' => '../index.php#gallery', 'sections' => 'gallery'],
    'services' => ['label' => 'Services',         'view' => '../services.php',     'sections' => 'services'],
    'about'    => ['label' => 'About & More',     'view' => '../index.php#about',  'sections' => 'about,process,cta_band'],
    'faq'      => ['label' => 'FAQ',              'view' => '../index.php#faq',    'sections' => 'faq'],
    'contact'  => ['label' => 'Contact & Booking','view' => '../index.php#contact','sections' => 'contact,booking'],
    'footer'   => ['label' => 'Footer & Nav',     'view' => '../index.php',        'sections' => 'brand,nav,footer'],
    'seo'      => ['label' => 'SEO & Branding',   'view' => '../index.php',        'sections' => 'seo'],
    'design'   => ['label' => 'Design & Theme',   'view' => '../index.php',        'sections' => 'theme'],
];
?>

<form id="cmsForm" method="post" action="content.php">
    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
    <input type="hidden" name="cms_action" value="save">

    <!-- Tab bar -->
    <div class="cms-tabs" role="tablist">
        <?php foreach ($tabs as $key => $tab): ?>
            <button type="button" class="cms-tab" data-tab="<?= esc($key) ?>" role="tab" aria-selected="false">
                <?= esc($tab['label']) ?>
                <span class="cms-tab__dot" aria-hidden="true"></span>
            </button>
        <?php endforeach; ?>
        <span class="cms-tabs__spacer"></span>
        <a href="../index.php" target="_blank" rel="noopener" class="btn btn--navy btn--sm">View live site</a>
    </div>

    <?php foreach ($tabs as $key => $tab): ?>
    <section class="cms-panel" data-panel="<?= esc($key) ?>" role="tabpanel" hidden>
        <div class="cms-panel__head">
            <p class="cms-panel__desc"><?= esc($tab['label']) ?> text — edits go live as soon as you click <strong>Save</strong>.</p>
            <div class="cms-panel__tools">
                <a href="<?= esc($tab['view']) ?>" target="_blank" rel="noopener" class="btn btn--ghost btn--sm">Preview page</a>
                <?php if (!empty($tab['sections'])): ?>
                    <button type="button" class="btn btn--ghost btn--sm cms-reset" data-reset-sections="<?= esc($tab['sections']) ?>">Restore defaults</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($key === 'home'): ?>
        <!-- ============================= HOME ============================= -->
        <div class="cms-group">
            <h3 class="cms-group__title">Hero — the big headline at the top</h3>
            <div class="form">
                <div class="form__row">
                    <div class="form__field">
                        <label for="hero_eyebrow">Small label above the headline</label>
                        <input type="text" id="hero_eyebrow" name="hero_eyebrow" value="<?= esc(content_text('hero.eyebrow')) ?>" maxlength="120">
                    </div>
                    <div class="form__field">
                        <label for="hero_badge">Badge text (right side)</label>
                        <input type="text" id="hero_badge" name="hero_badge" value="<?= esc(content_text('hero.badge')) ?>" maxlength="40">
                    </div>
                </div>
                <div class="form__field">
                    <label for="hero_title">Main headline</label>
                    <input type="text" id="hero_title" name="hero_title" value="<?= esc(content_text('hero.title')) ?>" maxlength="255">
                    <p class="form__hint">Wrap a word in *asterisks* to make it gold and italic — e.g. <em>precision in every *return*.</em></p>
                </div>
                <div class="form__field">
                    <label for="hero_subtitle">Subtitle</label>
                    <textarea id="hero_subtitle" name="hero_subtitle" rows="3" maxlength="600"><?= esc(content_text('hero.subtitle')) ?></textarea>
                </div>
                <div class="form__row">
                    <div class="form__field">
                        <label for="hero_primary_cta">Primary button text</label>
                        <input type="text" id="hero_primary_cta" name="hero_primary_cta" value="<?= esc(content_text('hero.primary_cta')) ?>" maxlength="60">
                    </div>
                    <div class="form__field">
                        <label for="hero_secondary_cta">Secondary button text</label>
                        <input type="text" id="hero_secondary_cta" name="hero_secondary_cta" value="<?= esc(content_text('hero.secondary_cta')) ?>" maxlength="60">
                    </div>
                </div>
                <div class="form__row">
                    <div class="form__field">
                        <label for="hero_card_label">Card label</label>
                        <input type="text" id="hero_card_label" name="hero_card_label" value="<?= esc(content_text('hero.card_label')) ?>" maxlength="120">
                    </div>
                    <div class="form__field">
                        <?php cms_image('hero_image', content_text('hero.image'), 'Optional hero background image', 'Leave empty to use the navy design.') ?>
                    </div>
                </div>
                <div class="form__field">
                    <label>Hero statistics — number, suffix and label per row</label>
                    <?php
                        cms_list('hero_stats', content_arr('hero.stats'), [
                            ['value',  'Number',  'e.g. 420',  'list-editor__input--sm'],
                            ['suffix', 'Suffix',  '+',         'list-editor__input--xs'],
                            ['label',  'Label',   'e.g. Organisations served', ''],
                        ], 'Add statistic');
                    ?>
                </div>
            </div>
        </div>

        <div class="cms-group">
            <h3 class="cms-group__title">Client logos strip</h3>
            <div class="form">
                <div class="form__field">
                    <label for="logos_heading">Heading</label>
                    <input type="text" id="logos_heading" name="logos_heading" value="<?= esc(content_text('logos.heading')) ?>" maxlength="160">
                </div>
                <div class="form__field">
                    <label for="logos_empty_note">Text when no logos have been added yet</label>
                    <input type="text" id="logos_empty_note" name="logos_empty_note" value="<?= esc(content_text('logos.empty_note')) ?>" maxlength="200">
                </div>
                <p class="form__hint">The logos themselves are managed on the <a href="logos.php">Partner Logos</a> page.</p>
            </div>
        </div>

        <div class="cms-group">
            <h3 class="cms-group__title">Services section heading</h3>
            <div class="form">
                <div class="form__row">
                    <div class="form__field">
                        <label for="services_eyebrow">Small label</label>
                        <input type="text" id="services_eyebrow" name="services_eyebrow" value="<?= esc(content_text('services_head.eyebrow')) ?>" maxlength="120">
                    </div>
                    <div class="form__field">
                        <label for="services_title">Title</label>
                        <input type="text" id="services_title" name="services_title" value="<?= esc(content_text('services_head.title')) ?>" maxlength="160">
                    </div>
                </div>
                <div class="form__row">
                    <div class="form__field">
                        <label for="services_card_cta">Button label on each card</label>
                        <input type="text" id="services_card_cta" name="services_card_cta" value="<?= esc(content_text('services_head.card_cta')) ?>" maxlength="40">
                    </div>
                    <div class="form__field">
                        <label for="services_card_wa">WhatsApp button label</label>
                        <input type="text" id="services_card_wa" name="services_card_wa" value="<?= esc(content_text('services_head.card_wa')) ?>" maxlength="40">
                    </div>
                </div>
                <div class="form__row">
                    <div class="form__field">
                        <label for="services_learn_more">“Learn more” link text</label>
                        <input type="text" id="services_learn_more" name="services_learn_more" value="<?= esc(content_text('services_head.learn_more')) ?>" maxlength="40">
                    </div>
                    <div class="form__field">
                        <label for="services_page_lede">Intro paragraph on the Services page</label>
                        <input type="text" id="services_page_lede" name="services_page_lede" value="<?= esc(content_text('services_head.page_lede')) ?>" maxlength="400">
                    </div>
                </div>
            </div>
        </div>

        <div class="cms-group">
            <h3 class="cms-group__title">Stats band — the navy strip of numbers</h3>
            <div class="form">
                <div class="form__field">
                    <label>Statistics — number, suffix and label per row</label>
                    <?php
                        cms_list('stats', content_arr('stats.items'), [
                            ['value',  'Number',  'e.g. 1200', 'list-editor__input--sm'],
                            ['suffix', 'Suffix',  '+',         'list-editor__input--xs'],
                            ['label',  'Label',   'e.g. Returns filed', ''],
                        ], 'Add statistic');
                    ?>
                </div>
            </div>
        </div>

        <?php elseif ($key === 'gallery'): ?>
        <!-- ============================ GALLERY =========================== -->
        <div class="cms-group">
            <h3 class="cms-group__title">Photo gallery — an inside look at the firm</h3>
            <div class="form">
                <div class="form__row">
                    <div class="form__field">
                        <label for="gallery_eyebrow">Small label</label>
                        <input type="text" id="gallery_eyebrow" name="gallery_eyebrow" value="<?= esc(content_text('gallery.eyebrow')) ?>" maxlength="120">
                    </div>
                    <div class="form__field">
                        <label for="gallery_title">Title</label>
                        <input type="text" id="gallery_title" name="gallery_title" value="<?= esc(content_text('gallery.title')) ?>" maxlength="160">
                    </div>
                </div>
                <div class="form__field">
                    <label>Photos — add as many as you like</label>
                    <div class="list-editor" data-list data-name="gallery" data-index="<?= count(content_arr('gallery.items')) ?>">
                        <div class="list-editor__rows" data-list-rows>
                            <?php foreach (content_arr('gallery.items') as $idx => $g): ?>
                                <div class="list-editor__row" data-list-row>
                                    <?php cms_image('gallery[' . (int)$idx . '][image]', (string)($g['image'] ?? ''), 'Photo', '') ?>
                                    <input type="text" name="gallery[<?= (int)$idx ?>][alt]" value="<?= esc((string)($g['alt'] ?? '')) ?>" placeholder="Caption / alt text" class="list-editor__input" aria-label="Caption / alt text">
                                    <button type="button" class="icon-btn icon-btn--danger" data-list-remove title="Remove this photo" aria-label="Remove photo">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn--ghost btn--sm" data-list-add>+ Add photo</button>
                        <template data-list-template>
                            <div class="list-editor__row" data-list-row>
                                <?php cms_image('gallery[__i__][image]', '', 'Photo', '') ?>
                                <input type="text" name="gallery[__i__][alt]" value="" placeholder="Caption / alt text" class="list-editor__input" aria-label="Caption / alt text">
                                <button type="button" class="icon-btn icon-btn--danger" data-list-remove title="Remove this photo" aria-label="Remove photo">&times;</button>
                            </div>
                        </template>
                    </div>
                    <p class="form__hint">These photos appear in a responsive grid on the homepage (below the About section) and the section stays hidden until you add at least one photo. Add photos with the “Upload image” button, or pick existing ones from the <a href="media.php">Media Library</a>.</p>
                </div>
            </div>
        </div>

        <?php elseif ($key === 'services'): ?>
        <!-- =========================== SERVICES =========================== -->
        <div class="pillars" data-pillars data-index="<?= count($svcs) ?>">
            <?php foreach ($svcs as $i => $svc): ?>
                <?php cms_pillar((string)$i, (array)$svc); ?>
            <?php endforeach; ?>
        </div>

        <div class="pillars__add">
            <button type="button" class="btn btn--ghost" data-pillar-add>+ Add another pillar</button>
            <p class="form__hint">Add as many service pillars as you need — each gets its own card on the homepage, its own section on the Services page, and its own option in the booking form. The card number (01, 02…) is whatever you type; at least one pillar is required.</p>
        </div>

        <template data-pillar-template>
            <?php cms_pillar('__i__', []); ?>
        </template>

        <?php elseif ($key === 'about'): ?>
        <!-- ======================== ABOUT & MORE ========================= -->
        <div class="cms-group">
            <h3 class="cms-group__title">About the firm</h3>
            <div class="form">
                <div class="form__row">
                    <div class="form__field">
                        <label for="about_eyebrow">Small label</label>
                        <input type="text" id="about_eyebrow" name="about_eyebrow" value="<?= esc(content_text('about.eyebrow')) ?>" maxlength="120">
                    </div>
                    <div class="form__field">
                        <label for="about_title">Title</label>
                        <input type="text" id="about_title" name="about_title" value="<?= esc(content_text('about.title')) ?>" maxlength="160">
                    </div>
                </div>
                <?php cms_rte('about_body', content_text('about.body'), 'Body text', 'Use the toolbar to bold, italicise or bullet your text. The 🖼 Image button drops photos from the media library straight into your paragraphs — click where you want the photo, then press 🖼 Image.') ?>
                <div class="form__row">
                    <div class="form__field">
                        <?php cms_image('about_image', content_text('about.image'), 'Photo', 'Shown beside the About text.') ?>
                    </div>
                    <div class="form__field">
                        <label for="about_image_alt">Alt text for the photo</label>
                        <input type="text" id="about_image_alt" name="about_image_alt" value="<?= esc(content_text('about.image_alt')) ?>" maxlength="200">
                    </div>
                </div>
                <div class="form__row">
                    <div class="form__field">
                        <label for="about_quote">Pull quote</label>
                        <textarea id="about_quote" name="about_quote" rows="2" maxlength="300"><?= esc(content_text('about.quote')) ?></textarea>
                    </div>
                    <div class="form__field">
                        <label for="about_quote_cite">Quote attribution</label>
                        <input type="text" id="about_quote_cite" name="about_quote_cite" value="<?= esc(content_text('about.quote_cite')) ?>" maxlength="160">
                    </div>
                </div>
                <div class="form__field">
                    <label>“Why us” points — bold heading + short text</label>
                    <?php
                        cms_list('about_points', content_arr('about.points'), [
                            ['heading', 'Heading', 'e.g. Partner-led service', 'list-editor__input--wide'],
                            ['text',    'Text',    'e.g. Senior expertise on every file.', ''],
                        ], 'Add point');
                    ?>
                </div>
                <div class="form__field" style="max-width:320px;">
                    <label for="about_cta">Button text</label>
                    <input type="text" id="about_cta" name="about_cta" value="<?= esc(content_text('about.cta')) ?>" maxlength="60">
                </div>
            </div>
        </div>

        <div class="cms-group">
            <h3 class="cms-group__title">“How we work” process (Services page)</h3>
            <div class="form">
                <div class="form__row">
                    <div class="form__field">
                        <label for="process_eyebrow">Small label</label>
                        <input type="text" id="process_eyebrow" name="process_eyebrow" value="<?= esc(content_text('process.eyebrow')) ?>" maxlength="120">
                    </div>
                    <div class="form__field">
                        <label for="process_title">Title</label>
                        <input type="text" id="process_title" name="process_title" value="<?= esc(content_text('process.title')) ?>" maxlength="160">
                    </div>
                </div>
                <div class="form__field">
                    <label>Steps — number and label</label>
                    <?php
                        cms_list('process', content_arr('process.steps'), [
                            ['num',   'Number', 'e.g. 01', 'list-editor__input--xs'],
                            ['label', 'Label',  'e.g. Consultation', ''],
                        ], 'Add step');
                    ?>
                </div>
            </div>
        </div>

        <div class="cms-group">
            <h3 class="cms-group__title">Closing call-to-action band (Services page)</h3>
            <div class="form">
                <div class="form__row">
                    <div class="form__field">
                        <label for="cta_eyebrow">Small label</label>
                        <input type="text" id="cta_eyebrow" name="cta_eyebrow" value="<?= esc(content_text('cta_band.eyebrow')) ?>" maxlength="120">
                    </div>
                    <div class="form__field">
                        <label for="cta_cta">Button text</label>
                        <input type="text" id="cta_cta" name="cta_cta" value="<?= esc(content_text('cta_band.cta')) ?>" maxlength="60">
                    </div>
                </div>
                <div class="form__field">
                    <label for="cta_title">Headline</label>
                    <input type="text" id="cta_title" name="cta_title" value="<?= esc(content_text('cta_band.title')) ?>" maxlength="160">
                </div>
                <div class="form__field">
                    <label for="cta_lede">Paragraph</label>
                    <textarea id="cta_lede" name="cta_lede" rows="2" maxlength="400"><?= esc(content_text('cta_band.lede')) ?></textarea>
                </div>
            </div>
        </div>

        <?php elseif ($key === 'faq'): ?>
        <!-- ============================ FAQ ============================== -->
        <div class="cms-group">
            <h3 class="cms-group__title">FAQ — accordion on the homepage</h3>
            <div class="form">
                <div class="form__row">
                    <div class="form__field">
                        <label for="faq_eyebrow">Small label</label>
                        <input type="text" id="faq_eyebrow" name="faq_eyebrow" value="<?= esc(content_text('faq.eyebrow')) ?>" maxlength="120">
                    </div>
                    <div class="form__field">
                        <label for="faq_title">Title</label>
                        <input type="text" id="faq_title" name="faq_title" value="<?= esc(content_text('faq.title')) ?>" maxlength="160">
                        <p class="form__hint">Wrap a word in *asterisks* to make it gold and italic.</p>
                    </div>
                </div>
                <div class="form__field">
                    <label for="faq_lede">Intro paragraph</label>
                    <textarea id="faq_lede" name="faq_lede" rows="2" maxlength="400"><?= esc(content_text('faq.lede')) ?></textarea>
                </div>
                <div class="form__field">
                    <label>Questions — question and answer per row</label>
                    <div class="list-editor" data-list data-name="faq" data-index="<?= count(content_arr('faq.items')) ?>">
                        <div class="list-editor__rows" data-list-rows>
                            <?php foreach (content_arr('faq.items') as $idx => $fq): ?>
                                <div class="list-editor__row" data-list-row>
                                    <input type="text" name="faq[<?= (int)$idx ?>][question]" value="<?= esc((string)($fq['question'] ?? '')) ?>" placeholder="e.g. How much does a consultation cost?" class="list-editor__input list-editor__input--wide" aria-label="Question">
                                    <textarea name="faq[<?= (int)$idx ?>][answer]" rows="3" maxlength="1000" placeholder="e.g. The first consultation is free and confidential…" class="list-editor__input list-editor__input--wide" aria-label="Answer"><?= esc((string)($fq['answer'] ?? '')) ?></textarea>
                                    <button type="button" class="icon-btn icon-btn--danger" data-list-remove title="Remove this row" aria-label="Remove row">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn--ghost btn--sm" data-list-add>+ Add question</button>
                        <template data-list-template>
                            <div class="list-editor__row" data-list-row>
                                <input type="text" name="faq[__i__][question]" placeholder="e.g. How much does a consultation cost?" class="list-editor__input list-editor__input--wide" aria-label="Question">
                                <textarea name="faq[__i__][answer]" rows="3" maxlength="1000" placeholder="e.g. The first consultation is free and confidential…" class="list-editor__input list-editor__input--wide" aria-label="Answer"></textarea>
                                <button type="button" class="icon-btn icon-btn--danger" data-list-remove title="Remove this row" aria-label="Remove row">&times;</button>
                            </div>
                        </template>
                    </div>
                    <p class="form__hint">The section stays hidden on the homepage until you add at least one question. These questions also feed the FAQ rich results in Google.</p>
                </div>
            </div>
        </div>

        <?php elseif ($key === 'contact'): ?>
        <!-- ======================= CONTACT & BOOKING ====================== -->
        <div class="cms-group">
            <h3 class="cms-group__title">Contact section</h3>
            <div class="form">
                <div class="form__row">
                    <div class="form__field">
                        <label for="contact_eyebrow">Small label</label>
                        <input type="text" id="contact_eyebrow" name="contact_eyebrow" value="<?= esc(content_text('contact.eyebrow')) ?>" maxlength="120">
                    </div>
                    <div class="form__field">
                        <label for="contact_title">Title</label>
                        <input type="text" id="contact_title" name="contact_title" value="<?= esc(content_text('contact.title')) ?>" maxlength="160">
                    </div>
                </div>
                <div class="form__field">
                    <label for="contact_lede">Intro paragraph</label>
                    <textarea id="contact_lede" name="contact_lede" rows="2" maxlength="400"><?= esc(content_text('contact.lede')) ?></textarea>
                </div>
                <div class="form__field">
                    <label>Card headings (Visit us · Call us · Email us · Office hours)</label>
                    <?php
                        cms_list('contact_cards', content_arr('contact.cards'), [
                            ['heading', 'Heading', 'e.g. Visit us', ''],
                        ], 'Add card');
                    ?>
                    <p class="form__hint">The actual address, phone, email and hours are set under <a href="settings.php">Settings</a>.</p>
                </div>
            </div>
        </div>

        <div class="cms-group">
            <h3 class="cms-group__title">Enquiry form (Contact page)</h3>
            <div class="form">
                <div class="form__row">
                    <div class="form__field">
                        <label for="contact_form_heading">Form title</label>
                        <input type="text" id="contact_form_heading" name="contact_form_heading" value="<?= esc(content_text('contact.form_heading')) ?>" maxlength="120">
                    </div>
                    <div class="form__field">
                        <label for="contact_submit">Submit button text</label>
                        <input type="text" id="contact_submit" name="contact_submit" value="<?= esc(content_text('contact.submit')) ?>" maxlength="60">
                    </div>
                </div>
                <div class="form__field">
                    <label for="contact_form_lede">Small intro under the form title</label>
                    <input type="text" id="contact_form_lede" name="contact_form_lede" value="<?= esc(content_text('contact.form_lede')) ?>" maxlength="200">
                </div>
                <div class="form__row">
                    <div class="form__field">
                        <label for="contact_service_placeholder">Dropdown placeholder text</label>
                        <input type="text" id="contact_service_placeholder" name="contact_service_placeholder" value="<?= esc(content_text('contact.service_placeholder')) ?>" maxlength="80">
                    </div>
                    <div class="form__field">
                        <label for="contact_general_option">“Other” option label</label>
                        <input type="text" id="contact_general_option" name="contact_general_option" value="<?= esc(content_text('contact.general_option')) ?>" maxlength="80">
                    </div>
                </div>
                <p class="form__hint">The service dropdown options come from the pillar names you set on the <strong>Services</strong> tab.</p>
            </div>
        </div>

        <div class="cms-group">
            <h3 class="cms-group__title">Booking pop-up (opens from every button)</h3>
            <div class="form">
                <div class="form__row">
                    <div class="form__field">
                        <label for="booking_eyebrow">Small label</label>
                        <input type="text" id="booking_eyebrow" name="booking_eyebrow" value="<?= esc(content_text('booking.eyebrow')) ?>" maxlength="120">
                    </div>
                    <div class="form__field">
                        <label for="booking_submit">Submit button text</label>
                        <input type="text" id="booking_submit" name="booking_submit" value="<?= esc(content_text('booking.submit')) ?>" maxlength="60">
                    </div>
                </div>
                <div class="form__field">
                    <label for="booking_title">Title</label>
                    <input type="text" id="booking_title" name="booking_title" value="<?= esc(content_text('booking.title')) ?>" maxlength="160">
                </div>
                <div class="form__field">
                    <label for="booking_lede">Intro paragraph</label>
                    <textarea id="booking_lede" name="booking_lede" rows="2" maxlength="400"><?= esc(content_text('booking.lede')) ?></textarea>
                </div>
                <div class="form__field">
                    <label for="booking_note">Footnote</label>
                    <input type="text" id="booking_note" name="booking_note" value="<?= esc(content_text('booking.note')) ?>" maxlength="200">
                    <p class="form__hint">{link} is replaced by the WhatsApp link automatically.</p>
                </div>
            </div>
        </div>

        <?php elseif ($key === 'footer'): ?>
        <!-- ======================= FOOTER & NAV ========================== -->
        <div class="cms-group">
            <h3 class="cms-group__title">Company logo</h3>
            <div class="form">
                <div class="form__field">
                    <?php cms_image('brand_logo', content_text('brand.logo'), 'Company logo image', 'Optional: upload your real logo (PNG/SVG) and it replaces the “OA” initials box in the header and footer. Leave empty to keep the initials. The browser-tab icon is a separate file — see below.') ?>
                </div>
            </div>
        </div>

        <div class="cms-group">
            <h3 class="cms-group__title">Firm name &amp; logo mark</h3>
            <div class="form">
                <div class="form__row">
                    <div class="form__field">
                        <label for="brand_name">Firm name</label>
                        <input type="text" id="brand_name" name="brand_name" value="<?= esc(content_text('brand.name')) ?>" maxlength="80">
                    </div>
                    <div class="form__field">
                        <label for="brand_mark">Logo initials</label>
                        <input type="text" id="brand_mark" name="brand_mark" value="<?= esc(content_text('brand.mark')) ?>" maxlength="4" style="max-width:90px;">
                        <p class="form__hint">Used only when no company logo image is set above.</p>
                    </div>
                </div>
                <div class="form__field" style="max-width:50%;">
                    <label for="brand_tagline">Tagline under the name</label>
                    <input type="text" id="brand_tagline" name="brand_tagline" value="<?= esc(content_text('brand.tagline')) ?>" maxlength="80">
                </div>
                <p class="form__hint">🔎 The browser-tab icon lives in the code at <code>assets/images/icons/favicon.svg</code> — replace that one file to change it (not editable here).</p>
            </div>
        </div>

        <div class="cms-group">
            <h3 class="cms-group__title">Top navigation menu</h3>
            <div class="form">
                <div class="form__field">
                    <label>Menu links — label and destination</label>
                    <?php
                        cms_list('nav_links', content_arr('nav.links'), [
                            ['label', 'Label', 'e.g. Home', 'list-editor__input--wide'],
                            ['href',  'Destination', 'e.g. index.php or index.php#about', ''],
                        ], 'Add link');
                    ?>
                </div>
                <div class="form__field" style="max-width:320px;">
                    <label for="nav_cta">Button label at the end of the menu</label>
                    <input type="text" id="nav_cta" name="nav_cta" value="<?= esc(content_text('nav.cta')) ?>" maxlength="60">
                </div>
            </div>
        </div>

        <div class="cms-group">
            <h3 class="cms-group__title">Footer</h3>
            <div class="form">
                <div class="form__field">
                    <label for="footer_blurb">Short description of the firm</label>
                    <textarea id="footer_blurb" name="footer_blurb" rows="3" maxlength="400"><?= esc(content_text('footer.blurb')) ?></textarea>
                </div>
                <div class="form__row">
                    <div class="form__field">
                        <label for="footer_col_services">First column heading</label>
                        <input type="text" id="footer_col_services" name="footer_col_services" value="<?= esc(content_text('footer.col_services')) ?>" maxlength="60">
                    </div>
                    <div class="form__field">
                        <label for="footer_col_firm">Second column heading</label>
                        <input type="text" id="footer_col_firm" name="footer_col_firm" value="<?= esc(content_text('footer.col_firm')) ?>" maxlength="60">
                    </div>
                </div>
                <div class="form__row">
                    <div class="form__field">
                        <label for="footer_col_contact">Third column heading</label>
                        <input type="text" id="footer_col_contact" name="footer_col_contact" value="<?= esc(content_text('footer.col_contact')) ?>" maxlength="60">
                    </div>
                    <div class="form__field">
                        <label for="footer_bottom_left">Copyright line</label>
                        <input type="text" id="footer_bottom_left" name="footer_bottom_left" value="<?= esc(content_text('footer.bottom_left')) ?>" maxlength="200">
                        <p class="form__hint">{year} is replaced with the current year automatically.</p>
                    </div>
                </div>
                <div class="form__field">
                    <label>“Services” column links</label>
                    <?php
                        cms_list('footer_services', content_arr('footer.services_links'), [
                            ['label', 'Label', 'e.g. Tax Advisory & Compliance', 'list-editor__input--wide'],
                            ['href',  'Destination', 'e.g. services.php#tax', ''],
                        ], 'Add link');
                    ?>
                </div>
                <div class="form__field">
                    <label>“Firm” column links — tick the box to open the booking pop-up instead of a page</label>
                    <div class="list-editor" data-list data-name="footer_firm" data-index="<?= count(content_arr('footer.firm_links')) ?>">
                        <div class="list-editor__rows" data-list-rows>
                            <?php foreach (content_arr('footer.firm_links') as $idx => $row): ?>
                                <div class="list-editor__row" data-list-row>
                                    <input type="text" name="footer_firm[<?= (int)$idx ?>][label]" value="<?= esc((string)($row['label'] ?? '')) ?>" placeholder="e.g. About Us" class="list-editor__input list-editor__input--wide" aria-label="Label">
                                    <input type="text" name="footer_firm[<?= (int)$idx ?>][href]" value="<?= esc((string)($row['href'] ?? '')) ?>" placeholder="e.g. index.php#about" class="list-editor__input" aria-label="Destination">
                                    <label class="list-editor__check" title="Open booking pop-up">
                                        <input type="checkbox" name="footer_firm[<?= (int)$idx ?>][modal]" value="1" <?= !empty($row['modal']) ? 'checked' : '' ?>>
                                        <span>Pop-up</span>
                                    </label>
                                    <button type="button" class="icon-btn icon-btn--danger" data-list-remove title="Remove this row" aria-label="Remove row">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn--ghost btn--sm" data-list-add>+ Add link</button>
                        <template data-list-template>
                            <div class="list-editor__row" data-list-row>
                                <input type="text" name="footer_firm[__i__][label]" placeholder="e.g. About Us" class="list-editor__input list-editor__input--wide" aria-label="Label">
                                <input type="text" name="footer_firm[__i__][href]" placeholder="e.g. index.php#about" class="list-editor__input" aria-label="Destination">
                                <label class="list-editor__check" title="Open booking pop-up">
                                    <input type="checkbox" name="footer_firm[__i__][modal]" value="1">
                                    <span>Pop-up</span>
                                </label>
                                <button type="button" class="icon-btn icon-btn--danger" data-list-remove title="Remove this row" aria-label="Remove row">&times;</button>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="form__field">
                    <label>Social media links — network, destination, label</label>
                    <?php
                        cms_list('footer_socials', content_arr('footer.socials'), [
                            ['network', 'Network (linkedin / x / whatsapp)', 'linkedin', 'list-editor__input--sm'],
                            ['href',    'Destination', 'https://… (or wa for WhatsApp)', ''],
                            ['label',   'Label', 'e.g. LinkedIn', 'list-editor__input--sm'],
                        ], 'Add social link');
                    ?>
                </div>
                <div class="form__field">
                    <label>Small links in the footer bottom bar</label>
                    <?php
                        cms_list('footer_bottom', content_arr('footer.bottom_links'), [
                            ['label', 'Label', 'e.g. Privacy Policy', 'list-editor__input--wide'],
                            ['href',  'Destination', 'e.g. privacy.php', ''],
                        ], 'Add link');
                    ?>
                </div>
            </div>
        </div>

        <?php elseif ($key === 'design'): ?>
        <!-- ======================== DESIGN & THEME ======================= -->
        <div class="cms-group">
            <h3 class="cms-group__title">Colours &amp; theme — no code needed</h3>
            <div class="form">
                <input type="hidden" name="theme_preset" value="<?= esc(content_text('theme.preset')) ?>" data-theme-preset-input>
                <div class="form__field">
                    <label>Pick a preset palette</label>
                    <div class="theme-presets" id="themePresets">
                        <?php foreach (THEME_PRESETS as $pk => $p): ?>
                            <button type="button" class="theme-preset <?= content_text('theme.preset') === $pk ? 'is-selected' : '' ?>"
                                    data-theme-preset="<?= esc($pk) ?>"
                                    data-primary="<?= esc($p['primary']) ?>"
                                    data-accent="<?= esc($p['accent']) ?>"
                                    data-background="<?= esc($p['background']) ?>">
                                <span class="theme-preset__swatch">
                                    <i style="background:<?= esc($p['primary']) ?>"></i>
                                    <i style="background:<?= esc($p['accent']) ?>"></i>
                                    <i style="background:<?= esc($p['background']) ?>"></i>
                                </span>
                                <span class="theme-preset__name"><?= esc($p['label']) ?></span>
                            </button>
                        <?php endforeach; ?>
                        <button type="button" class="theme-preset <?= !isset(THEME_PRESETS[content_text('theme.preset')]) ? 'is-selected' : '' ?>" data-theme-preset="custom">
                            <span class="theme-preset__swatch theme-preset__swatch--custom"><i></i><i></i><i></i></span>
                            <span class="theme-preset__name">Custom…</span>
                        </button>
                    </div>
                    <p class="form__hint">Click a palette to preview it below — nothing is saved until you click <strong>Save Changes</strong>.</p>
                </div>

                <div class="form__row">
                    <div class="form__field">
                        <label>Main colour <span class="form__optional">(header, footer, buttons)</span></label>
                        <div class="theme-color">
                            <input type="color" id="theme_primary" value="<?= esc(content_text('theme.primary')) ?>" data-theme-color aria-label="Main colour">
                            <input type="text" name="theme_primary" value="<?= esc(content_text('theme.primary')) ?>" maxlength="7" pattern="#[0-9a-fA-F]{6}" data-theme-hex aria-label="Main colour hex">
                        </div>
                    </div>
                    <div class="form__field">
                        <label>Accent colour <span class="form__optional">(highlights, buttons, gold bits)</span></label>
                        <div class="theme-color">
                            <input type="color" id="theme_accent" value="<?= esc(content_text('theme.accent')) ?>" data-theme-color aria-label="Accent colour">
                            <input type="text" name="theme_accent" value="<?= esc(content_text('theme.accent')) ?>" maxlength="7" pattern="#[0-9a-fA-F]{6}" data-theme-hex aria-label="Accent colour hex">
                        </div>
                    </div>
                </div>

                <div class="form__field" style="max-width:50%;">
                    <label>Page background</label>
                    <div class="theme-color">
                        <input type="color" id="theme_background" value="<?= esc(content_text('theme.background')) ?>" data-theme-color aria-label="Page background">
                        <input type="text" name="theme_background" value="<?= esc(content_text('theme.background')) ?>" maxlength="7" pattern="#[0-9a-fA-F]{6}" data-theme-hex aria-label="Page background hex">
                    </div>
                </div>

                <div class="form__field">
                    <label>Fonts — heading &amp; body pairing</label>
                    <input type="hidden" name="theme_fonts" value="<?= esc(content_text('theme.fonts')) ?>" data-theme-fonts-input>
                    <div class="theme-presets" id="themeFonts">
                        <?php foreach (FONT_PAIRS as $fk => $f): ?>
                            <button type="button" class="theme-preset font-preset <?= content_text('theme.fonts') === $fk ? 'is-selected' : '' ?>"
                                    data-theme-fonts="<?= esc($fk) ?>"
                                    data-display="<?= esc($f['display']) ?>"
                                    data-body="<?= esc($f['body']) ?>">
                                <span class="font-preset__name" style="font-family:'<?= esc($f['display']) ?>', Georgia, serif;"><?= esc($f['display']) ?></span>
                                <span class="font-preset__body" style="font-family:'<?= esc($f['body']) ?>', sans-serif;"><?= esc($f['body']) ?></span>
                                <span class="theme-preset__name"><?= esc($f['label']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <p class="form__hint">Heading + body pairing used across the whole site. The preview below shows the real fonts — click a card to load and preview it.</p>
                </div>

                <div class="theme-preview" id="themePreview">
                    <div class="theme-preview__nav" data-preview-primary></div>
                    <div class="theme-preview__hero" data-preview-hero></div>
                    <div class="theme-preview__body">
                        <span class="theme-preview__title" data-preview-title>Sample headline</span>
                        <span class="theme-preview__text">Sample body text sitting on the page background.</span>
                        <span class="theme-preview__btn" data-preview-accent>Button</span>
                    </div>
                    <div class="theme-preview__stats" data-preview-stats></div>
                    <div class="theme-preview__cta" data-preview-cta></div>
                    <div class="theme-preview__foot" data-preview-footer></div>
                </div>

                <p class="form__hint">These colours and fonts re-skin the whole public site instantly after saving — darker and lighter shades of your main colour are generated automatically so every palette stays coherent. Backgrounds of specific sections (e.g. the hero) can also be set with an image from the Media Library.</p>
            </div>
        </div>

        <div class="cms-group">
            <h3 class="cms-group__title">Section backgrounds — colour the main bands separately</h3>
            <div class="form">
                <p class="form__hint">These bands use white text, so pick darker colours. “Use theme colour” keeps that band on the site's main colour.</p>
                <div class="form__row">
                    <?php cms_section_color('hero', 'Hero band', 'The big opening area under the menu (homepage). A background image you set under Home still shows on top.') ?>
                    <?php cms_section_color('stats', 'Stats & process band', 'The navy strip of numbers on the homepage and the process section on the Services page.') ?>
                </div>
                <div class="form__row">
                    <?php cms_section_color('cta', 'CTA band', 'The closing call-to-action band on the Services page.') ?>
                    <?php cms_section_color('footer', 'Footer', 'The dark footer at the bottom of every page.') ?>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- ========================= SEO & BRANDING ======================= -->
        <div class="cms-group">
            <h3 class="cms-group__title">Search engines &amp; browser tabs</h3>
            <div class="form">
                <div class="form__row">
                    <div class="form__field">
                        <label for="seo_home_title">Homepage tab title</label>
                        <input type="text" id="seo_home_title" name="seo_home_title" value="<?= esc(content_text('seo.home_title')) ?>" maxlength="80">
                    </div>
                    <div class="form__field">
                        <label for="seo_services_title">Services page tab title</label>
                        <input type="text" id="seo_services_title" name="seo_services_title" value="<?= esc(content_text('seo.services_title')) ?>" maxlength="80">
                    </div>
                </div>
                <div class="form__field">
                    <label for="seo_home_description">Homepage description (shown in Google results)</label>
                    <textarea id="seo_home_description" name="seo_home_description" rows="3" maxlength="300"><?= esc(content_text('seo.home_description')) ?></textarea>
                </div>
                <div class="form__field">
                    <label for="seo_services_description">Services page description (shown in Google results)</label>
                    <textarea id="seo_services_description" name="seo_services_description" rows="3" maxlength="300"><?= esc(content_text('seo.services_description')) ?></textarea>
                </div>
            </div>
        </div>

        <div class="cms-group">
            <h3 class="cms-group__title">Tips</h3>
            <ul class="cms-tips">
                <li>Tab titles appear in the browser tab and in Google results. Keep them under ~60 characters.</li>
                <li>Descriptions are the text under the blue Google link — a clear one-line pitch works best.</li>
                <li>Contact details (phone, address, WhatsApp number) live under <a href="settings.php">Settings</a>.</li>
            </ul>
        </div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>

    <!-- Sticky save bar -->
    <div class="savebar" data-savebar hidden>
        <div class="savebar__status" data-save-status>You have unsaved changes — draft saved automatically</div>
        <div class="savebar__actions">
            <button type="button" class="btn btn--ghost btn--sm" data-save-discard>Discard</button>
            <button type="submit" class="btn btn--gold btn--sm" data-save-btn>Save Changes</button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
