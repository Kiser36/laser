<?php
/**
 * Owere & Associates — services page (three core pillars).
 * All text is editable from the admin panel (admin/content.php).
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Services';
$activeNav = 'services';
$seoKey    = 'services';

$flash = consume_flash();

$servicesEyebrow = content_text('services_head.eyebrow');
$servicesTitle   = content_text('services_head.title');
$servicesLede    = content_text('services_head.page_lede');
$servicesItems   = content_arr('services.items');

// Engagement process
$processEyebrow = content_text('process.eyebrow');
$processTitle   = content_text('process.title');
$processSteps   = content_arr('process.steps');

// CTA band
$ctaEyebrow = content_text('cta_band.eyebrow');
$ctaTitle   = content_text('cta_band.title');
$ctaLede    = content_text('cta_band.lede');
$ctaCta     = content_text('cta_band.cta');

include __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert--<?= esc($flash['type']) ?>" data-alert>
        <span><?= esc($flash['message']) ?></span>
        <button type="button" class="alert__close" data-alert-close aria-label="Dismiss">&times;</button>
    </div>
<?php endif; ?>

<!-- ============ PAGE BANNER ============ -->
<section class="page-banner">
    <div class="container">
        <span class="eyebrow eyebrow--center"><?= esc($servicesEyebrow) ?></span>
        <h1><?= render_emphasis($servicesTitle) ?></h1>
        <p class="page-banner__lede"><?= esc($servicesLede) ?></p>
    </div>
</section>

<!-- ============ SERVICE PILLARS ============ -->
<?php foreach ($servicesItems as $i => $svc): ?>
    <?php
        $key     = (string)($svc['key'] ?? ('pillar-' . $i));
        $num     = (string)($svc['num'] ?? sprintf('%02d', $i + 1));
        $title   = (string)($svc['title'] ?? '');
        $icon    = (string)($svc['icon'] ?? 'assets/images/icons/briefcase.svg');
        $lede    = (string)($svc['page_lede'] ?? '');
        $image   = (string)($svc['page_image'] ?? 'assets/images/about-visual.svg');
        $imageAlt= (string)($svc['page_image_alt'] ?? $title);
        $items   = (array)($svc['items'] ?? []);
        $altBg   = $i % 2 === 1;
    ?>
    <section class="section<?= $altBg ? ' section--bg' : '' ?>" id="<?= esc($key) ?>">
        <div class="container service-detail<?= $altBg ? ' service-detail--alt' : '' ?>">
            <div class="reveal">
                <div class="service-detail__icon">
                    <img src="<?= esc($icon) ?>" alt="" width="40" height="40">
                </div>
                <span class="service-detail__num"><?= esc($num) ?> &mdash; Pillar</span>
                <h2><?= esc($title) ?></h2>
                <p class="service-detail__lede"><?= esc($lede) ?></p>
                <ul class="service-detail__list">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <img src="assets/images/icons/checkmark.svg" alt="" width="18" height="18">
                            <span><strong><?= esc((string)($item['heading'] ?? '')) ?></strong><?= (string)($item['text'] ?? '') !== '' ? ' — ' . esc((string)$item['text']) : '' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="service-detail__actions">
                    <a href="#" class="btn btn--gold" data-modal-open="booking" data-service="<?= esc($title) ?>">Book Consultation</a>
                    <a href="<?= esc(whatsapp_link(whatsapp_service_message($title), (string)($svc['whatsapp'] ?? ''))) ?>" class="btn btn--wa" target="_blank" rel="noopener">Quick Inquiry via WhatsApp</a>
                </div>
            </div>
            <div class="reveal reveal-delay-1">
                <div class="about-visual__frame" style="aspect-ratio:4/3.4;">
                    <img src="<?= esc($image) ?>" alt="<?= esc($imageAlt) ?>" style="width:100%;height:100%;object-fit:cover;">
                </div>
            </div>
        </div>
    </section>
<?php endforeach; ?>

<!-- ============ ENGAGEMENT PROCESS ============ -->
<section class="section section--primary">
    <div class="container">
        <div class="section__head reveal">
            <span class="eyebrow eyebrow--center"><?= esc($processEyebrow) ?></span>
            <h2 class="section__title"><?= render_emphasis($processTitle) ?></h2>
        </div>
        <div class="stats-band reveal">
            <?php foreach ($processSteps as $step): ?>
                <div class="stat">
                    <div class="stat__num" style="font-size:30px;"><?= esc((string)($step['num'] ?? '')) ?></div>
                    <div class="stat__label"><?= esc((string)($step['label'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ CTA BAND ============ -->
<section class="cta-band">
    <div class="container">
        <span class="eyebrow eyebrow--center reveal"><?= esc($ctaEyebrow) ?></span>
        <h2 class="reveal reveal-delay-1"><?= nl2br(render_emphasis($ctaTitle)) ?></h2>
        <p class="reveal reveal-delay-2"><?= esc($ctaLede) ?></p>
        <a href="#" class="btn btn--gold reveal reveal-delay-3" data-modal-open="booking"><?= esc($ctaCta) ?></a>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
