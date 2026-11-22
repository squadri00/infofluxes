/*
 * The picture gallery: a click on a picture opens it big, with Previous and Next (buttons, the arrow keys, or a swipe).
 * Without this script a picture is an ordinary link to its page, so nothing is lost. The big picture is the whole picture (never cut).
 * The address gets #I-12 while a picture is open, so it can be shared; opening that address opens the picture.
 */
(function () {
    'use strict';

    var grid = document.querySelector('[data-gallery-grid]');
    if (!grid) { return; }

    var links = Array.prototype.slice.call(grid.querySelectorAll('.gtile__link'));
    if (!links.length) { return; }

    var labels = {};
    try { labels = JSON.parse(grid.getAttribute('data-labels') || '{}'); } catch (e) { labels = {}; }
    var rtl = (document.documentElement.getAttribute('dir') || 'ltr') === 'rtl';

    var box = null;
    var els = {};
    var index = -1;
    var opener = null;
    var touchX = null;

    function text(s) { return s == null ? '' : String(s); }

    function el(tag, cls, attrs) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
        return n;
    }

    function build() {
        box = el('div', 'gv', { role: 'dialog', 'aria-modal': 'true', 'aria-label': labels.dialog || 'Picture viewer', hidden: 'hidden' });
        els.close = el('button', 'gv__close', { type: 'button', 'aria-label': labels.close || 'Close' });
        els.close.innerHTML = '<span aria-hidden="true">&times;</span>';
        els.prev = el('button', 'gv__nav gv__nav--prev', { type: 'button', 'aria-label': labels.prev || 'Previous picture' });
        els.prev.innerHTML = '<span aria-hidden="true">&#8249;</span>';
        els.next = el('button', 'gv__nav gv__nav--next', { type: 'button', 'aria-label': labels.next || 'Next picture' });
        els.next.innerHTML = '<span aria-hidden="true">&#8250;</span>';

        var fig = el('figure', 'gv__fig');
        els.img = el('img', 'gv__img', { alt: '' });
        var cap = el('figcaption', 'gv__cap', { 'aria-live': 'polite' });
        els.id = el('span', 'gv__id');
        els.kind = el('span', 'gv__kind');
        els.title = el('strong', 'gv__title');
        els.parent = el('span', 'gv__parent');
        els.count = el('span', 'gv__count');
        els.go = el('a', 'btn btn--primary gv__go');
        var meta = el('div', 'gv__meta');
        meta.appendChild(els.id);
        meta.appendChild(els.kind);
        meta.appendChild(els.count);
        cap.appendChild(meta);
        cap.appendChild(els.title);
        cap.appendChild(els.parent);
        cap.appendChild(els.go);
        fig.appendChild(els.img);
        fig.appendChild(cap);

        box.appendChild(els.close);
        box.appendChild(els.prev);
        box.appendChild(fig);
        box.appendChild(els.next);
        document.body.appendChild(box);

        els.close.addEventListener('click', close);
        els.prev.addEventListener('click', function () { go(-1); });
        els.next.addEventListener('click', function () { go(1); });
        // A click on the dark part (not on the picture, the words or a button) closes the viewer.
        box.addEventListener('click', function (e) { if (e.target === box || e.target === fig) { close(); } });
        fig.addEventListener('touchstart', function (e) { touchX = e.touches.length === 1 ? e.touches[0].clientX : null; }, { passive: true });
        fig.addEventListener('touchend', function (e) {
            if (touchX === null || !e.changedTouches.length) { return; }
            var dx = e.changedTouches[0].clientX - touchX;
            touchX = null;
            if (Math.abs(dx) > 50) { go((dx < 0) !== rtl ? 1 : -1); }
        }, { passive: true });
    }

    function preload(i) {
        var link = links[(i + links.length) % links.length];
        if (link) { var img = new Image(); img.src = link.getAttribute('data-full'); }
    }

    function show(i) {
        index = (i + links.length) % links.length;
        var link = links[index];
        var thumb = link.querySelector('img');
        var full = link.getAttribute('data-full');

        // The small picture shows at once; the whole picture replaces it as soon as it has loaded.
        els.img.classList.add('is-loading');
        els.img.src = thumb ? thumb.currentSrc || thumb.src : full;
        els.img.alt = text(link.getAttribute('data-alt'));
        var big = new Image();
        var mine = index;
        big.onload = function () { if (mine === index) { els.img.src = full; els.img.classList.remove('is-loading'); } };
        big.onerror = function () { if (mine === index) { els.img.classList.remove('is-loading'); } };
        big.src = full;

        els.id.textContent = text(link.getAttribute('data-id'));
        els.kind.textContent = text(link.getAttribute('data-kind'));
        els.title.textContent = text(link.getAttribute('data-title'));
        var parent = text(link.getAttribute('data-parent'));
        els.parent.textContent = parent;
        els.parent.hidden = parent === '' || parent === text(link.getAttribute('data-title'));
        els.count.textContent = (index + 1) + ' / ' + links.length;
        els.go.href = link.getAttribute('href');
        els.go.textContent = (labels.go || 'Go to this page') + ' →';
        els.prev.hidden = els.next.hidden = links.length < 2;

        preload(index + 1);
        preload(index - 1);
        try { history.replaceState(null, '', location.pathname + location.search + '#' + text(link.getAttribute('data-id'))); } catch (e) { /* the address stays as it is */ }
    }

    function open(i) {
        if (!box) { build(); }
        opener = document.activeElement;
        box.hidden = false;
        document.documentElement.classList.add('gv-open');
        show(i);
        els.close.focus();
    }

    function close() {
        if (!box || box.hidden) { return; }
        box.hidden = true;
        document.documentElement.classList.remove('gv-open');
        els.img.removeAttribute('src');
        try { history.replaceState(null, '', location.pathname + location.search); } catch (e) { /* ignore */ }
        if (opener && opener.focus) { try { opener.focus(); } catch (e) { /* gone */ } }
    }

    function go(step) { if (box && !box.hidden) { show(index + step); } }

    links.forEach(function (link, i) {
        link.addEventListener('click', function (e) {
            // A click with Ctrl, Shift or the middle button still opens the page in a new tab, as usual.
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) { return; }
            e.preventDefault();
            open(i);
        });
    });

    document.addEventListener('keydown', function (e) {
        if (!box || box.hidden) { return; }
        if (e.key === 'Escape') { close(); }
        else if (e.key === 'ArrowRight') { e.preventDefault(); go(rtl ? -1 : 1); }
        else if (e.key === 'ArrowLeft') { e.preventDefault(); go(rtl ? 1 : -1); }
        else if (e.key === 'Home') { e.preventDefault(); show(0); }
        else if (e.key === 'End') { e.preventDefault(); show(links.length - 1); }
        else if (e.key === 'Tab') {
            // The focus stays inside the viewer.
            var items = [els.close, els.prev, els.next, els.go].filter(function (n) { return !n.hidden; });
            var at = items.indexOf(document.activeElement);
            e.preventDefault();
            items[(at + (e.shiftKey ? -1 : 1) + items.length) % items.length].focus();
        }
    });

    // An address with #I-12 opens that picture (if it is on this page).
    function fromHash() {
        var id = decodeURIComponent((location.hash || '').replace(/^#/, ''));
        if (!id) { return; }
        for (var i = 0; i < links.length; i++) {
            if (links[i].getAttribute('data-id') === id) { open(i); return; }
        }
    }
    fromHash();
    // The address changed by hand (or the Back button): follow it.
    window.addEventListener('hashchange', function () {
        if (location.hash) { fromHash(); } else { close(); }
    });
})();
