<?php
/**
 * Owere & Associates — public homepage.
 * All text is editable from the admin panel (admin/content.php).
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Home';
$activeNav = 'home';
$seoKey    = 'home';

$flash = consume_flash();

// --- Hero ---
$heroEyebrow   = content_text('hero.eyebrow');
$heroTitle     = content_text('hero.title');
$heroSubtitle  = content_text('hero.subtitle');
$heroPrimary   = content_text('hero.primary_cta');
$heroSecondary = content_text('hero.secondary_cta');
$heroCardLabel = content_text('hero.card_label');
$heroBadge     = content_text('hero.badge');
$heroImage     = content_text('hero.image');
$heroStats     = content_arr('hero.stats');

// --- Logos strip ---
$logosHeading = content_text('logos.heading');
$logosEmpty   = content_text('logos.empty_note');
$partners     = get_partners();

// --- Services ---
$servicesEyebrow = content_text('services_head.eyebrow');
$servicesTitle   = content_text('services_head.title');
$servicesCta     = content_text('services_head.card_cta', 'Book Consultation');
$servicesWa      = content_text('services_head.card_wa', 'WhatsApp');
$servicesMore    = content_text('services_head.learn_more', 'Learn more');
$servicesItems   = content_arr('services.items');

// --- About ---
$aboutEyebrow  = content_text('about.eyebrow');
$aboutTitle    = content_text('about.title');
$aboutBody     = content_text('about.body');
$aboutImage    = content_text('about.image');
$aboutImageAlt = content_text('about.image_alt');
$aboutQuote    = content_text('about.quote');
$aboutCite     = content_text('about.quote_cite');
$aboutPoints   = content_arr('about.points');
$aboutCta      = content_text('about.cta');

// --- Gallery ---
$galleryEyebrow = content_text('gallery.eyebrow');
$galleryTitle   = content_text('gallery.title');
$galleryItems   = content_arr('gallery.items');

// --- Stats band ---
$statItems = content_arr('stats.items');

// --- Contact ---
$contactEyebrow = content_text('contact.eyebrow');
$contactTitle   = content_text('contact.title');
$contactLede    = content_text('contact.lede');
$contactCards   = content_arr('contact.cards');
$formHeading    = content_text('contact.form_heading');
$formLede       = content_text('contact.form_lede');
$formSubmit     = content_text('contact.submit');
$formPlaceholder= content_text('contact.service_placeholder', 'Select a service…');
$serviceOptions = booking_service_options();

// --- FAQ ---
$faqEyebrow = content_text('faq.eyebrow');
$faqTitle   = content_text('faq.title');
$faqLede    = content_text('faq.lede');
$faqItems   = content_arr('faq.items');

$sitePhone   = get_setting('phone_display');
$siteEmail   = get_setting('notification_email');
$siteAddress = get_setting('address');
$siteHours   = get_setting('hours');

include __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert--<?= esc($flash['type']) ?>" data-alert>
        <span><?= esc($flash['message']) ?></span>
        <button type="button" class="alert__close" data-alert-close aria-label="Dismiss">&times;</button>
    </div>
<?php endif; ?>

<!-- ============ HERO ============ -->
<section class="hero<?= $heroImage !== '' ? ' hero--has-image' : '' ?>"<?= $heroImage !== '' ? ' style="background-image:url(' . esc($heroImage) . ');"' : '' ?>>
    <div class="container hero__inner">
        <div class="hero__copy">
            <span class="eyebrow reveal"><?= esc($heroEyebrow) ?></span>
            <h1 class="hero__title reveal reveal-delay-1"><?= render_emphasis($heroTitle) ?></h1>
            <p class="hero__subtitle reveal reveal-delay-2"><?= esc($heroSubtitle) ?></p>
            <div class="hero__actions reveal reveal-delay-3">
                <a href="#" class="btn btn--gold" data-modal-open="booking">
                    <?= esc($heroPrimary) ?>
                    <svg class="btn__arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
                <a href="services.php" class="btn btn--outline"><?= esc($heroSecondary) ?></a>
            </div>
        </div>

        <div class="hero__visual reveal reveal-delay-2">
            <div class="hero__card">
                <span class="hero__card-label"><?= esc($heroCardLabel) ?></span>
                <?php foreach ($heroStats as $stat): ?>
                    <div class="hero__stat">
                        <span class="hero__stat-num" data-count="<?= esc((string)($stat['value'] ?? '0')) ?>" data-suffix="<?= esc((string)($stat['suffix'] ?? '')) ?>">0<?= esc((string)($stat['suffix'] ?? '')) ?></span>
                        <span class="hero__stat-label"><?= esc((string)($stat['label'] ?? '')) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="hero__badge"><?= nl2br(esc($heroBadge)) ?></div>
        </div>
    </div>
    <a href="#partners" class="hero__cue" aria-label="Scroll to our clients">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
    </a>
</section>

<!-- ============ CLIENT CREDIBILITY BANNER (LOGOS) ============ -->
<section class="section section--bg" id="partners">
    <div class="container">
        <div class="partners__head reveal"><?= esc($logosHeading) ?></div>
        <div class="partners-grid">
            <?php if (empty($partners)): ?>
                <p class="section__lede" style="grid-column:1/-1;text-align:center;"><?= esc($logosEmpty) ?></p>
            <?php else: ?>
                <?php foreach ($partners as $i => $p): ?>
                    <div class="partner-logo reveal <?= $i === 1 ? 'reveal-delay-1' : ($i === 2 ? 'reveal-delay-2' : '') ?>"
                         title="<?= esc($p['org_name']) ?>">
                        <img src="<?= esc($p['logo_path']) ?>" alt="<?= esc($p['org_name']) ?>" loading="lazy">
                        <span class="partner-logo__name"><?= esc($p['org_name']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============ SERVICES ============ -->
<section class="section" id="services">
    <div class="container">
        <div class="section__head reveal">
            <span class="eyebrow eyebrow--center"><?= esc($servicesEyebrow) ?></span>
            <h2 class="section__title"><?= render_emphasis($servicesTitle) ?></h2>
        </div>

        <div class="services-grid">
            <?php foreach ($servicesItems as $i => $svc): ?>
                <?php $title = (string)($svc['title'] ?? ''); ?>
                <article class="service-card reveal <?= $i === 1 ? 'reveal-delay-1' : ($i === 2 ? 'reveal-delay-2' : '') ?>">
                    <span class="service-card__num"><?= esc((string)($svc['num'] ?? '')) ?></span>
                    <div class="service-card__icon">
                        <img src="<?= esc((string)($svc['icon'] ?? 'assets/images/icons/briefcase.svg')) ?>" alt="" width="34" height="34">
                    </div>
                    <h3 class="service-card__title"><?= esc($title) ?></h3>
                    <p class="service-card__body"><?= esc((string)($svc['card_body'] ?? '')) ?></p>
                    <div class="service-card__actions">
                        <a href="#" class="btn btn--gold btn--sm" data-modal-open="booking" data-service="<?= esc($title) ?>">
                            <?= esc($servicesCta) ?>
                        </a>
                        <a href="<?= esc(whatsapp_link(whatsapp_service_message($title), (string)($svc['whatsapp'] ?? ''))) ?>"
                           class="btn btn--wa btn--sm" target="_blank" rel="noopener">
                            <?= esc($servicesWa) ?>
                        </a>
                    </div>
                    <a href="services.php#<?= esc((string)($svc['key'] ?? '')) ?>" class="service-card__link" style="margin-top:18px;">
                        <?= esc($servicesMore) ?>
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ ABOUT ============ -->
<section class="section section--bg" id="about">
    <div class="container about-grid">
        <div class="about-visual reveal">
            <div class="about-visual__frame">
                <img src="<?= esc($aboutImage) ?>" alt="<?= esc($aboutImageAlt) ?>">
            </div>
            <blockquote class="about-visual__quote">
                <p><?= esc($aboutQuote) ?></p>
                <cite><?= esc($aboutCite) ?></cite>
            </blockquote>
        </div>

        <div class="about-copy reveal reveal-delay-1">
            <span class="eyebrow"><?= esc($aboutEyebrow) ?></span>
            <h2 class="section__title" style="margin-bottom:0;"><?= render_emphasis($aboutTitle) ?></h2>
            <?= render_rich($aboutBody) ?>

            <div class="about-points">
                <?php foreach ($aboutPoints as $point): ?>
                    <div class="about-point">
                        <img src="assets/images/icons/checkmark.svg" alt="" width="18" height="18">
                        <div><strong><?= esc((string)($point['heading'] ?? '')) ?></strong><span><?= esc((string)($point['text'] ?? '')) ?></span></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <a href="#" class="btn btn--navy" data-modal-open="booking"><?= esc($aboutCta) ?></a>
        </div>
    </div>
</section>

<!-- ============ GALLERY ============ -->
<?php if (!empty($galleryItems)): ?>
<section class="section" id="gallery">
    <div class="container">
        <div class="section__head reveal">
            <span class="eyebrow eyebrow--center"><?= esc($galleryEyebrow) ?></span>
            <h2 class="section__title"><?= render_emphasis($galleryTitle) ?></h2>
        </div>
        <div class="gallery-grid">
            <?php foreach ($galleryItems as $i => $g): ?>
                <figure class="gallery-item reveal <?= $i === 1 ? 'reveal-delay-1' : ($i === 2 ? 'reveal-delay-2' : '') ?>">
                    <img src="<?= esc((string)($g['image'] ?? '')) ?>" alt="<?= esc((string)($g['alt'] ?? '')) ?>" loading="lazy">
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ STATS BAND ============ -->
<section class="section section--primary">
    <div class="container">
        <div class="stats-band reveal">
            <?php foreach ($statItems as $stat): ?>
                <div class="stat">
                    <div class="stat__num" data-count="<?= esc((string)($stat['value'] ?? '0')) ?>" data-suffix="<?= esc((string)($stat['suffix'] ?? '')) ?>">0<?= esc((string)($stat['suffix'] ?? '')) ?></div>
                    <div class="stat__label"><?= esc((string)($stat['label'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ FAQ ============ -->
<?php if (!empty($faqItems)): ?>
<section class="section section--bg" id="faq">
    <div class="container">
        <div class="section__head reveal">
            <span class="eyebrow eyebrow--center"><?= esc($faqEyebrow) ?></span>
            <h2 class="section__title"><?= render_emphasis($faqTitle) ?></h2>
            <?php if ($faqLede !== ''): ?><p class="section__lede"><?= esc($faqLede) ?></p><?php endif; ?>
        </div>
        <div class="faq">
            <?php foreach ($faqItems as $i => $fq): ?>
                <?php $question = (string)($fq['question'] ?? ''); ?>
                <?php if ($question === ''): continue; endif; ?>
                <div class="faq__item reveal <?= $i === 1 ? 'reveal-delay-1' : ($i === 2 ? 'reveal-delay-2' : '') ?>">
                    <button type="button" class="faq__q" aria-expanded="false" aria-controls="faq-panel-<?= esc((string)$i) ?>">
                        <span><?= esc($question) ?></span>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="faq__a" id="faq-panel-<?= esc((string)$i) ?>" role="region" hidden>
                        <p><?= nl2br(esc((string)($fq['answer'] ?? ''))) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ CONTACT ============ -->
<section class="section" id="contact">
    <div class="container">
        <div class="section__head reveal">
            <span class="eyebrow eyebrow--center"><?= esc($contactEyebrow) ?></span>
            <h2 class="section__title"><?= render_emphasis($contactTitle) ?></h2>
            <p class="section__lede"><?= esc($contactLede) ?></p>
        </div>

        <div class="contact-grid">
            <div class="contact-info reveal">
                <div class="contact-card">
                    <div class="contact-card__icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div>
                        <h3><?= esc((string)($contactCards[0]['heading'] ?? 'Visit us')) ?></h3>
                        <p><?= esc($siteAddress) ?></p>
                    </div>
                </div>
                <div class="contact-card">
                    <div class="contact-card__icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </div>
                    <div>
                        <h3><?= esc((string)($contactCards[1]['heading'] ?? 'Call us')) ?></h3>
                        <p><a href="tel:<?= esc(preg_replace('/\s+/', '', $sitePhone)) ?>"><?= esc($sitePhone) ?></a></p>
                    </div>
                </div>
                <div class="contact-card">
                    <div class="contact-card__icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                    </div>
                    <div>
                        <h3><?= esc((string)($contactCards[2]['heading'] ?? 'Email us')) ?></h3>
                        <p><a href="mailto:<?= esc($siteEmail) ?>"><?= esc($siteEmail) ?></a></p>
                    </div>
                </div>
                <div class="contact-card">
                    <div class="contact-card__icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div>
                        <h3><?= esc((string)($contactCards[3]['heading'] ?? 'Office hours')) ?></h3>
                        <p><?= esc($siteHours) ?></p>
                    </div>
                </div>
            </div>

            <div class="form-card reveal reveal-delay-1">
                <h3 class="form-card__title"><?= esc($formHeading) ?></h3>
                <p class="form-card__lede"><?= esc($formLede) ?></p>

                <form class="form" action="api/submit-booking.php" method="post" id="contactForm">
                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                    <input type="hidden" name="redirect" value="index.php#contact">

                    <div class="form__row">
                        <div class="form__field">
                            <label for="cf-name">Full name *</label>
                            <input type="text" id="cf-name" name="full_name" required maxlength="120" autocomplete="name" placeholder="Jane Doe">
                        </div>
                        <div class="form__field">
                            <label for="cf-email">Work email *</label>
                            <input type="email" id="cf-email" name="email" required maxlength="150" autocomplete="email" placeholder="jane@company.com">
                        </div>
                    </div>

                    <div class="form__row">
                        <div class="form__field">
                            <label for="cf-phone">Phone *</label>
                            <input type="tel" id="cf-phone" name="phone" required maxlength="40" autocomplete="tel" placeholder="+256 700 000 000">
                        </div>
                        <div class="form__field">
                            <label for="cf-service">Service *</label>
                            <select id="cf-service" name="service" required>
                                <option value="" disabled selected><?= esc($formPlaceholder) ?></option>
                                <?php foreach ($serviceOptions as $option): ?>
                                    <option value="<?= esc($option) ?>"><?= esc($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form__row">
                        <div class="form__field">
                            <label for="cf-company">Company / Organization <span class="form__optional">(optional)</span></label>
                            <input type="text" id="cf-company" name="company" maxlength="120" placeholder="Company Ltd. or NGO">
                        </div>
                        <div class="form__field">
                            <label for="cf-date">Preferred date <span class="form__optional">(optional)</span></label>
                            <input type="date" id="cf-date" name="preferred_date">
                        </div>
                    </div>

                    <div class="form__field">
                        <label for="cf-message">Project brief *</label>
                        <textarea id="cf-message" name="message" required rows="4" maxlength="3000" placeholder="Briefly describe your project or requirements…"></textarea>
                    </div>

                    <button type="submit" class="btn btn--gold btn--block">
                        <?= esc($formSubmit) ?>
                        <svg class="btn__arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
