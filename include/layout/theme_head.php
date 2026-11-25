<?php
/**
 * Theme bootstrap, included at the very top of <head> by both layouts.
 * Runs before the stylesheets so the page never flashes the wrong theme.
 *
 * Preference is stored in localStorage('myevent-theme') as 'light' or 'dark'.
 * With no stored preference the operating-system setting is followed (and tracked live).
 */
?>
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f4f6fb">
<script nonce="<?= e(CSP_NONCE) ?>">
(function () {
    var KEY = 'myevent-theme';
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    function stored() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
    function resolve() { var s = stored(); return s === 'light' || s === 'dark' ? s : (media && media.matches ? 'dark' : 'light'); }
    function apply(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) { meta.setAttribute('content', theme === 'dark' ? '#0b0f1e' : '#f4f6fb'); }
    }
    apply(resolve());
    window.myeventTheme = {
        get: resolve,
        set: function (theme) {
            try { localStorage.setItem(KEY, theme); } catch (e) {}
            var root = document.documentElement;
            root.classList.add('theme-transition');
            apply(theme);
            setTimeout(function () { root.classList.remove('theme-transition'); }, 250);
        }
    };
    if (media && media.addEventListener) {
        media.addEventListener('change', function () { if (!stored()) { apply(resolve()); } });
    }
})();
</script>
