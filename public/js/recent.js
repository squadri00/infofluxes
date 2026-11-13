// Recently viewed: the last pages a visitor opened, kept in their own browser (localStorage).
// Nothing is sent to the server. Every storage call is guarded: if the browser blocks storage, the list is simply empty.
(function () {
    'use strict';

    var box = document.querySelector('[data-recent]');
    if (!box) return;

    var KEY = 'infofluxes.recent';
    var KEEP = 12;                                   // how many are remembered
    var SHOW = parseInt(box.getAttribute('data-recent-max') || '5', 10);
    var url = box.getAttribute('data-current-url') || '';
    var title = box.getAttribute('data-current-title') || '';

    function read() {
        try {
            var v = JSON.parse(localStorage.getItem(KEY) || '[]');
            return Array.isArray(v) ? v : [];
        } catch (e) {
            return [];
        }
    }

    // Only addresses of this site are ever shown as links.
    function safe(u) {
        if (typeof u !== 'string') return null;
        if (u.charAt(0) === '/' && u.charAt(1) !== '/') return u;
        return u.indexOf(location.origin + '/') === 0 ? u : null;
    }

    var items = read().filter(function (i) { return i && safe(i.url) && typeof i.title === 'string' && i.title; });

    // What to show: the latest pages other than this one.
    var shown = items.filter(function (i) { return i.url !== url; }).slice(0, SHOW);

    // Then remember this page at the top.
    if (url && title && safe(url)) {
        var next = [{ url: url, title: title }].concat(items.filter(function (i) { return i.url !== url; })).slice(0, KEEP);
        try { localStorage.setItem(KEY, JSON.stringify(next)); } catch (e) { /* nothing is kept */ }
    }

    var list = box.querySelector('[data-recent-list]');
    var none = box.querySelector('[data-recent-none]');
    shown.forEach(function (i) {
        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = i.url;
        a.textContent = i.title;
        li.appendChild(a);
        list.appendChild(li);
    });
    if (none) none.hidden = shown.length > 0;
})();
