/**
 * dashboard-widgets.js
 * Animated stat counters and dashboard widget utilities.
 */

(function () {
    'use strict';

    /**
     * Animate a number from 0 to the target value.
     * @param {Element} el    - Target element containing the number
     * @param {number}  end   - Target value
     * @param {number}  duration - Animation duration in ms
     */
    function animateCounter(el, end, duration) {
        var startTime = null;
        var startVal  = 0;
        var formatted = !isNaN(end) && end >= 1000;

        function formatNum(num) {
            return formatted ? num.toLocaleString() : Math.ceil(num);
        }

        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var eased    = 1 - Math.pow(1 - progress, 3); // ease-out cubic
            var current  = startVal + (end - startVal) * eased;

            el.textContent = formatNum(current);

            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = formatNum(end);
            }
        }

        requestAnimationFrame(step);
    }

    /**
     * Run counter animations on all .dash-card__value elements.
     */
    function initCounters() {
        var counterEls = document.querySelectorAll('.dash-card__value');

        if (!('IntersectionObserver' in window)) {
            // Fallback: just set values directly
            counterEls.forEach(function (el) {
                el.dataset.counted = 'true';
            });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && !entry.target.dataset.counted) {
                    var raw = entry.target.textContent.replace(/,/g, '').trim();
                    var end = parseFloat(raw);
                    if (!isNaN(end)) {
                        entry.target.dataset.counted = 'true';
                        animateCounter(entry.target, end, 800);
                    }
                }
            });
        }, { threshold: 0.3 });

        counterEls.forEach(function (el) {
            observer.observe(el);
        });
    }

    /**
     * Add fade-in-up animation to dashboard cards on load.
     */
    function initCardAnimations() {
        var cards = document.querySelectorAll('.dash-card');

        cards.forEach(function (card, index) {
            card.style.opacity   = '0';
            card.style.transform = 'translateY(16px)';
            card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            card.style.transitionDelay = (index * 60) + 'ms';

            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    card.style.opacity   = '1';
                    card.style.transform = 'translateY(0)';
                });
            });
        });
    }

    /**
     * Highlight the current active table row on click.
     */
    function initTableRowHighlight() {
        var tables = document.querySelectorAll('.data-table tbody');

        tables.forEach(function (tbody) {
            tbody.addEventListener('click', function (event) {
                var row = event.target.closest('tr');
                if (!row) return;

                tbody.querySelectorAll('tr').forEach(function (r) {
                    r.classList.remove('data-table__row--selected');
                });

                row.classList.add('data-table__row--selected');
            });
        });
    }

    // ── INIT ─────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        initCounters();
        initCardAnimations();
        initTableRowHighlight();
    });

})();
