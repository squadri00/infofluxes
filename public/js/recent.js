// Recently viewed: the last pages a visitor opened, kept in their own browser (localStorage).
// Nothing is sent to the server. Every storage call is guarded: if the browser blocks storage, the list is simply empty.
// It does three things on every page: remembers this page (if the page says so), fills the "Recent" card of the tab on the
// right edge (and the phone menu), and fills the strip on the home page.
(function () {
    'use strict';

    var KEY = 'infofluxes.recent';
    var KEEP = 12;                                   // how many are remembered

    function read() {
        try {
            var v = JSON.parse(localStorage.getItem(KEY) || '[]');
            return Array.isArray(v) ? v : [];
        } catch (e) {
            return [];
        }
    }

    function write(list) {
        try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) { /* nothing is kept */ }
    }

    // Only addresses of this site are ever shown as links.
    function safe(u) {
        if (typeof u !== 'string') return null;
        if (u.charAt(0) === '/' && u.charAt(1) !== '/') return u;
        return u.indexOf(location.origin + '/') === 0 ? u : null;
    }

    function clean(list) {
        return list.filter(function (i) { return i && safe(i.url) && typeof i.title === 'string' && i.title; });
    }

    // The page to remember: named by the page itself (on the body).
    var body = document.body;
    var url = (body && body.getAttribute('data-recent-url')) || '';
    var title = (body && body.getAttribute('data-recent-title')) || '';

    var items = clean(read());
    var before = items.filter(function (i) { return i.url !== url; });   // what was there before this page

    if (url && title && safe(url)) {
        items = [{ url: url, title: title }].concat(before).slice(0, KEEP);
        write(items);
    }

    function addLinks(list, shown) {
        list.textContent = '';
        shown.forEach(function (i) {
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.href = i.url;
            a.textContent = i.title;
            li.appendChild(a);
            list.appendChild(li);
        });
    }

    function fill(container, max) {
        var list = container.querySelector('[data-recent-fill]');
        if (!list) return 0;
        var shown = before.slice(0, max);
        addLinks(list, shown);
        var none = container.querySelector('[data-recent-empty]');
        if (none) none.hidden = shown.length > 0;
        var clear = container.querySelector('[data-recent-clear]');
        if (clear) clear.hidden = shown.length === 0;
        return shown.length;
    }

    function paint() {
        document.querySelectorAll('[data-recent-menu]').forEach(function (m) { fill(m, 8); });

        document.querySelectorAll('[data-recent-strip]').forEach(function (s) {
            var n = fill(s, parseInt(s.getAttribute('data-recent-max') || '4', 10));
            s.hidden = n === 0;
        });
    }

    paint();

    // The tab on the right edge: opens and closes the card.
    var tab = document.querySelector('[data-recent-tab]');
    if (tab) {
        var tabBtn = tab.querySelector('[data-recent-tab-btn]');
        var closeBtn = tab.querySelector('[data-recent-tab-close]');
        var setOpen = function (open, focus) {
            tab.classList.toggle('is-open', open);
            tabBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (focus) { (open ? closeBtn : tabBtn).focus(); }
        };
        tabBtn.addEventListener('click', function () { setOpen(!tab.classList.contains('is-open'), true); });
        if (closeBtn) { closeBtn.addEventListener('click', function () { setOpen(false, true); }); }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && tab.classList.contains('is-open')) { setOpen(false, true); }
        });
        document.addEventListener('click', function (e) {
            if (tab.classList.contains('is-open') && !tab.contains(e.target)) { setOpen(false, false); }
        });
    }

    // "Clear list": forgets everything except this page.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-recent-clear]') : null;
        if (!btn) return;
        before = [];
        items = (url && title && safe(url)) ? [{ url: url, title: title }] : [];
        write(items);
        paint();
    });
})();
