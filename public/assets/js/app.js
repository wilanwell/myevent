/* MyEvent - shared UI behaviour (no dependencies). */
(function () {
    'use strict';

    // Mobile sidebar drawer
    var body = document.body;
    var toggle = document.querySelector('[data-nav-toggle]');
    function setNav(open) {
        body.classList.toggle('nav-open', open);
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }
    if (toggle) {
        toggle.addEventListener('click', function () {
            setNav(!body.classList.contains('nav-open'));
        });
    }
    document.querySelectorAll('[data-nav-close]').forEach(function (el) {
        el.addEventListener('click', function () { setNav(false); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { setNav(false); }
    });

    // Confirm before destructive submits: <form data-confirm="Delete this event?">
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var msg = form.getAttribute && form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    // Live image preview: <input type="file" data-preview="#imgId">
    document.querySelectorAll('input[type=file][data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            var selector = input.getAttribute('data-preview');
            var target = document.querySelector(selector);
            var file = input.files && input.files[0];
            if (!target || !file || !/^image\//.test(file.type)) { return; }
            var reader = new FileReader();
            reader.onload = function (ev) {
                target.src = ev.target.result;
                target.hidden = false;
                var placeholder = document.querySelector(selector + '-placeholder');
                if (placeholder) { placeholder.hidden = true; }
            };
            reader.readAsDataURL(file);
        });
    });

    // Light / dark theme toggle (state lives in theme_head.php)
    function syncThemeButtons() {
        var dark = window.myeventTheme && window.myeventTheme.get() === 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            // Labels come from the page so they follow the interface language.
            btn.setAttribute('aria-label', dark ? (btn.getAttribute('data-label-light') || 'Switch to light theme')
                                                : (btn.getAttribute('data-label-dark') || 'Switch to dark theme'));
        });
    }
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!window.myeventTheme) { return; }
            window.myeventTheme.set(window.myeventTheme.get() === 'dark' ? 'light' : 'dark');
            syncThemeButtons();
        });
    });
    syncThemeButtons();

    // Print button: <button data-print>
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });

    // Report filters: typing a date switches the period to "Custom dates"
    document.querySelectorAll('[data-custom-range]').forEach(function (input) {
        input.addEventListener('change', function () {
            var range = input.form && input.form.querySelector('select[name=range]');
            if (range) { range.value = 'custom'; }
        });
    });

    // Chart hover / focus text: any element with data-tip inside a .viz
    var tip = null;
    function showTip(el) {
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'viz-tip';
            tip.setAttribute('role', 'presentation');
            document.body.appendChild(tip);
        }
        tip.textContent = el.getAttribute('data-tip');
        tip.hidden = false;
        var r = el.getBoundingClientRect();
        var bar = el.querySelector('.viz-bar') || el;
        var top = bar.getBoundingClientRect().top - (bar.querySelector('.viz-value') ? 20 : 0);
        if (bar === el) { top = r.top; }
        var x = r.left + r.width / 2 + window.scrollX;
        var w = tip.offsetWidth;
        x = Math.max(window.scrollX + 8 + w / 2, Math.min(x, window.scrollX + document.documentElement.clientWidth - 8 - w / 2));
        tip.style.left = x + 'px';
        tip.style.top = (top + window.scrollY - tip.offsetHeight - 8) + 'px';
    }
    function hideTip() { if (tip) { tip.hidden = true; } }
    document.querySelectorAll('.viz [data-tip]').forEach(function (el) {
        el.addEventListener('mouseenter', function () { showTip(el); });
        el.addEventListener('focus', function () { showTip(el); });
        el.addEventListener('mouseleave', hideTip);
        el.addEventListener('blur', hideTip);
    });
    window.addEventListener('scroll', hideTip, { passive: true });

    // Auto-submit filters: <select data-autosubmit>
    document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () { select.form.submit(); });
    });
})();
