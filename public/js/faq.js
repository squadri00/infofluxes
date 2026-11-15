/* FAQ page: a link such as /faq#q-3 opens that question, and the box above the list filters the questions. Without this script the questions still open and close one by one. */
(function () {
    'use strict';
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-faq-item]'));
    if (!items.length) { return; }

    function openFromHash() {
        var id = (location.hash || '').replace('#', '');
        if (!id) { return; }
        var el = document.getElementById(id);
        if (el && el.matches('[data-faq-item]')) {
            el.open = true;
            el.scrollIntoView({ block: 'start' });
        }
    }
    openFromHash();
    window.addEventListener('hashchange', openFromHash);

    var box = document.querySelector('[data-faq-search]');
    var nothing = document.querySelector('[data-faq-nothing]');
    var groups = Array.prototype.slice.call(document.querySelectorAll('[data-faq-group]'));
    if (!box) { return; }

    box.addEventListener('input', function () {
        var q = box.value.trim().toLowerCase();
        var shown = 0;
        items.forEach(function (d) {
            var hit = q === '' || d.textContent.toLowerCase().indexOf(q) !== -1;
            d.hidden = !hit;
            if (hit) { shown++; }
            if (q !== '') { d.open = hit; }
        });
        groups.forEach(function (g) {
            g.hidden = !g.querySelector('[data-faq-item]:not([hidden])');
        });
        if (nothing) { nothing.hidden = shown !== 0; }
    });
})();
