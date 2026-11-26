/* The small clips in the open drop-down menus: the video file is only fetched when its menu opens (first moment as the picture), and the clip plays, muted, while the pointer or the keyboard focus is on it. */
(function () {
    function prepare(menu) {
        [].forEach.call(menu.querySelectorAll('.mega__clip video[data-src]'), function (v) {
            if (v.getAttribute('src')) { return; }
            v.preload = 'metadata';
            v.src = v.getAttribute('data-src') + '#t=0.5';
        });
    }
    function play(v) {
        if (!v || !v.getAttribute('src')) { return; }
        v.preload = 'auto';
        var p = v.play();
        if (p && p.catch) { p.catch(function () {}); }
    }
    function rest(v) {
        if (!v) { return; }
        v.pause();
        try { v.currentTime = 0.5; } catch (e) {}
    }
    // "toggle" does not bubble, so it is caught on the way down.
    document.addEventListener('toggle', function (e) {
        var d = e.target;
        if (d && d.open && d.classList && d.classList.contains('menu__drop--mega')) { prepare(d); }
    }, true);
    document.addEventListener('mouseover', function (e) {
        var a = e.target.closest && e.target.closest('.mega__clip');
        if (a) { play(a.querySelector('video')); }
    });
    document.addEventListener('mouseout', function (e) {
        var a = e.target.closest && e.target.closest('.mega__clip');
        if (a && !a.contains(e.relatedTarget)) { rest(a.querySelector('video')); }
    });
    document.addEventListener('focusin', function (e) {
        var a = e.target.closest && e.target.closest('.mega__clip');
        if (a) { play(a.querySelector('video')); }
    });
    document.addEventListener('focusout', function (e) {
        var a = e.target.closest && e.target.closest('.mega__clip');
        if (a) { rest(a.querySelector('video')); }
    });
})();
