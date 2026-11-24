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

    // Auto-submit filters: <select data-autosubmit>
    document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () { select.form.submit(); });
    });
})();
