// Grid / list switch. The choice is kept only on this device and applied to the whole page by one attribute on <html>.
(function () {
    'use strict';

    var KEY = 'infofluxes.view';
    var root = document.documentElement;

    function current() {
        return root.getAttribute('data-view') === 'list' ? 'list' : 'grid';
    }

    function paint() {
        document.querySelectorAll('[data-view-toggle]').forEach(function (box) {
            box.querySelectorAll('[data-view-btn]').forEach(function (b) {
                b.setAttribute('aria-pressed', b.getAttribute('data-view-btn') === current() ? 'true' : 'false');
            });
        });
    }

    document.querySelectorAll('[data-view-btn]').forEach(function (button) {
        button.addEventListener('click', function () {
            var chosen = button.getAttribute('data-view-btn');
            root.setAttribute('data-view', chosen);
            try { localStorage.setItem(KEY, chosen); } catch (e) { /* kept for this visit only */ }
            paint();
        });
    });

    paint();
})();
