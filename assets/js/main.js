/* ============================================================================
   OWERE & ASSOCIATES — main.js
   Modal handlers · mobile nav · sticky header · reveal animations · flash
   ========================================================================== */

(function () {
    'use strict';

    var d = document;

    /* ---------- Sticky header shadow ---------- */
    var header = d.getElementById('siteHeader');
    if (header) {
        var onScroll = function () {
            header.classList.toggle('is-scrolled', window.scrollY > 10);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* ---------- Mobile navigation ---------- */
    var navToggle = d.getElementById('navToggle');
    var siteNav = d.getElementById('siteNav');
    if (navToggle && siteNav) {
        navToggle.addEventListener('click', function () {
            var open = siteNav.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            d.body.style.overflow = open ? 'hidden' : '';
        });

        siteNav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                siteNav.classList.remove('is-open');
                navToggle.setAttribute('aria-expanded', 'false');
                d.body.style.overflow = '';
            });
        });
    }

    /* ---------- Booking modal ---------- */
    var modal = d.getElementById('modal-booking');
    var bookingForm = d.getElementById('bookingForm');
    var serviceSelect = d.getElementById('bk-service');

    function openModal(service) {
        if (!modal) return;
        if (service && serviceSelect) {
            var matched = false;
            Array.prototype.forEach.call(serviceSelect.options, function (opt) {
                if (opt.value === service) {
                    serviceSelect.value = service;
                    matched = true;
                }
            });
            if (!matched) serviceSelect.value = 'General Enquiry';
        }
        modal.hidden = false;
        d.body.style.overflow = 'hidden';
        var first = modal.querySelector('input, select, textarea');
        if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, 120);
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        d.body.style.overflow = '';
    }

    d.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-modal-open]');
        if (opener) {
            e.preventDefault();
            var service = opener.getAttribute('data-service') || '';
            openModal(service);
            return;
        }
        if (e.target.closest('[data-modal-close]')) {
            closeModal();
        }
    });

    d.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
    });

    /* ---------- Smooth anchor scrolling with sticky-header offset ---------- */
    d.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            var id = anchor.getAttribute('href');
            if (id.length < 2) return;
            var target = d.querySelector(id);
            if (!target) return;
            e.preventDefault();
            var headerOffset = header ? header.offsetHeight : 0;
            var top = target.getBoundingClientRect().top + window.pageYOffset - headerOffset - 18;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    });

    /* ---------- Reveal-on-scroll ---------- */
    var revealEls = d.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach(function (el) { io.observe(el); });
    } else {
        revealEls.forEach(function (el) { el.classList.add('is-visible'); });
    }

    /* ---------- Animated stat counters ---------- */
    var counters = d.querySelectorAll('[data-count]');
    function animateCount(el) {
        var target = parseFloat(el.getAttribute('data-count')) || 0;
        var suffix = el.getAttribute('data-suffix') || '';
        var decimals = (String(target).split('.')[1] || '').length;
        var duration = 1400;
        var start = null;

        function step(ts) {
            if (!start) start = ts;
            var progress = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            var value = target * eased;
            el.textContent = value.toFixed(decimals) + suffix;
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    if ('IntersectionObserver' in window && counters.length) {
        var cio = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateCount(entry.target);
                    cio.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        counters.forEach(function (el) { cio.observe(el); });
    }

    /* ---------- Flash alerts auto-dismiss ---------- */
    var alerts = d.querySelectorAll('[data-alert]');
    alerts.forEach(function (alertEl) {
        var closeBtn = alertEl.querySelector('[data-alert-close]');
        var dismiss = function () {
            alertEl.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alertEl.style.opacity = '0';
            alertEl.style.transform = 'translateY(-8px)';
            setTimeout(function () { alertEl.remove(); }, 400);
        };
        if (closeBtn) closeBtn.addEventListener('click', dismiss);
        setTimeout(dismiss, 6000);
    });

    /* ---------- FAQ accordion ---------- */
    var faqItems = d.querySelectorAll('.faq__item');
    if (faqItems.length) {
        function closeFaqItems() {
            faqItems.forEach(function (item) {
                item.classList.remove('is-open');
                var btn = item.querySelector('.faq__q');
                var panel = item.querySelector('.faq__a');
                if (btn) btn.setAttribute('aria-expanded', 'false');
                if (panel) panel.hidden = true;
            });
        }
        faqItems.forEach(function (item) {
            var btn = item.querySelector('.faq__q');
            var panel = item.querySelector('.faq__a');
            if (!btn || !panel) return;
            btn.addEventListener('click', function () {
                var wasOpen = item.classList.contains('is-open');
                closeFaqItems();
                if (!wasOpen) {
                    item.classList.add('is-open');
                    btn.setAttribute('aria-expanded', 'true');
                    panel.hidden = false;
                }
            });
        });
    }

    /* ---------- Footer year ---------- */
    var yearEls = d.querySelectorAll('[data-year]');
    yearEls.forEach(function (el) { el.textContent = String(new Date().getFullYear()); });
})();
