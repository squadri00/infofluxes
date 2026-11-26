/* The small clips in the open drop-down menus. Each one shows a still picture (nothing heavy loads when a menu opens);
   the video file is only fetched when the pointer or the keyboard focus is on the clip, and it plays, muted, until the pointer leaves.
   A clip that has no still picture yet shows the first moment of its video instead (fetched when its menu opens). */
(function () {
    function prepare(menu) {
        // The still pictures are fetched when the menu opens (not before).
        [].forEach.call(menu.querySelectorAll('.mega__clip img[data-src]'), function (img) {
            if (!img.getAttribute('src')) { img.src = img.getAttribute('data-src'); }
        });
        [].forEach.call(menu.querySelectorAll('.mega__clip video[data-first-frame]'), function (v) {
            if (v.getAttribute('src')) { return; }
            v.preload = 'metadata';
            v.src = v.getAttribute('data-src') + '#t=0.5';
        });
    }
    function play(v) {
        if (!v) { return; }
        if (!v.getAttribute('src')) { v.src = v.getAttribute('data-src'); }
        v.preload = 'auto';
        var p = v.play();
        if (p && p.catch) { p.catch(function () {}); }
    }
    function rest(v) {
        if (!v) { return; }
        v.pause();
        if (v.parentNode) { v.parentNode.classList.remove('is-playing'); }
        try { v.currentTime = v.hasAttribute('data-first-frame') ? 0.5 : 0; } catch (e) {}
    }
    // The video is shown over the still picture once it really plays.
    document.addEventListener('playing', function (e) {
        var v = e.target;
        if (v && v.tagName === 'VIDEO' && v.closest && v.closest('.mega__clip') && v.parentNode) { v.parentNode.classList.add('is-playing'); }
    }, true);
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
