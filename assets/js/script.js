/* =====================================================================
   Banking Management System  -  assets/js/script.js
   ---------------------------------------------------------------------
   Plain JavaScript, no jQuery. Every behaviour is wired up from a
   data-* attribute in the markup rather than from a hard-coded element
   ID, so the same script serves the public site, the customer panel and
   the admin panel.

   IMPORTANT: nothing in this file is a security control. The browser is
   under the visitor's control, so every check here is repeated on the
   server in PHP. This code exists to make the forms pleasant, not safe.

   Contents
     01  Small helpers
     02  Panel sidebar
     03  Sticky public navbar
     04  Password: show/hide, strength, confirmation
     05  Digits-only inputs
     06  Account balance display and balance checking
     07  Quick amount buttons
     08  Copy to clipboard
     09  Live account verification (fetch)
     10  Charts (Chart.js)
     11  Animated counters
     12  Reveal on scroll
     13  Alerts that dismiss themselves
     14  Back to top
     15  Confirm before acting
     16  Form submit guard
     17  Miscellaneous polish
   ================================================================== */

(function () {
    'use strict';

    /* =================================================================
       01  SMALL HELPERS
       ============================================================== */

    /** querySelectorAll as a real array, so forEach/map/filter work. */
    function $$(selector, scope) {
        return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
    }

    function $(selector, scope) {
        return (scope || document).querySelector(selector);
    }

    /**
     * Read a JSON value out of a data-* attribute.
     * Returns the fallback if the attribute is missing or malformed,
     * so one bad value can never take the whole page down.
     */
    function readJSON(element, attribute, fallback) {
        var raw = element.getAttribute(attribute);
        if (!raw) { return fallback; }
        try {
            return JSON.parse(raw);
        } catch (error) {
            console.warn('[BMS] Could not parse ' + attribute + ' on', element, error);
            return fallback;
        }
    }

    /** Format a number the way the rest of the site formats money. */
    function formatNumber(value, decimals) {
        var places = typeof decimals === 'number' ? decimals : 0;
        return Number(value).toLocaleString('en-IN', {
            minimumFractionDigits: places,
            maximumFractionDigits: places
        });
    }

    /** Wait until the user stops typing before doing expensive work. */
    function debounce(fn, wait) {
        var timer = null;
        return function () {
            var context = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(context, args); }, wait);
        };
    }

    var prefersReducedMotion = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* =================================================================
       02  PANEL SIDEBAR
       ---------------------------------------------------------------
       On desktop the sidebar is always on screen. Below the large
       breakpoint it slides in over the content with a dimming overlay.
       ============================================================== */
    function initSidebar() {
        var sidebar = $('#panelSidebar');
        var overlay = $('#sidebarOverlay');
        var toggle  = $('#sidebarToggle');
        var close   = $('#sidebarClose');

        if (!sidebar) { return; }

        function openSidebar() {
            sidebar.classList.add('show');
            if (overlay) { overlay.classList.add('show'); }
            document.body.style.overflow = 'hidden';
            if (toggle) { toggle.setAttribute('aria-expanded', 'true'); }
        }

        function closeSidebar() {
            sidebar.classList.remove('show');
            if (overlay) { overlay.classList.remove('show'); }
            document.body.style.overflow = '';
            if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
        }

        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.addEventListener('click', function () {
                if (sidebar.classList.contains('show')) { closeSidebar(); } else { openSidebar(); }
            });
        }

        if (close)   { close.addEventListener('click', closeSidebar); }
        if (overlay) { overlay.addEventListener('click', closeSidebar); }

        // Escape closes it, which is what people expect from a drawer.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('show')) { closeSidebar(); }
        });

        // Tapping a menu item on a phone should close the drawer.
        $$('.sidebar-link', sidebar).forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) { closeSidebar(); }
            });
        });

        // Growing the window past the breakpoint must clear the mobile state,
        // otherwise the body stays locked at overflow:hidden.
        window.addEventListener('resize', debounce(function () {
            if (window.innerWidth >= 992) { closeSidebar(); }
        }, 150));
    }

    /* =================================================================
       03  STICKY PUBLIC NAVBAR
       ============================================================== */
    function initNavbar() {
        var navbar = $('#siteNavbar');
        if (!navbar) { return; }

        function onScroll() {
            navbar.classList.toggle('scrolled', window.scrollY > 20);
        }

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        // Collapse the mobile menu after a link is tapped.
        var menu = $('#mainNav');
        if (menu) {
            $$('.nav-link, .btn', menu).forEach(function (link) {
                link.addEventListener('click', function () {
                    if (menu.classList.contains('show') && window.bootstrap) {
                        var instance = window.bootstrap.Collapse.getInstance(menu);
                        if (instance) { instance.hide(); }
                    }
                });
            });
        }
    }

    /* =================================================================
       04  PASSWORD FIELDS
       ============================================================== */

    /* ---- Show / hide ---- */
    function initPasswordToggles() {
        $$('[data-toggle-password]').forEach(function (button) {
            var field = $(button.getAttribute('data-toggle-password'));
            if (!field) { return; }

            button.addEventListener('click', function () {
                var showing = field.type === 'text';
                field.type = showing ? 'password' : 'text';

                var icon = $('i', button);
                if (icon) {
                    icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
                }
                button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');

                // Put the caret back where it was rather than at position 0.
                field.focus();
                var end = field.value.length;
                try { field.setSelectionRange(end, end); } catch (ignore) { /* number-ish inputs */ }
            });
        });
    }

    /* ---- Strength meter ----
       Deliberately generous and advisory. The real rules live in
       password_problems() in includes/functions.php. */
    function scorePassword(value) {
        var score = 0;

        if (value.length >= 8)  { score += 1; }
        if (value.length >= 12) { score += 1; }
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) { score += 1; }
        if (/[0-9]/.test(value)) { score += 1; }
        if (/[^A-Za-z0-9]/.test(value)) { score += 1; }

        // Obvious choices should not score well however long they are.
        if (/^(password|admin|12345678|qwerty|letmein|welcome)/i.test(value)) { score = 1; }

        return Math.min(score, 4);
    }

    function initPasswordStrength() {
        $$('[data-strength-target]').forEach(function (field) {
            var meter = $(field.getAttribute('data-strength-target'));
            if (!meter) { return; }

            var text    = $('.pw-text', meter);
            var classes = ['is-weak', 'is-fair', 'is-good', 'is-strong'];
            var labels  = [
                'Too weak - add length and a mix of characters',
                'Weak - use at least 8 characters with letters and numbers',
                'Fair - add a capital letter or a symbol',
                'Good - this is a reasonable password',
                'Strong - nicely done'
            ];

            field.addEventListener('input', function () {
                var value = field.value;

                classes.forEach(function (name) { meter.classList.remove(name); });

                if (!value) {
                    if (text) { text.textContent = 'Use 8+ characters with letters and numbers'; }
                    return;
                }

                var score = scorePassword(value);
                if (score > 0) { meter.classList.add(classes[score - 1]); }
                if (text) { text.textContent = labels[score]; }
            });
        });
    }

    /* ---- Confirmation match ---- */
    function initPasswordMatch() {
        $$('[data-match]').forEach(function (confirmField) {
            var original = $(confirmField.getAttribute('data-match'));
            if (!original) { return; }

            // The hint sits in the .match-text element next to the field.
            var group  = confirmField.closest('.mb-3, .col-md-6, .col-12, .mb-4') || confirmField.parentElement;
            var target = group ? $('.match-text', group) : null;

            function check() {
                if (!confirmField.value) {
                    if (target) {
                        target.textContent = '';
                        target.classList.remove('is-ok', 'is-bad');
                    }
                    confirmField.classList.remove('is-valid', 'is-invalid');
                    return;
                }

                var matches = confirmField.value === original.value;

                confirmField.classList.toggle('is-valid', matches);
                confirmField.classList.toggle('is-invalid', !matches);

                if (target) {
                    target.textContent = matches ? 'Passwords match' : 'Passwords do not match yet';
                    target.classList.toggle('is-ok', matches);
                    target.classList.toggle('is-bad', !matches);
                }
            }

            confirmField.addEventListener('input', check);
            original.addEventListener('input', check);
        });
    }

    /* =================================================================
       05  DIGITS-ONLY INPUTS
       ---------------------------------------------------------------
       Account numbers, mobile numbers and PIN codes. Strips anything
       that is not a digit as it is typed, including on paste.
       ============================================================== */
    function initDigitsOnly() {
        $$('[data-digits-only]').forEach(function (field) {
            field.addEventListener('input', function () {
                var cleaned = field.value.replace(/\D+/g, '');
                if (cleaned !== field.value) {
                    var caret = field.selectionStart;
                    var removed = field.value.length - cleaned.length;
                    field.value = cleaned;
                    try { field.setSelectionRange(caret - removed, caret - removed); } catch (ignore) {}
                }
            });

            // Block the keys that would need cleaning up afterwards.
            field.addEventListener('keypress', function (event) {
                if (event.key.length === 1 && /\D/.test(event.key)) { event.preventDefault(); }
            });
        });
    }

    /* =================================================================
       06  BALANCE DISPLAY AND BALANCE CHECKING
       ============================================================== */

    /* ---- Switching account updates the balance banner ---- */
    function initBalanceDisplay() {
        $$('[data-balance-display]').forEach(function (select) {
            var display = $(select.getAttribute('data-balance-display'));
            if (!display) { return; }

            var banner = display.closest('.balance-banner');

            select.addEventListener('change', function () {
                var option = select.options[select.selectedIndex];
                if (!option) { return; }

                var formatted = option.getAttribute('data-balance-text');
                if (formatted) { display.textContent = formatted; }

                // A small pulse so the change is noticed.
                if (banner && !prefersReducedMotion) {
                    banner.style.transition = 'transform .2s ease';
                    banner.style.transform = 'scale(1.015)';
                    setTimeout(function () { banner.style.transform = ''; }, 200);
                }

                // Re-run any amount check that depends on this select.
                $$('[data-check-balance]').forEach(function (amountField) {
                    if ($(amountField.getAttribute('data-check-balance')) === select) {
                        amountField.dispatchEvent(new Event('input'));
                    }
                });
            });
        });
    }

    /* ---- Warn before the server has to refuse ----
       The authoritative check is the locked SELECT ... FOR UPDATE in
       customer/withdraw.php and customer/transfer.php. This only saves
       the customer a round trip. */
    function initBalanceCheck() {
        $$('[data-check-balance]').forEach(function (amountField) {
            var select = $(amountField.getAttribute('data-check-balance'));
            if (!select) { return; }

            // Bootstrap needs the feedback element as a sibling of the input
            // group, and d-block makes it show without form validation state.
            var anchor   = amountField.closest('.input-group') || amountField;
            var feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block';
            feedback.style.display = 'none';
            anchor.parentNode.insertBefore(feedback, anchor.nextSibling);

            amountField.addEventListener('input', function () {
                var option = select.options[select.selectedIndex];
                if (!option) { return; }

                var balance = parseFloat(option.getAttribute('data-balance') || '0');
                var amount  = parseFloat(amountField.value || '0');
                var pretty  = option.getAttribute('data-balance-text') || formatNumber(balance, 2);

                if (amountField.value !== '' && amount > balance) {
                    amountField.classList.add('is-invalid');
                    feedback.textContent = 'That is more than the available balance of ' + pretty + '.';
                    feedback.style.display = 'block';
                } else {
                    amountField.classList.remove('is-invalid');
                    feedback.style.display = 'none';
                }
            });
        });
    }

    /* =================================================================
       07  QUICK AMOUNT BUTTONS
       ---------------------------------------------------------------
       data-amount="500"   fills a fixed figure
       data-amount="max"   fills the balance of the account in data-source
       data-amount="clear" empties the field
       ============================================================== */
    function initQuickAmounts() {
        $$('[data-amount]').forEach(function (button) {
            var target = $(button.getAttribute('data-target') || '#amount');
            if (!target) { return; }

            button.addEventListener('click', function () {
                var value = button.getAttribute('data-amount');

                if (value === 'clear') {
                    target.value = '';
                } else if (value === 'max') {
                    var select = $(button.getAttribute('data-source') || '');
                    if (select && select.options[select.selectedIndex]) {
                        target.value = select.options[select.selectedIndex].getAttribute('data-balance') || '';
                    }
                } else {
                    target.value = value;
                }

                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.focus();
            });
        });
    }

    /* =================================================================
       08  COPY TO CLIPBOARD
       ============================================================== */
    function initCopyButtons() {
        $$('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                var text = button.getAttribute('data-copy') || '';
                var icon = $('i', button);
                var originalIcon = icon ? icon.className : '';

                function succeeded() {
                    button.classList.add('copied');
                    if (icon) { icon.className = 'bi bi-check-lg'; }
                    button.setAttribute('aria-label', 'Copied');

                    setTimeout(function () {
                        button.classList.remove('copied');
                        if (icon) { icon.className = originalIcon; }
                        button.setAttribute('aria-label', 'Copy account number');
                    }, 1600);
                }

                // The modern API needs a secure context. On plain http://localhost
                // it usually works, but fall back to the old trick if it does not.
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(succeeded).catch(legacyCopy);
                } else {
                    legacyCopy();
                }

                function legacyCopy() {
                    var scratch = document.createElement('textarea');
                    scratch.value = text;
                    scratch.setAttribute('readonly', '');
                    scratch.style.position = 'fixed';
                    scratch.style.opacity = '0';
                    document.body.appendChild(scratch);
                    scratch.select();
                    try { document.execCommand('copy'); succeeded(); } catch (ignore) {}
                    document.body.removeChild(scratch);
                }
            });
        });
    }

    /* =================================================================
       09  LIVE ACCOUNT VERIFICATION
       ---------------------------------------------------------------
       Asks the server who owns an account number once twelve digits
       have been entered, so nobody sends money into a typo.

       The endpoints are deliberately stingy: the customer one returns a
       masked name only. See customer/verify_account.php.
       ============================================================== */
    function initAccountVerify() {
        $$('[data-verify-account]').forEach(function (field) {
            var endpoint = field.getAttribute('data-verify-account');

            // The result box sits inside the same form group as the input.
            var group  = field.closest('.mb-3, .mb-4, .col-12, .col-md-6') || field.parentElement;
            var output = group
                ? $('.account-check, #accountCheck, #receiverStatus', group)
                : null;

            if (!output) { output = $('#accountCheck') || $('#receiverStatus'); }
            if (!output) { return; }

            var defaultText = output.textContent;
            var isPlainText = !output.classList.contains('account-check');
            var currentRequest = 0;

            function paint(state, message) {
                if (isPlainText) {
                    // A .form-text under the transfer field.
                    output.textContent = message;
                    output.classList.remove('text-success', 'text-danger', 'text-muted');
                    output.classList.add(
                        state === 'ok' ? 'text-success' : state === 'error' ? 'text-danger' : 'text-muted'
                    );
                } else {
                    output.textContent = message;
                    output.classList.remove('is-loading', 'is-ok', 'is-error', 'is-visible');
                    if (state) { output.classList.add('is-' + state, 'is-visible'); }
                }
            }

            function reset() {
                if (isPlainText) {
                    output.textContent = defaultText;
                    output.classList.remove('text-success', 'text-danger');
                } else {
                    output.textContent = '';
                    output.classList.remove('is-loading', 'is-ok', 'is-error', 'is-visible');
                }
            }

            var lookup = debounce(function () {
                var number = field.value.replace(/\D+/g, '');

                if (number.length !== 12) { reset(); return; }

                paint('loading', 'Checking account ...');

                var ticket = ++currentRequest;

                fetch(endpoint + '?account_number=' + encodeURIComponent(number), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        if (!response.ok) { throw new Error('HTTP ' + response.status); }
                        return response.json();
                    })
                    .then(function (data) {
                        // A slower earlier request must not overwrite a newer answer.
                        if (ticket !== currentRequest) { return; }
                        paint(data.ok ? 'ok' : 'error', data.message || 'Account could not be checked.');
                    })
                    .catch(function () {
                        if (ticket !== currentRequest) { return; }
                        paint('error', 'Could not reach the server to check that account.');
                    });
            }, 350);

            field.addEventListener('input', lookup);
            field.addEventListener('blur', lookup);

            // Verify straight away if the browser restored a value.
            if (field.value.replace(/\D+/g, '').length === 12) { lookup(); }
        });
    }

    /* =================================================================
       10  CHARTS
       ---------------------------------------------------------------
       Chart.js is loaded from a CDN in the panel footers. If it is not
       present - offline, blocked - the canvases simply stay blank
       rather than throwing.
       ============================================================== */
    function initCharts() {
        var canvases = $$('[data-chart]');
        if (!canvases.length) { return; }

        if (typeof window.Chart === 'undefined') {
            console.warn('[BMS] Chart.js did not load, so charts were skipped.');
            return;
        }

        window.Chart.defaults.font.family =
            "'Plus Jakarta Sans', 'Inter', 'Segoe UI', system-ui, sans-serif";
        window.Chart.defaults.color = '#64748b';
        window.Chart.defaults.plugins.legend.display = false;

        var money = {
            grid: { color: 'rgba(148,163,184,.16)', drawBorder: false },
            border: { display: false },
            ticks: {
                padding: 8,
                callback: function (value) {
                    if (value >= 10000000) { return (value / 10000000).toFixed(1) + ' Cr'; }
                    if (value >= 100000)   { return (value / 100000).toFixed(1) + ' L'; }
                    if (value >= 1000)     { return (value / 1000).toFixed(0) + 'K'; }
                    return value;
                }
            }
        };

        var tooltip = {
            backgroundColor: '#0b1c3d',
            titleFont:  { weight: '700', size: 13 },
            bodyFont:   { size: 12 },
            padding: 12,
            cornerRadius: 10,
            displayColors: true,
            boxPadding: 5
        };

        canvases.forEach(function (canvas) {
            var kind   = canvas.getAttribute('data-chart');
            var labels = readJSON(canvas, 'data-labels', []);

            /* ---------- Grouped bars: deposits against withdrawals ---------- */
            if (kind === 'bars') {
                var credits = readJSON(canvas, 'data-credits', []);
                var debits  = readJSON(canvas, 'data-debits', []);

                new window.Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Deposits',
                                data: credits,
                                backgroundColor: 'rgba(34,197,94,.85)',
                                hoverBackgroundColor: 'rgba(34,197,94,1)',
                                borderRadius: 6,
                                borderSkipped: false,
                                maxBarThickness: 34
                            },
                            {
                                label: 'Withdrawals',
                                data: debits,
                                backgroundColor: 'rgba(239,68,68,.85)',
                                hoverBackgroundColor: 'rgba(239,68,68,1)',
                                borderRadius: 6,
                                borderSkipped: false,
                                maxBarThickness: 34
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: prefersReducedMotion ? false : { duration: 700 },
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                align: 'end',
                                labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 16 }
                            },
                            tooltip: Object.assign({}, tooltip, {
                                callbacks: {
                                    label: function (context) {
                                        return context.dataset.label + ': ' + formatNumber(context.parsed.y, 2);
                                    }
                                }
                            })
                        },
                        scales: {
                            x: { grid: { display: false }, border: { display: false }, ticks: { padding: 6 } },
                            y: Object.assign({ beginAtZero: true }, money)
                        }
                    }
                });
                return;
            }

            /* ---------- Doughnut: account mix, transaction mix ---------- */
            if (kind === 'doughnut') {
                var values = readJSON(canvas, 'data-values', []);

                new window.Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: [
                                'rgba(29,78,216,.9)',
                                'rgba(14,165,233,.9)',
                                'rgba(34,197,94,.9)',
                                'rgba(245,158,11,.9)',
                                'rgba(239,68,68,.9)',
                                'rgba(139,92,246,.9)'
                            ],
                            borderColor: '#fff',
                            borderWidth: 3,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '64%',
                        animation: prefersReducedMotion ? false : { duration: 700 },
                        plugins: {
                            tooltip: Object.assign({}, tooltip, {
                                callbacks: {
                                    label: function (context) {
                                        var data  = context.dataset.data;
                                        var total = data.reduce(function (sum, n) { return sum + Number(n); }, 0);
                                        var share = total ? Math.round((context.parsed / total) * 100) : 0;
                                        return ' ' + context.label + ': ' + formatNumber(context.parsed)
                                             + ' (' + share + '%)';
                                    }
                                }
                            })
                        }
                    }
                });
            }
        });
    }

    /* =================================================================
       11  ANIMATED COUNTERS
       ---------------------------------------------------------------
       Used for the headline figures. Accepts either data-counter (the
       panels) or data-count (the public pages), with an optional
       data-suffix such as "+".
       ============================================================== */
    function initCounters() {
        var counters = $$('[data-counter], [data-count]');
        if (!counters.length) { return; }

        function run(element) {
            var raw = element.getAttribute('data-counter') || element.getAttribute('data-count') || '0';
            var target = parseFloat(String(raw).replace(/[^0-9.\-]/g, ''));

            if (isNaN(target)) { return; }

            var suffix   = element.getAttribute('data-suffix') || '';
            var decimals = String(raw).indexOf('.') !== -1 ? 2 : 0;

            if (prefersReducedMotion || target === 0) {
                element.textContent = formatNumber(target, decimals) + suffix;
                return;
            }

            var duration = 1100;
            var started  = null;

            function step(timestamp) {
                if (started === null) { started = timestamp; }

                var progress = Math.min((timestamp - started) / duration, 1);
                // Ease out, so it decelerates into the final figure.
                var eased = 1 - Math.pow(1 - progress, 3);

                element.textContent = formatNumber(target * eased, decimals) + suffix;

                if (progress < 1) { window.requestAnimationFrame(step); }
            }

            window.requestAnimationFrame(step);
        }

        if (!('IntersectionObserver' in window)) {
            counters.forEach(run);
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    run(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });

        counters.forEach(function (element) { observer.observe(element); });
    }

    /* =================================================================
       12  REVEAL ON SCROLL
       ============================================================== */
    function initReveal() {
        var items = $$('.reveal');
        if (!items.length) { return; }

        if (prefersReducedMotion || !('IntersectionObserver' in window)) {
            items.forEach(function (item) { item.classList.add('is-visible'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry, index) {
                if (!entry.isIntersecting) { return; }

                // A small stagger reads better than everything arriving at once.
                setTimeout(function () {
                    entry.target.classList.add('is-visible');
                }, index * 70);

                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });

        items.forEach(function (item) { observer.observe(item); });
    }

    /* =================================================================
       13  ALERTS THAT DISMISS THEMSELVES
       ============================================================== */
    function initAutoDismiss() {
        $$('.auto-dismiss').forEach(function (alert) {
            // Errors stay put; the reader needs time with them.
            if (alert.classList.contains('alert-danger') || alert.classList.contains('alert-warning')) {
                return;
            }

            setTimeout(function () {
                if (window.bootstrap && window.bootstrap.Alert) {
                    var instance = window.bootstrap.Alert.getOrCreateInstance(alert);
                    instance.close();
                } else {
                    alert.style.transition = 'opacity .4s ease';
                    alert.style.opacity = '0';
                    setTimeout(function () { alert.remove(); }, 400);
                }
            }, 6000);
        });
    }

    /* =================================================================
       14  BACK TO TOP
       ============================================================== */
    function initBackToTop() {
        var button = $('#backToTop');
        if (!button) { return; }

        function onScroll() {
            button.classList.toggle('show', window.scrollY > 400);
        }

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        button.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
        });
    }

    /* =================================================================
       15  CONFIRM BEFORE ACTING
       ---------------------------------------------------------------
       Anything carrying data-confirm asks first. Note this is a courtesy
       only: the server still requires a valid CSRF token and a POST.
       ============================================================== */
    function initConfirm() {
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-confirm]');
            if (!trigger) { return; }

            var message = trigger.getAttribute('data-confirm');
            if (message && !window.confirm(message)) {
                event.preventDefault();
                event.stopPropagation();
            }
        }, true);
    }

    /* =================================================================
       16  FORM SUBMIT GUARD
       ---------------------------------------------------------------
       Stops an impatient double-click from posting a transfer twice.
       The server is still the real defence - each transfer runs inside
       a database transaction - but this removes the common case.
       ============================================================== */
    function initSubmitGuard() {
        $$('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('button[type="submit"], input[type="submit"]');
                if (!button || button.dataset.busy === '1') { return; }

                // Anything the browser is unhappy about should not lock the button.
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) { return; }

                button.dataset.busy = '1';
                var original = button.innerHTML;

                // Disable on the next tick. Doing it immediately would drop the
                // button's own name/value from the submitted form data.
                setTimeout(function () {
                    button.disabled = true;
                    button.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>'
                        + 'Please wait ...';
                }, 0);

                // If the page is still here after a while, the submit failed
                // somewhere. Give the button back rather than trapping the user.
                setTimeout(function () {
                    button.disabled = false;
                    button.innerHTML = original;
                    delete button.dataset.busy;
                }, 12000);
            });
        });
    }

    /* =================================================================
       17  MISCELLANEOUS POLISH
       ============================================================== */
    function initMisc() {
        // Bootstrap tooltips, where the markup asks for them.
        if (window.bootstrap && window.bootstrap.Tooltip) {
            $$('[data-bs-toggle="tooltip"]').forEach(function (element) {
                new window.bootstrap.Tooltip(element);
            });
        }

        // Smooth in-page anchors that Bootstrap does not already own.
        $$('a[href^="#"]:not([data-bs-toggle]):not([href="#"])').forEach(function (link) {
            link.addEventListener('click', function (event) {
                var target = document.getElementById(link.getAttribute('href').slice(1));
                if (!target) { return; }

                event.preventDefault();
                target.scrollIntoView({
                    behavior: prefersReducedMotion ? 'auto' : 'smooth',
                    block: 'start'
                });
            });
        });

        // Trim stray whitespace out of email and username fields on the way
        // in. A trailing space in a pasted email is a miserable bug to hit.
        $$('input[type="email"], input[name="username"], input[name="account_number"]')
            .forEach(function (field) {
                field.addEventListener('blur', function () {
                    field.value = field.value.trim();
                });
            });

        // A page reached with ?highlight=... scrolls that row into view.
        var params = new URLSearchParams(window.location.search);
        var highlight = params.get('highlight');
        if (highlight) {
            var row = document.getElementById('row-' + highlight);
            if (row) {
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                row.style.transition = 'background-color 1.4s ease';
                row.style.backgroundColor = 'rgba(29,78,216,.12)';
                setTimeout(function () { row.style.backgroundColor = ''; }, 2200);
            }
        }
    }

    /* =================================================================
       BOOT
       ---------------------------------------------------------------
       Each initialiser is wrapped so that one failure cannot stop the
       rest from running.
       ============================================================== */
    function boot() {
        [
            initSidebar,
            initNavbar,
            initPasswordToggles,
            initPasswordStrength,
            initPasswordMatch,
            initDigitsOnly,
            initBalanceDisplay,
            initBalanceCheck,
            initQuickAmounts,
            initCopyButtons,
            initAccountVerify,
            initCharts,
            initCounters,
            initReveal,
            initAutoDismiss,
            initBackToTop,
            initConfirm,
            initSubmitGuard,
            initMisc
        ].forEach(function (fn) {
            try {
                fn();
            } catch (error) {
                console.error('[BMS] ' + fn.name + ' failed:', error);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
