// Grid / list / index switch. The choice is kept only on this device.
// Grid and list change the whole page at once, by one attribute on <html>.
// Index is a link to the same category with ?view=index (the whole branch as a quick list). When Index was the last choice,
// the next category page opens in Index by itself; choosing Grid or List again brings the normal page back.
(function () {
    'use strict';

    var KEY = 'infofluxes.view';
    var root = document.documentElement;

    function stored() {
        try { return localStorage.getItem(KEY); } catch (e) { return null; }
    }

    function remember(choice) {
        try { localStorage.setItem(KEY, choice); } catch (e) { /* kept for this visit only */ }
    }

    function current() {
        return root.getAttribute('data-view') === 'list' ? 'list' : 'grid';
    }

    function paint() {
        document.querySelectorAll('[data-view-toggle]').forEach(function (box) {
            var active = box.getAttribute('data-active') || current();
            box.querySelectorAll('[data-view-btn], [data-view-link]').forEach(function (b) {
                var name = b.getAttribute('data-view-btn') || b.getAttribute('data-view-link');
                b.setAttribute('aria-pressed', name === active ? 'true' : 'false');
            });
        });
    }

    // Index was the last choice: open this category in Index.
    var indexLink = document.querySelector('[data-view-toggle]:not([data-active]) [data-view-link="index"]');
    if (indexLink && stored() === 'index') {
        window.location.replace(indexLink.href);
        return;
    }

    document.querySelectorAll('[data-view-btn]').forEach(function (button) {
        button.addEventListener('click', function () {
            var chosen = button.getAttribute('data-view-btn');
            root.setAttribute('data-view', chosen);
            remember(chosen);
            paint();
        });
    });

    // The links (Index, and Grid / List while in Index) remember the choice, then go there.
    document.querySelectorAll('[data-view-link]').forEach(function (link) {
        link.addEventListener('click', function () {
            var chosen = link.getAttribute('data-view-link');
            remember(chosen);
            if (chosen === 'grid' || chosen === 'list') { root.setAttribute('data-view', chosen); }
        });
    });

    paint();
})();
