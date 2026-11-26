/* Clips page: one big player that plays the clips one after the other, and six thumbnails (a page of six at a time). */
(function () {
    var root = document.querySelector('[data-clips]');
    var dataEl = document.getElementById('clips-data');
    if (!root || !dataEl) { return; }
    var clips;
    try { clips = JSON.parse(dataEl.textContent); } catch (e) { return; }
    if (!clips.length) { return; }

    var PER = 6;
    var video = root.querySelector('.clips__video');
    var title = root.querySelector('[data-clips-title]');
    var blurb = root.querySelector('[data-clips-blurb]');
    var link = root.querySelector('[data-clips-link]');
    var overlay = root.querySelector('[data-clips-overlay]');
    var sound = root.querySelector('[data-clips-sound]');
    var count = root.querySelector('[data-clips-count]');
    var items = [].slice.call(root.querySelectorAll('[data-clips-item]'));
    var current = 0;
    var page = 0;
    var pages = Math.ceil(clips.length / PER);

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

    function play(i, userMade) {
        current = (i + clips.length) % clips.length;
        var c = clips[current];
        video.src = c.src;
        video.loop = clips.length === 1;
        video.setAttribute('aria-label', c.title);
        title.textContent = c.title;
        blurb.textContent = c.blurb;
        blurb.hidden = !c.blurb;
        if (c.url) { link.href = c.url; link.textContent = c.label; link.hidden = false; } else { link.hidden = true; }
        overlay.hidden = !c.title && !c.blurb && !c.url;
        items.forEach(function (li) {
            var b = li.querySelector('button');
            var on = Number(b.getAttribute('data-clips-thumb')) === current;
            b.classList.toggle('is-current', on);
            if (on) { b.setAttribute('aria-current', 'true'); } else { b.removeAttribute('aria-current'); }
        });
        if (Math.floor(current / PER) !== page) { showPage(Math.floor(current / PER)); }
        var p = video.play();
        if (p && p.catch) { p.catch(function () { /* the browser wants a press on Play first: the player controls are there */ }); }
    }

    video.addEventListener('ended', function () { if (clips.length > 1) { play(current + 1); } });
    video.addEventListener('error', function () { if (clips.length > 1) { setTimeout(function () { play(current + 1); }, 600); } });
    root.querySelector('[data-clips-prev]').addEventListener('click', function () { play(current - 1, true); });
    root.querySelector('[data-clips-next]').addEventListener('click', function () { play(current + 1, true); });
    root.addEventListener('click', function (e) {
        var t = e.target.closest('[data-clips-thumb]');
        if (t) { play(Number(t.getAttribute('data-clips-thumb')), true); }
    });
    var pp = root.querySelector('[data-clips-page-prev]');
    var pn = root.querySelector('[data-clips-page-next]');
    if (pp) { pp.addEventListener('click', function () { showPage(page - 1); }); }
    if (pn) { pn.addEventListener('click', function () { showPage(page + 1); }); }

    function soundLabel() {
        sound.textContent = video.muted ? sound.getAttribute('data-on') : sound.getAttribute('data-off');
        sound.setAttribute('aria-pressed', video.muted ? 'false' : 'true');
    }
    sound.addEventListener('click', function () { video.muted = !video.muted; soundLabel(); });
    video.addEventListener('volumechange', soundLabel);

    showPage(0);
    soundLabel();
    play(0);
})();
