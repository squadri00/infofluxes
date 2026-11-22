/*
 * Topic tabs: the cards reshuffle into their new places, with no page reload.
 * A card that is in both tabs glides from its old place to its new one. A card that is not in the new tab shrinks away.
 * A card that is new grows into its place. The pill slides to the new tab, and the height of the list changes smoothly.
 * The tabs are real links, so without this script (or if anything goes wrong) a click simply opens the page of that tab.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-topic-tabs]');
    if (!root || !window.fetch || !window.DOMParser) { return; }

    var nav = root.querySelector('[data-topic-nav]');
    var panel = root.querySelector('[data-topic-panel]');
    if (!nav || !panel) { return; }

    // The tab of an address is found by one word of the address ("topic" for the stories; the gallery says data-param="kind").
    // data-strip: a word that asks for a new random order (the gallery's "shuffle"); it is taken out of the address that is shown.
    // data-nocache: every click fetches the page again, so every click gives a new order.
    var PARAM = root.getAttribute('data-param') || 'topic';
    var STRIP = root.getAttribute('data-strip') || '';
    var NOCACHE = root.hasAttribute('data-nocache');

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var MOVE = 520;      // milliseconds: a card gliding to its new place
    var EASE = 'cubic-bezier(.2, .7, .2, 1)';
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

    /** Where every card of the list is now, by its key (the id of the story). */
    function places() {
        var out = {};
        Array.prototype.forEach.call(panel.querySelectorAll('[data-key]'), function (el) {
            var r = el.getBoundingClientRect();
            out[el.getAttribute('data-key')] = { el: el, left: r.left, top: r.top, width: r.width, height: r.height };
        });
        return out;
    }

    /** Swaps the list for the new one and lets the cards find their places. */
    function reshuffle(html) {
        var before = places();
        var box = panel.getBoundingClientRect();
        var oldHeight = panel.offsetHeight;
        var ghosts = [];

        if (!reduced) {
            Object.keys(before).forEach(function (key) {
                var b = before[key];
                var g = b.el.cloneNode(true);
                g.className += ' topic-ghost';
                g.removeAttribute('data-key');
                g.setAttribute('aria-hidden', 'true');
                g.style.left = (b.left - box.left) + 'px';
                g.style.top = (b.top - box.top) + 'px';
                g.style.width = b.width + 'px';
                g.style.height = b.height + 'px';
                ghosts.push({ key: key, el: g });
            });
        }

        panel.innerHTML = html;
        if (reduced) { return; }

        var after = places();
        var newHeight = panel.offsetHeight;

        // The list changes its height smoothly.
        panel.style.height = oldHeight + 'px';
        void panel.offsetHeight;
        panel.style.transition = 'height ' + MOVE + 'ms ' + EASE;
        panel.style.height = newHeight + 'px';
        window.setTimeout(function () { panel.style.transition = ''; panel.style.height = ''; }, MOVE + 60);

        Object.keys(after).forEach(function (key) {
            var a = after[key];
            var b = before[key];
            if (b) {
                // It was here too: it glides from its old place.
                a.el.style.transition = 'none';
                a.el.style.transform = 'translate(' + (b.left - a.left) + 'px, ' + (b.top - a.top) + 'px)';
                a.el.style.zIndex = '2';
                void a.el.offsetWidth;
                a.el.style.transition = 'transform ' + MOVE + 'ms ' + EASE;
                a.el.style.transform = '';
                window.setTimeout(function () { a.el.style.transition = ''; a.el.style.zIndex = ''; }, MOVE + 60);
            } else {
                // It is new: it grows into its place.
                a.el.style.transition = 'none';
                a.el.style.opacity = '0';
                a.el.style.transform = 'scale(.86)';
                void a.el.offsetWidth;
                a.el.style.transition = 'transform ' + MOVE + 'ms ' + EASE + ', opacity ' + MOVE + 'ms ease';
                a.el.style.opacity = '';
                a.el.style.transform = '';
                window.setTimeout(function () { a.el.style.transition = ''; }, MOVE + 60);
            }
        });

        // A card that is not in the new list shrinks away where it was.
        ghosts.forEach(function (g) {
            if (after[g.key]) { return; }
            panel.appendChild(g.el);
            void g.el.offsetWidth;
            g.el.style.transition = 'transform ' + (MOVE - 120) + 'ms ' + EASE + ', opacity ' + (MOVE - 120) + 'ms ease';
            g.el.style.transform = 'scale(.8)';
            g.el.style.opacity = '0';
            window.setTimeout(function () { if (g.el.parentNode) { g.el.parentNode.removeChild(g.el); } }, MOVE);
        });
    }

    // The page of a tab is fetched as soon as the pointer is over it, so the click feels instant.
    var cache = {};
    function load(url) {
        var hit = cache[url];
        if (!NOCACHE && hit && Date.now() - hit.at < 60000) { return hit.p; }
        var p = fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) { throw new Error('bad'); }
            return r.text();
        });
        cache[url] = { at: Date.now(), p: p };
        p.catch(function () { delete cache[url]; });
        return p;
    }
    function warm(e) {
        if (NOCACHE) { return; }
        var a = e.target.closest ? e.target.closest('a') : null;
        if (a && nav.contains(a) && a.origin === window.location.origin) { load(a.href); }
    }
    nav.addEventListener('pointerenter', warm, true);
    nav.addEventListener('focusin', warm);
    nav.addEventListener('touchstart', warm, { passive: true });

    function show(url, push) {
        if (busy) { wanted = { url: url, push: push }; return; }
        busy = true;
        wanted = null;

        // The tab of this address: the one with the same ?topic= (no ?topic= is the tab All).
        var topic = new URL(url, window.location.href).searchParams.get(PARAM);
        var target = null;
        Array.prototype.forEach.call(nav.querySelectorAll('.topic-tabs__tab'), function (a) {
            if (new URL(a.href).searchParams.get(PARAM) === topic) { target = a; }
        });
        if (target) {
            var old = current();
            if (old) { old.classList.remove('is-current'); old.removeAttribute('aria-current'); }
            target.classList.add('is-current');
            target.setAttribute('aria-current', 'page');
            moveInk(true);
            try { target.scrollIntoView({ block: 'nearest', inline: 'center', behavior: reduced ? 'auto' : 'smooth' }); } catch (e) { /* old browser */ }
        }

        panel.setAttribute('aria-busy', 'true');
        load(url).then(function (text) {
            var doc = new DOMParser().parseFromString(text, 'text/html');
            var next = doc.querySelector('[data-topic-panel]');
            if (!next) { throw new Error('no panel'); }
            reshuffle(next.innerHTML);
            if (push) {
                var shown = new URL(url, window.location.href);
                if (STRIP) { shown.searchParams.delete(STRIP); }
                history.pushState({ topic: true }, '', shown.pathname + shown.search + shown.hash);
            }
            document.title = doc.title || document.title;
            panel.removeAttribute('aria-busy');
            window.setTimeout(function () {
                busy = false;
                if (wanted) { show(wanted.url, wanted.push); }
            }, reduced ? 0 : 200);
        }).catch(function () {
            window.location.href = url;   // anything went wrong: open the page the normal way
        });
    }

    root.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a') : null;
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        var inTabs = nav.contains(a);
        var inPager = panel.contains(a) && a.closest('.pager');
        // A link inside the list that says data-topic-link (the gallery's other filters and its Shuffle button) changes the list the same smooth way.
        var inLink = panel.contains(a) && a.hasAttribute('data-topic-link');
        if (!inTabs && !inPager && !inLink) { return; }
        if (a.origin !== window.location.origin) { return; }
        if (inTabs && !STRIP && a.classList.contains('is-current') && a.href === window.location.href) { e.preventDefault(); return; }
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
})();
