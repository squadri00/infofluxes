// The page header: the Menu button on phones, and drop-downs that open when you point at them.
(function () {
    'use strict';

    var header = document.querySelector('[data-site-header]');
    if (!header) { return; }

    // --- Sticky header: slim when scrolled (the top bar and the search bar fold away), full again at the top ---
    var searchToggle = header.querySelector('[data-search-toggle]');
    var timer = null;

    // The slim header is shorter than the full one. If the page simply got shorter, the content would jump up,
    // a short page would push the scroll position back, and the header would flip again and again (blinking).
    // So the height that was given up is kept as empty space under the header: the page never changes height.
    var saved = 0;

    // If the menu items do not all fit in one row beside the logo, the menu moves to its own row under the logo.
    // It is measured, not guessed, so it keeps working however many items the menu has.
    var list = header.querySelector('.menu--main .menu__list');

    function fitNav() {
        header.classList.remove('nav-below');
        if (!list || !list.offsetParent || window.getComputedStyle(list).flexDirection === 'column') { return; }

        var items = list.children;
        for (var i = 1; i < items.length; i++) {
            if (items[i].offsetTop - items[0].offsetTop > 8) { header.classList.add('nav-below'); return; }
        }
    }

    // How much shorter the slim header is (measured in one go, so nothing is drawn in between).
    function measure() {
        var wasCompact = header.classList.contains('is-compact');
        header.classList.remove('is-compact');
        fitNav();
        var full = header.offsetHeight;
        header.classList.add('is-compact');
        fitNav();
        saved = Math.max(0, full - header.offsetHeight);
        if (!wasCompact) { header.classList.remove('is-compact'); fitNav(); }
    }

    function keepPageHeight() {
        measure();
        header.style.marginBottom = header.classList.contains('is-compact') ? saved + 'px' : '';
    }

    function setCompact(compact) {
        if (header.classList.contains('is-compact') === compact) { return; }
        header.classList.toggle('is-compact', compact);
        fitNav();
        if (searchToggle) { searchToggle.hidden = !compact; }
        if (!compact) { header.classList.remove('is-search-open'); }
        keepPageHeight();
    }

    function onScroll() {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var y = window.pageYOffset || document.documentElement.scrollTop;
            // Two different limits, so the header does not flicker around one line. The slim header only
            // takes over once the part it gave up is out of sight.
            var on = Math.max(170, saved + 20);
            if (y > on) { setCompact(true); } else if (y < 60) { setCompact(false); }
        }, 20);
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', function () { fitNav(); keepPageHeight(); });
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function () { fitNav(); keepPageHeight(); }); }
    measure();
    onScroll();

    if (searchToggle) {
        searchToggle.addEventListener('click', function () {
            var open = header.classList.toggle('is-search-open');
            searchToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                var input = header.querySelector('.header-search__input');
                if (input) { input.focus(); }
            }
        });
    }
    // --- The Menu button (phones) ---
    var toggle = header.querySelector('[data-nav-toggle]');
    var nav = header.querySelector('[data-site-nav]');
    if (toggle && nav) {
        toggle.hidden = false;
        header.classList.add('has-nav-toggle');
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // --- Drop-downs: open when pointed at (mouse), always open by click or keyboard ---
    var drops = Array.prototype.slice.call(header.querySelectorAll('details.menu__drop'));
    var canHover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    function closeAll(except) {
        drops.forEach(function (d) { if (d !== except) { d.removeAttribute('open'); } });
    }

    drops.forEach(function (drop) {
        var timer = null;

        drop.addEventListener('toggle', function () {
            if (drop.open) { closeAll(drop); }
        });

        if (canHover) {
            drop.addEventListener('mouseenter', function () {
                clearTimeout(timer);
                closeAll(drop);
                drop.setAttribute('open', '');
            });
            drop.addEventListener('mouseleave', function () {
                timer = setTimeout(function () { drop.removeAttribute('open'); }, 180);
            });
        }
    });

    document.addEventListener('click', function (event) {
        if (!header.contains(event.target)) { closeAll(null); return; }
        drops.forEach(function (d) { if (!d.contains(event.target)) { d.removeAttribute('open'); } });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') { return; }
        var openOne = drops.filter(function (d) { return d.open; })[0];
        if (openOne) {
            openOne.removeAttribute('open');
            var summary = openOne.querySelector('summary');
            if (summary) { summary.focus(); }
        }
    });
})();
