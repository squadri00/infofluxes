/*
 * Topic tabs: a smooth change from one tab to the next, with no page reload.
 * The tabs are real links, so without this script (or if anything goes wrong) a click simply opens the page of that tab.
 * With the script: the coloured pill slides to the new tab, the old cards fade out, the new ones fade in one after another,
 * the address and the Back button follow the tab, and nothing jumps because the panel keeps its height while it changes.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-topic-tabs]');
    if (!root || !window.fetch || !window.DOMParser) { return; }

    var nav = root.querySelector('[data-topic-nav]');
    var panel = root.querySelector('[data-topic-panel]');
    if (!nav || !panel) { return; }

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var OUT = reduced ? 0 : 170;
    var busy = false;
    var wanted = null;

    // The pill that slides under the current tab.
    var ink = document.createElement('span');
    ink.className = 'topic-tabs__ink';
    ink.setAttribute('aria-hidden', 'true');
    nav.insertBefore(ink, nav.firstChild);
    nav.classList.add('topic-tabs--js');

    function current() { return nav.querySelector('.topic-tabs__tab.is-current'); }

    function moveInk(animate) {
        var tab = current();
        if (!tab) { ink.style.opacity = '0'; return; }
        if (!animate) { ink.style.transition = 'none'; }
        ink.style.opacity = '1';
        ink.style.width = tab.offsetWidth + 'px';
        ink.style.transform = 'translateX(' + tab.offsetLeft + 'px)';
        if (!animate) { void ink.offsetWidth; ink.style.transition = ''; }
    }

    function stagger() {
        if (reduced) { return; }
        Array.prototype.forEach.call(panel.querySelectorAll('.scards > li, .topic-tabs__about, .pager'), function (el, i) {
            el.style.setProperty('--i', Math.min(i, 12));
            el.classList.add('topic-in');
        });
    }

    function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

    function show(url, push) {
        if (busy) { wanted = { url: url, push: push }; return; }
        busy = true;
        wanted = null;

        // The tab of this address: the one with the same ?topic= (no ?topic= is the tab All).
        var topic = new URL(url, window.location.href).searchParams.get('topic');
        var target = null;
        Array.prototype.forEach.call(nav.querySelectorAll('.topic-tabs__tab'), function (a) {
            if (new URL(a.href).searchParams.get('topic') === topic) { target = a; }
        });
        // A page of the same tab (the pager) keeps the tab; a tab link moves the pill at once so the click feels instant.
        if (target) {
            var old = current();
            if (old) { old.classList.remove('is-current'); old.removeAttribute('aria-current'); }
            target.classList.add('is-current');
            target.setAttribute('aria-current', 'page');
            moveInk(true);
            try { target.scrollIntoView({ block: 'nearest', inline: 'center', behavior: reduced ? 'auto' : 'smooth' }); } catch (e) { /* old browser */ }
        }

        panel.style.minHeight = panel.offsetHeight + 'px';
        panel.classList.add('is-leaving');
        panel.setAttribute('aria-busy', 'true');

        Promise.all([
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) {
                if (!r.ok) { throw new Error('bad'); }
                return r.text();
            }),
            wait(OUT)
        ]).then(function (res) {
            var doc = new DOMParser().parseFromString(res[0], 'text/html');
            var next = doc.querySelector('[data-topic-panel]');
            var nextNav = doc.querySelector('[data-topic-nav]');
            if (!next) { throw new Error('no panel'); }
            panel.innerHTML = next.innerHTML;
            if (nextNav) {
                // The counts and the current tab come from the server, so they are always right.
                var theirs = nextNav.querySelectorAll('.topic-tabs__tab');
                Array.prototype.forEach.call(nav.querySelectorAll('.topic-tabs__tab'), function (a, i) {
                    if (theirs[i]) {
                        var c = theirs[i].querySelector('.topic-tabs__count');
                        var mine = a.querySelector('.topic-tabs__count');
                        if (c && mine) { mine.textContent = c.textContent; }
                    }
                });
            }
            if (push) { history.pushState({ topic: true }, '', url); }
            document.title = doc.title || document.title;
            panel.classList.remove('is-leaving');
            stagger();
            panel.removeAttribute('aria-busy');
            window.setTimeout(function () { panel.style.minHeight = ''; }, reduced ? 0 : 420);
            busy = false;
            if (wanted) { show(wanted.url, wanted.push); }
        }).catch(function () {
            window.location.href = url;   // anything went wrong: open the page the normal way
        });
    }

    root.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a') : null;
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        var inTabs = nav.contains(a);
        var inPager = panel.contains(a) && a.closest('.pager');
        if (!inTabs && !inPager) { return; }
        if (a.origin !== window.location.origin) { return; }
        if (inTabs && a.classList.contains('is-current') && a.href === window.location.href) { e.preventDefault(); return; }
        e.preventDefault();
        show(a.href, true);
        if (inPager) {
            var top = root.getBoundingClientRect().top + window.pageYOffset - 70;
            window.scrollTo({ top: Math.max(0, top), behavior: reduced ? 'auto' : 'smooth' });
        }
    });

    window.addEventListener('popstate', function () {
        // Back or Forward: show the page of that address, in the same smooth way.
        show(window.location.href, false);
    });

    window.addEventListener('resize', function () { moveInk(false); });
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function () { moveInk(false); }); }
    moveInk(false);
    stagger();
})();
