/*
 * The Quran Index: the search box filters the list as you type (by ID, by words, or by the name of the category),
 * and the two buttons open or close every category. Without this script the page is still a complete list.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-qi]');
    if (!root) { return; }

    var tools = root.querySelector('[data-qi-tools]');
    var input = root.querySelector('[data-qi-search]');
    var count = root.querySelector('[data-qi-count]');
    var none = root.querySelector('[data-qi-none]');
    var cats = Array.prototype.slice.call(root.querySelectorAll('[data-qi-cat]'));
    var parts = Array.prototype.slice.call(root.querySelectorAll('[data-qi-part]'));
    var items = Array.prototype.slice.call(root.querySelectorAll('[data-qi-item]'));
    var foundText = root.getAttribute('data-found') || ':n found';

    if (tools) { tools.hidden = false; }

    function setOpen(open) { cats.forEach(function (c) { c.open = open; }); }
    var openBtn = root.querySelector('[data-qi-open]');
    var closeBtn = root.querySelector('[data-qi-close]');
    if (openBtn) { openBtn.addEventListener('click', function () { setOpen(true); }); }
    if (closeBtn) { closeBtn.addEventListener('click', function () { setOpen(false); }); }

    function filter() {
        var words = (input.value || '').toLowerCase().trim().split(/\s+/).filter(Boolean);
        var shown = 0;

        items.forEach(function (li) {
            var text = li.getAttribute('data-text') || '';
            var ok = words.every(function (w) { return text.indexOf(w) !== -1; });
            li.hidden = !ok;
            if (ok) { shown++; }
        });

        // a sub-category, a category and a part show only when something inside them shows
        root.querySelectorAll('[data-qi-sub]').forEach(function (s) {
            s.hidden = words.length > 0 && !s.querySelector('[data-qi-item]:not([hidden])');
        });
        cats.forEach(function (c) {
            var any = !!c.querySelector('[data-qi-item]:not([hidden])');
            c.hidden = words.length > 0 && !any;
            if (words.length > 0) { c.open = any; }
        });
        parts.forEach(function (p) {
            p.hidden = words.length > 0 && !p.querySelector('[data-qi-cat]:not([hidden])');
        });

        if (count) { count.textContent = words.length ? foundText.replace(':n', String(shown)) : ''; }
        if (none) { none.hidden = !(words.length && shown === 0); }
    }

    if (input) { input.addEventListener('input', filter); }

    // A link from the site search (#e-10.1.005) opens the category that holds the subject and marks it.
    function openHash() {
        var id = decodeURIComponent((window.location.hash || '').replace(/^#/, ''));
        if (id.indexOf('e-') !== 0) { return; }
        var el = document.getElementById(id);
        if (!el) { return; }
        for (var p = el.parentElement; p; p = p.parentElement) { if (p.tagName === 'DETAILS') { p.open = true; } }
        if (el.tagName === 'DETAILS') { el.open = true; }
        el.classList.add('qi__hit');
        el.scrollIntoView({ block: 'center' });
    }
    openHash();
    window.addEventListener('hashchange', openHash);
})();