/*
 * The admin tables that are wider than the screen get a second horizontal scrollbar ABOVE the table, kept in step with the one below it.
 * Without this script nothing is lost: the table still scrolls with its own bar at the bottom.
 * The tables are drawn again by Livewire when a filter or a page changes, so the bar is checked again after every change.
 */
(function () {
    'use strict';

    var CSS = '.if-topscroll{overflow-x:auto;overflow-y:hidden;height:14px;margin:0;scrollbar-width:thin}.if-topscroll>div{height:1px}';
    var style = document.createElement('style');
    style.textContent = CSS;
    document.head.appendChild(style);

    function build(ctn) {
        var bar = document.createElement('div');
        bar.className = 'if-topscroll';
        bar.setAttribute('aria-hidden', 'true');
        var inner = document.createElement('div');
        bar.appendChild(inner);
        // Each bar moves the other one. A scroll event only fires when the position changes, so the two do not chase each other.
        bar.addEventListener('scroll', function () { if (ctn.scrollLeft !== bar.scrollLeft) { ctn.scrollLeft = bar.scrollLeft; } });
        if (!ctn.__topScrollListener) {
            ctn.addEventListener('scroll', function () {
                var t = ctn.__topScroll;
                if (t && t.bar.scrollLeft !== ctn.scrollLeft) { t.bar.scrollLeft = ctn.scrollLeft; }
            });
            ctn.__topScrollListener = true;
        }
        ctn.parentNode.insertBefore(bar, ctn);
        ctn.__topScroll = { bar: bar, inner: inner };
    }

    function update(ctn) {
        var t = ctn.__topScroll;
        var wide = ctn.scrollWidth > ctn.clientWidth + 1;
        var width = ctn.scrollWidth + 'px';
        if (t.inner.style.width !== width) { t.inner.style.width = width; }
        var show = wide ? 'block' : 'none';
        if (t.bar.style.display !== show) { t.bar.style.display = show; }
    }

    function scan() {
        var tables = document.querySelectorAll('.fi-ta-content-ctn');
        for (var i = 0; i < tables.length; i++) {
            var ctn = tables[i];
            // (Livewire may have drawn the table again and removed the bar: then it is made again.)
            if (!ctn.__topScroll || !ctn.__topScroll.bar.isConnected) { build(ctn); }
            update(ctn);
        }
    }

    var queued = false;
    function later() {
        if (queued) { return; }
        queued = true;
        window.requestAnimationFrame(function () { queued = false; scan(); });
    }

    document.addEventListener('DOMContentLoaded', scan);
    window.addEventListener('resize', later);
    new MutationObserver(later).observe(document.documentElement, { childList: true, subtree: true });
    scan();
})();
