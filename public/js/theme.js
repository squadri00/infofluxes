// Light / dark mode button. The choice is kept only on this device. Before it is chosen, the device's own setting is followed.
(function () {
    'use strict';

    var root = document.documentElement;
    var query = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function current() {
        var chosen = root.getAttribute('data-theme');
        if (chosen === 'dark' || chosen === 'light') { return chosen; }
        return query && query.matches ? 'dark' : 'light';
    }

    function paint(button) {
        var dark = current() === 'dark';
        // The button offers the OTHER mode.
        button.setAttribute('aria-label', button.getAttribute(dark ? 'data-label-to-light' : 'data-label-to-dark'));
        button.setAttribute('title', button.getAttribute(dark ? 'data-label-to-light' : 'data-label-to-dark'));
        var icon = button.querySelector('[data-theme-icon]');
        if (icon) { icon.textContent = dark ? '☀' : '☾'; }
    }

    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
        paint(button);
        button.addEventListener('click', function () {
            var next = current() === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('theme', next); } catch (e) { /* kept for this visit only */ }
            document.querySelectorAll('[data-theme-toggle]').forEach(paint);
        });
    });
})();
