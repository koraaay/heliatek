/* Heliatek Frontend-Verhalten: Navigation, animierte Zahlen, Scroll-Reveals */
(function () {
    'use strict';

    // Mobile Navigation
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('mainnav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Zahlen in der Statistik-Sektion hochzählen, sobald sichtbar
    var counters = document.querySelectorAll('[data-count-to]');
    if (counters.length && 'IntersectionObserver' in window) {
        var animate = function (el) {
            var target = parseFloat(el.getAttribute('data-count-to'));
            if (isNaN(target)) {
                return;
            }
            var decimals = (el.getAttribute('data-count-to').split('.')[1] || '').length;
            var duration = 1600;
            var start = null;
            var step = function (ts) {
                if (!start) {
                    start = ts;
                }
                var progress = Math.min((ts - start) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = (target * eased).toFixed(decimals).replace('.', ',');
                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };
            requestAnimationFrame(step);
        };

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animate(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });

        counters.forEach(function (el) {
            observer.observe(el);
        });
    }

    // Dezente Scroll-Reveals (translateY + fade), respektiert reduced motion
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        var sections = document.querySelectorAll('.hl-main > section, .hl-main .hl-section');
        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        sections.forEach(function (el) {
            el.classList.add('hl-reveal');
            revealObserver.observe(el);
        });
    }
})();
