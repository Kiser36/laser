<?php
/**
 * Global footer, floating WhatsApp widget, booking modal & scripts.
 * All labels/columns come from content/site-content.json; contact details
 * come from the settings table (admin → Settings).
 */
$siteEmail   = get_setting('notification_email');
$sitePhone   = get_setting('phone_display');
$siteAddress = get_setting('address');
$siteHours   = get_setting('hours');
$waLink      = whatsapp_link(get_setting('whatsapp_welcome_msg'));

$brandName = content_text('brand.name', 'Owere & Associates');
$brandMark = content_text('brand.mark', 'OA');
$brandTag  = content_text('brand.tagline', 'Tax · NGO · Corporate');
$brandLogo = content_text('brand.logo');

$footerBlurb   = content_text('footer.blurb');
$footerSvcHead = content_text('footer.col_services', 'Services');
$footerFirmHead = content_text('footer.col_firm', 'Firm');
$footerConHead = content_text('footer.col_contact', 'Contact');
$serviceLinks  = content_arr('footer.services_links');
$firmLinks     = content_arr('footer.firm_links');
$socials       = content_arr('footer.socials');
$bottomLeft    = content_text('footer.bottom_left');
$bottomLinks   = content_arr('footer.bottom_links');

$serviceOptions = booking_service_options();

$modalEyebrow = content_text('booking.eyebrow', 'Book a Consultation');
$modalTitle   = content_text('booking.title', 'Tell us about your *needs*.');
$modalLede    = content_text('booking.lede');
$modalSubmit  = content_text('booking.submit', 'Submit Enquiry');
$modalNote    = content_text('booking.note', 'Prefer to chat? {link}.');
// Escape the editable text first, then swap in the trusted WhatsApp anchor.
$modalNote    = str_replace('{link}', '<a href="' . esc($waLink) . '" target="_blank" rel="noopener">Message us on WhatsApp</a>', esc($modalNote));

/** Social network → inline icon markup. */
$socialIcons = [
    'linkedin' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM7.12 20.45H3.55V9h3.57v11.45z"/></svg>',
    'x'        => '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.4l-5.8-7.59-6.64 7.59H.47l8.6-9.83L0 1.15h7.59l5.24 6.93 6.07-6.93zm-1.29 19.5h2.04L6.49 3.24H4.3l13.31 17.41z"/></svg>',
    'whatsapp' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.4-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.5 0 1.47 1.07 2.89 1.22 3.09.15.2 2.11 3.22 5.1 4.51.71.31 1.27.49 1.7.63.72.23 1.37.2 1.88.12.58-.09 1.76-.72 2.01-1.42.25-.7.25-1.29.17-1.42-.07-.13-.27-.2-.57-.35zM12.05 21.79h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.9-9.88a9.82 9.82 0 0 1 6.99 2.9 9.82 9.82 0 0 1 2.9 7c0 5.44-4.45 9.87-9.9 9.87zm8.42-18.3A11.82 11.82 0 0 0 12.04 0C5.46 0 .1 5.35.1 11.93c0 2.1.55 4.16 1.6 5.97L0 24l6.23-1.63a11.93 11.93 0 0 0 5.81 1.48h.01c6.58 0 11.94-5.35 11.94-11.93 0-3.19-1.24-6.18-3.5-8.43z"/></svg>',
];
?>
</main>

<!-- ============ FOOTER ============ -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col footer-col--brand">
                <a href="index.php" class="brand brand--footer">
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
                <p class="footer-col__lede"><?= esc($footerBlurb) ?></p>
                <div class="footer-col__social">
                    <?php foreach ($socials as $social): ?>
                        <?php $network = (string)($social['network'] ?? ''); ?>
                        <?php if (!isset($socialIcons[$network])): continue; endif; ?>
                        <?php
                            $label = (string)($social['label'] ?? $network);
                            $href  = $network === 'whatsapp'
                                ? $waLink
                                : sanitize_href((string)($social['href'] ?? '#'));
                        ?>
                        <a href="<?= esc($href) ?>" target="_blank" rel="noopener" aria-label="<?= esc($label) ?>"><?= $socialIcons[$network] ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="footer-col">
                <h4 class="footer-col__title"><?= esc($footerSvcHead) ?></h4>
                <ul class="footer-col__list">
                    <?php foreach ($serviceLinks as $link): ?>
                        <?php $label = (string)($link['label'] ?? ''); ?>
                        <?php if ($label === ''): continue; endif; ?>
                        <li><a href="<?= esc(sanitize_href((string)($link['href'] ?? '#'))) ?>"><?= esc($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="footer-col">
                <h4 class="footer-col__title"><?= esc($footerFirmHead) ?></h4>
                <ul class="footer-col__list">
                    <?php foreach ($firmLinks as $link): ?>
                        <?php $label = (string)($link['label'] ?? ''); ?>
                        <?php if ($label === ''): continue; endif; ?>
                        <li>
                            <?php if (!empty($link['modal'])): ?>
                                <a href="#" data-modal-open="booking"><?= esc($label) ?></a>
                            <?php else: ?>
                                <a href="<?= esc(sanitize_href((string)($link['href'] ?? '#'))) ?>"><?= esc($label) ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="footer-col">
                <h4 class="footer-col__title"><?= esc($footerConHead) ?></h4>
                <ul class="footer-col__list footer-col__list--contact">
                    <li>
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?= esc($siteAddress) ?>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <a href="tel:<?= esc(preg_replace('/\s+/', '', $sitePhone)) ?>"><?= esc($sitePhone) ?></a>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                        <a href="mailto:<?= esc($siteEmail) ?>"><?= esc($siteEmail) ?></a>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?= esc($siteHours) ?>
                    </li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p><?= esc(str_replace('{year}', (string)date('Y'), $bottomLeft)) ?></p>
            <p class="footer-bottom__links">
                <?php foreach ($bottomLinks as $i => $link): ?>
                    <?php $label = (string)($link['label'] ?? ''); ?>
                    <?php if ($label === ''): continue; endif; ?>
                    <?php if ($i > 0): ?><span>&middot;</span><?php endif; ?>
                    <a href="<?= esc(sanitize_href((string)($link['href'] ?? '#'))) ?>"><?= esc($label) ?></a>
                <?php endforeach; ?>
            </p>
        </div>
    </div>
</footer>

<!-- ============ FLOATING WHATSAPP ============ -->
<a href="<?= esc($waLink) ?>" class="wa-float" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">
    <span class="wa-float__pulse"></span>
    <svg viewBox="0 0 24 24" width="30" height="30" fill="#fff"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.4-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.5 0 1.47 1.07 2.89 1.22 3.09.15.2 2.11 3.22 5.1 4.51.71.31 1.27.49 1.7.63.72.23 1.37.2 1.88.12.58-.09 1.76-.72 2.01-1.42.25-.7.25-1.29.17-1.42-.07-.13-.27-.2-.57-.35zM12.05 21.79h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.9-9.88a9.82 9.82 0 0 1 6.99 2.9 9.82 9.82 0 0 1 2.9 7c0 5.44-4.45 9.87-9.9 9.87zm8.42-18.3A11.82 11.82 0 0 0 12.04 0C5.46 0 .1 5.35.1 11.93c0 2.1.55 4.16 1.6 5.97L0 24l6.23-1.63a11.93 11.93 0 0 0 5.81 1.48h.01c6.58 0 11.94-5.35 11.94-11.93 0-3.19-1.24-6.18-3.5-8.43z"/></svg>
</a>

<!-- ============ BOOKING MODAL ============ -->
<div class="modal" id="modal-booking" role="dialog" aria-modal="true" aria-labelledby="bookingTitle" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__panel">
        <button class="modal__close" data-modal-close aria-label="Close">&times;</button>
        <span class="eyebrow eyebrow--dark"><?= esc($modalEyebrow) ?></span>
        <h2 class="modal__title" id="bookingTitle"><?= render_emphasis($modalTitle) ?></h2>
        <p class="modal__lede"><?= esc($modalLede) ?></p>

        <form class="form" action="api/submit-booking.php" method="post" id="bookingForm">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
            <input type="hidden" name="redirect" value="<?= esc($_SERVER['PHP_SELF'] ?? 'index.php') ?>#contact">

            <div class="form__row">
                <div class="form__field">
                    <label for="bk-name">Full name *</label>
                    <input type="text" id="bk-name" name="full_name" required maxlength="120" autocomplete="name" placeholder="Jane Doe">
                </div>
                <div class="form__field">
                    <label for="bk-email">Work email *</label>
                    <input type="email" id="bk-email" name="email" required maxlength="150" autocomplete="email" placeholder="jane@company.com">
                </div>
            </div>

            <div class="form__row">
                <div class="form__field">
                    <label for="bk-phone">Phone *</label>
                    <input type="tel" id="bk-phone" name="phone" required maxlength="40" autocomplete="tel" placeholder="+256 700 000 000">
                </div>
                <div class="form__field">
                    <label for="bk-service">Service *</label>
                    <select id="bk-service" name="service" required>
                        <option value="" disabled selected><?= esc(content_text('contact.service_placeholder', 'Select a service…')) ?></option>
                        <?php foreach ($serviceOptions as $option): ?>
                            <option value="<?= esc($option) ?>"><?= esc($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form__row">
                <div class="form__field">
                    <label for="bk-company">Company / Organization <span class="form__optional">(optional)</span></label>
                    <input type="text" id="bk-company" name="company" maxlength="120" placeholder="Company Ltd. or NGO">
                </div>
                <div class="form__field">
                    <label for="bk-date">Preferred date <span class="form__optional">(optional)</span></label>
                    <input type="date" id="bk-date" name="preferred_date">
                </div>
            </div>

            <div class="form__field">
                <label for="bk-message">Project brief *</label>
                <textarea id="bk-message" name="message" required rows="4" maxlength="3000" placeholder="Briefly describe your project or requirements…"></textarea>
            </div>

            <button type="submit" class="btn btn--gold btn--block"><?= esc($modalSubmit) ?></button>
            <p class="form__note"><?= $modalNote ?></p>
        </form>
    </div>
</div>

<script src="assets/js/main.js" defer></script>
</body>
</html>
