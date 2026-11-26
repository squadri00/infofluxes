/* Clips page: one big player that plays the clips one after the other, and six thumbnails (a page of six at a time).
   Two players sit on top of each other: while one plays, the other already loads the next clip, and the two are swapped with a plain cut (no fade, which is easier on the eyes). So there is no wait between clips. */
(function () {
    var root = document.querySelector('[data-clips]');
    var dataEl = document.getElementById('clips-data');
    if (!root || !dataEl) { return; }
    var clips;
    try { clips = JSON.parse(dataEl.textContent); } catch (e) { return; }
    if (!clips.length) { return; }

    var PER = 6;
    var FADE_AT = 0.2;    // seconds before the end when the next clip (already loaded) takes over: a plain cut, no fade
    var first = root.querySelector('.clips__video');
    var second = first.cloneNode(false);
    second.removeAttribute('autoplay');
    second.removeAttribute('controls');
    second.setAttribute('aria-hidden', 'true');
    second.tabIndex = -1;
    first.parentNode.insertBefore(second, first.nextSibling);
    var front = first;        // the player that is seen
    var back = second;        // the player that loads the next clip
    var frontIndex = -1;      // the clip in the front player
    var backIndex = -1;       // the clip in the back player (loaded or loading)
    var pendingIndex = -1;    // a clip that should come to the front as soon as it is ready
    var muted = true;
    front.classList.add('is-front');

    var title = root.querySelector('[data-clips-title]');
    var blurb = root.querySelector('[data-clips-blurb]');
    var link = root.querySelector('[data-clips-link]');
    var overlay = root.querySelector('[data-clips-overlay]');
    var sound = root.querySelector('[data-clips-sound]');
    var count = root.querySelector('[data-clips-count]');
    var items = [].slice.call(root.querySelectorAll('[data-clips-item]'));
    var page = 0;
    var pages = Math.ceil(clips.length / PER);

    function wrap(i) { return (i + clips.length) % clips.length; }

    function showPage(p) {
        page = (p + pages) % pages;
        items.forEach(function (li, i) {
            var on = Math.floor(i / PER) === page;
            li.hidden = !on;
            var v = li.querySelector('video');
            if (on && v && !v.getAttribute('src')) {
                v.preload = 'metadata';
                v.src = v.getAttribute('data-src') + '#t=0.5';   // the first moment of the clip is the thumbnail
            }
        });
        var from = page * PER + 1;
        var to = Math.min(clips.length, from + PER - 1);
        count.textContent = clips.length > PER ? from + '–' + to + ' / ' + clips.length : clips.length + (clips.length === 1 ? ' clip' : ' clips');
    }

    function words(i) {
        var c = clips[i];
        title.textContent = c.title;
        blurb.textContent = c.blurb;
        blurb.hidden = !c.blurb;
        if (c.url) { link.href = c.url; link.textContent = c.label; link.hidden = false; } else { link.hidden = true; }
        overlay.hidden = !c.title && !c.blurb && !c.url;
        items.forEach(function (li) {
            var b = li.querySelector('button');
            var on = Number(b.getAttribute('data-clips-thumb')) === i;
            b.classList.toggle('is-current', on);
            if (on) { b.setAttribute('aria-current', 'true'); } else { b.removeAttribute('aria-current'); }
        });
        if (Math.floor(i / PER) !== page) { showPage(Math.floor(i / PER)); }
    }

    // Loads a clip into the back player, ready to play.
    function load(i) {
        if (backIndex === i) { return; }
        backIndex = i;
        back.preload = 'auto';
        back.muted = muted;
        back.loop = false;
        back.src = clips[i].src;
        back.load();
    }

    function soundLabel() {
        sound.textContent = muted ? sound.getAttribute('data-on') : sound.getAttribute('data-off');
        sound.setAttribute('aria-pressed', muted ? 'false' : 'true');
    }

    // Puts the back player in front (a plain cut) and starts it.
    function swap() {
        var i = backIndex;
        if (i < 0) { return; }
        pendingIndex = -1;
        back.muted = muted;
        back.loop = clips.length === 1;
        var p = back.play();
        if (p && p.catch) { p.catch(function () { /* the browser wants a press on Play first: the player controls are there */ }); }
        var old = front;
        front = back;
        back = old;
        frontIndex = i;
        backIndex = -1;
        front.classList.add('is-front');
        front.setAttribute('controls', '');
        front.removeAttribute('aria-hidden');
        front.tabIndex = 0;
        front.setAttribute('aria-label', clips[i].title);
        old.classList.remove('is-front');
        old.removeAttribute('controls');
        old.setAttribute('aria-hidden', 'true');
        old.tabIndex = -1;
        words(i);
        setTimeout(function () { if (old !== front) { old.pause(); } }, 700);   // when the cut is done
        if (clips.length > 1) { load(wrap(i + 1)); }
        soundLabel();
    }

    // Plays clip i: at once when it is already loaded, else when it can start (the clip on the screen keeps playing meanwhile).
    function play(i) {
        i = wrap(i);
        if (i === frontIndex && !front.ended) { return; }
        pendingIndex = i;
        load(i);
        if (back.readyState >= 3) { swap(); }
    }

    function onReady(e) {
        if (e.target === back && backIndex >= 0 && pendingIndex === backIndex) { swap(); }
    }

    function onTime(e) {
        var v = e.target;
        if (v !== front || clips.length < 2 || !v.duration || backIndex < 0) { return; }
        if (v.duration - v.currentTime <= FADE_AT && back.readyState >= 3 && pendingIndex < 0) { pendingIndex = backIndex; swap(); }
    }

    function onEnded(e) {
        if (e.target !== front || clips.length < 2) { return; }
        if (backIndex < 0) { load(wrap(frontIndex + 1)); }
        pendingIndex = backIndex;
        if (back.readyState >= 3) { swap(); }   // else onReady swaps as soon as it can
    }

    function onError(e) {
        if (clips.length < 2) { return; }
        if (e.target === back && backIndex >= 0) {
            var bad = backIndex;
            backIndex = -1;
            if (pendingIndex === bad) { pendingIndex = -1; }
            setTimeout(function () { if (backIndex < 0) { load(wrap(bad + 1)); } }, 400);
        } else if (e.target === front) {
            setTimeout(function () { play(frontIndex + 1); }, 400);
        }
    }

    // The visitor can also use the volume button of the player itself.
    function onVolume(e) { if (e.target === front && front.muted !== muted) { muted = front.muted; back.muted = muted; soundLabel(); } }

    [first, second].forEach(function (v) {
        v.addEventListener('canplay', onReady);
        v.addEventListener('timeupdate', onTime);
        v.addEventListener('ended', onEnded);
        v.addEventListener('error', onError);
        v.addEventListener('volumechange', onVolume);
    });

    root.querySelector('[data-clips-prev]').addEventListener('click', function () { play(frontIndex - 1); });
    root.querySelector('[data-clips-next]').addEventListener('click', function () { play(frontIndex + 1); });
    root.addEventListener('click', function (e) {
        var t = e.target.closest('[data-clips-thumb]');
        if (t) { play(Number(t.getAttribute('data-clips-thumb'))); }
    });
    var pp = root.querySelector('[data-clips-page-prev]');
    var pn = root.querySelector('[data-clips-page-next]');
    if (pp) { pp.addEventListener('click', function () { showPage(page - 1); }); }
    if (pn) { pn.addEventListener('click', function () { showPage(page + 1); }); }
    sound.addEventListener('click', function () { muted = !muted; front.muted = muted; back.muted = muted; soundLabel(); });

    showPage(0);
    soundLabel();
    // The first clip is loaded in the back player and swapped in, the same way as every other.
    pendingIndex = 0;
    load(0);
    if (back.readyState >= 3) { swap(); }
})();
