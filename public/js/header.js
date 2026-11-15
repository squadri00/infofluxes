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

    // The menu never drops to a second row: the items that do not fit in one row move into a "More" drop-down at the end.
    // It is measured, not guessed, so it keeps working however many items the menu has and however wide the window is.
    var list = header.querySelector('.menu--main .menu__list');
    var navBox = header.querySelector('[data-site-nav]');
    var moreLabel = (navBox && navBox.getAttribute('data-more-label')) || 'More';
    var items = list ? Array.prototype.slice.call(list.children) : [];
    var moreItem = null;

    // The right edge the menu may reach: the end of the row, less the search button when it is showing.
    function limit() {
        var inner = header.querySelector('.site-header__main-inner');
        var box = inner.getBoundingClientRect();
        var style = window.getComputedStyle(inner);
        var edge = box.right - parseFloat(style.paddingRight || 0);
        if (searchToggle && !searchToggle.hidden && searchToggle.offsetParent) { edge -= searchToggle.getBoundingClientRect().width + 12; }
        return edge;
    }

    function overflows() {
        var last = list.lastElementChild;
        return !!last && last.getBoundingClientRect().right > limit() + 1;
    }

    // One moved item as plain links: its own link, then its sub-items (they would not open inside another drop-down).
    function plainLinks(item) {
        var out = document.createDocumentFragment();
        var drop = item.querySelector('details.menu__drop');
        // A wide panel: its own link first, then the sub-categories by name (not the feature card).
        if (drop && drop.classList.contains('menu__drop--mega')) {
            var headLi = document.createElement('li');
            headLi.className = 'menu__more-head';
            var headA = document.createElement('a');
            headA.href = drop.getAttribute('data-url');
            headA.textContent = drop.querySelector('summary').textContent;
            headLi.appendChild(headA);
            out.appendChild(headLi);
            Array.prototype.forEach.call(drop.querySelectorAll('.mega__list a'), function (a) {
                var li = document.createElement('li');
                li.className = 'menu__more-child';
                var c = document.createElement('a');
                c.href = a.getAttribute('href');
                var name = a.querySelector('strong');
                c.textContent = (name || a).textContent;
                li.appendChild(c);
                out.appendChild(li);
            });
            return out;
        }
        var links = drop ? Array.prototype.slice.call(drop.querySelectorAll('.menu__sub a')) : Array.prototype.slice.call(item.querySelectorAll(':scope > a'));
        links.forEach(function (a, i) {
            var li = document.createElement('li');
            var copy = document.createElement('a');
            copy.href = a.getAttribute('href');
            copy.textContent = (drop && i === 0) ? drop.querySelector('summary').textContent : a.textContent;
            if (drop && i === 0) { li.className = 'menu__more-head'; } else if (drop) { li.className = 'menu__more-child'; }
            li.appendChild(copy);
            out.appendChild(li);
        });
        return out;
    }

    function fitNav() {
        header.classList.remove('nav-below');
        if (!list || !items.length) { return; }

        // Put everything back first, then move out only what does not fit.
        if (moreItem && moreItem.parentNode) { moreItem.parentNode.removeChild(moreItem); }
        moreItem = null;
        items.forEach(function (item) { if (item.parentNode !== list) { list.appendChild(item); } });

        // On a phone the menu is a column behind the Menu button: nothing to fit.
        if (!list.offsetParent || window.getComputedStyle(list).flexDirection === 'column') { return; }

        header.classList.add('menu-fit');
        if (!overflows()) { return; }

        moreItem = document.createElement('li');
        moreItem.className = 'menu__item menu__more';
        moreItem.innerHTML = '<details class="menu__drop"><summary></summary><ul class="menu__sub"></ul></details>';
        moreItem.querySelector('summary').textContent = moreLabel;
        var sub = moreItem.querySelector('.menu__sub');
        list.appendChild(moreItem);
        wireDrop(moreItem.querySelector('details'));

        var moved = [];
        while (overflows() && items.length - moved.length > 1) {
            var item = items[items.length - 1 - moved.length];
            moved.push(item);
            list.removeChild(item);
        }
        // The first moved item is the last in the menu: show them in menu order.
        moved.reverse().forEach(function (item) { sub.appendChild(plainLinks(item)); });
        if (!sub.children.length) { list.removeChild(moreItem); moreItem = null; }
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
    function canHover() { return !!(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches); }

    function allDrops() { return Array.prototype.slice.call(header.querySelectorAll('details.menu__drop')); }

    function closeAll(except) {
        allDrops().forEach(function (d) { if (d !== except) { d.removeAttribute('open'); } });
    }

    function wireDrop(drop) {
        var timer = null;

        drop.addEventListener('toggle', function () {
            if (drop.open) { closeAll(drop); }
        });

        if (canHover()) {
            drop.addEventListener('mouseenter', function () {
                clearTimeout(timer);
                closeAll(drop);
                drop.setAttribute('open', '');
            });
            drop.addEventListener('mouseleave', function () {
                timer = setTimeout(function () { drop.removeAttribute('open'); }, 180);
            });
        }
    }

    allDrops().forEach(wireDrop);
    document.addEventListener('click', function (event) {
        if (!header.contains(event.target)) { closeAll(null); return; }
        allDrops().forEach(function (d) { if (!d.contains(event.target)) { d.removeAttribute('open'); } });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') { return; }
        var openOne = allDrops().filter(function (d) { return d.open; })[0];
        if (openOne) {
            openOne.removeAttribute('open');
            var summary = openOne.querySelector('summary');
            if (summary) { summary.focus(); }
        }
    });
})();
