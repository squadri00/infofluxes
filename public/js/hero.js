// The hero banner slider: fades from one picture (or video clip) to the next, with arrows, dots and a pause button.
// A clip plays by itself, without sound, in a loop. It does not play for people who asked their device for less motion
// or for a data saver; then its still picture (or its first moment) is shown. The pause button also pauses the clips.
(function () {
    'use strict';

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var saver = !!(navigator.connection && navigator.connection.saveData);
    var canPlay = !reduced && !saver;

    function button(text, label, extra) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'hero__btn' + (extra ? ' ' + extra : '');
        b.setAttribute('aria-label', label);
        b.textContent = text;
        return b;
    }

    function play(video) {
        var p = video.play();
        if (p && p.catch) { p.catch(function () { /* the browser said no: the still picture stays */ }); }
    }

    function setup(box) {
        var slides = Array.prototype.slice.call(box.querySelectorAll('[data-hero-slide]'));
        var controls = box.querySelector('[data-hero-controls]');
        var hasVideo = !!box.querySelector('[data-hero-video]');

        // Clips that must not play: keep the first moment (or the still picture) and do not download more than needed.
        if (!canPlay) {
            Array.prototype.forEach.call(box.querySelectorAll('[data-hero-video]'), function (v) {
                v.removeAttribute('autoplay');
                v.preload = 'metadata';
                v.pause();
            });
        }

        if (!controls || (slides.length < 2 && !hasVideo)) { return; }

        var seconds = reduced ? 0 : (parseInt(box.getAttribute('data-hero-seconds'), 10) || 0);
        var current = 0;
        var timer = null;
        var paused = false;
        var holding = false;
        var dots = [];

        // The clip of the slide that is showing plays; every other clip is stopped (so it does not use the processor or the data).
        function syncVideos() {
            slides.forEach(function (slide, i) {
                var v = slide.querySelector('[data-hero-video]');
                if (!v) { return; }
                if (i === current && canPlay && !paused && !document.hidden) {
                    if (v.preload === 'none') { v.preload = 'auto'; }
                    play(v);
                } else {
                    v.pause();
                }
            });
        }

        function show(n, restart) {
            var before = current;
            current = (n + slides.length) % slides.length;
            slides.forEach(function (slide, i) {
                var on = i === current;
                slide.classList.toggle('is-active', on);
                slide.setAttribute('aria-hidden', on ? 'false' : 'true');
                var link = slide.querySelector('a');
                if (link) { link.tabIndex = on ? 0 : -1; }
                var img = slide.querySelector('img');
                if (img && on) { img.loading = 'eager'; }
            });
            dots.forEach(function (d, i) {
                d.classList.toggle('is-current', i === current);
                d.setAttribute('aria-current', i === current ? 'true' : 'false');
            });
            // A clip starts again from its beginning each time its slide comes back.
            if (current !== before || restart) {
                var v = slides[current].querySelector('[data-hero-video]');
                if (v) { try { v.currentTime = 0; } catch (e) { /* not ready yet */ } }
            }
            syncVideos();
        }

        function stopTimer() { clearInterval(timer); timer = null; }

        function startTimer() {
            stopTimer();
            if (!seconds || paused || holding) { return; }
            timer = setInterval(function () {
                if (!document.hidden) { show(current + 1); }
            }, seconds * 1000);
        }

        if (slides.length > 1) {
            var prev = button('‹', box.getAttribute('data-label-prev'), 'hero__btn--arrow');
            var next = button('›', box.getAttribute('data-label-next'), 'hero__btn--arrow');
            var strip = document.createElement('span');
            strip.className = 'hero__dots';

            slides.forEach(function (slide, i) {
                var d = button('', box.getAttribute('data-label-goto').replace(':n', i + 1), 'hero__dot');
                d.addEventListener('click', function () { show(i); startTimer(); });
                strip.appendChild(d);
                dots.push(d);
            });

            prev.addEventListener('click', function () { show(current - 1); startTimer(); });
            next.addEventListener('click', function () { show(current + 1); startTimer(); });

            controls.appendChild(prev);
            controls.appendChild(strip);
            controls.appendChild(next);
        }

        var playBtn = null;
        if (seconds || (hasVideo && canPlay)) {
            playBtn = button('❚❚', box.getAttribute('data-label-pause'), 'hero__btn--play');
            playBtn.addEventListener('click', function () {
                paused = !paused;
                playBtn.textContent = paused ? '▶' : '❚❚';
                playBtn.setAttribute('aria-label', box.getAttribute(paused ? 'data-label-play' : 'data-label-pause'));
                startTimer();
                syncVideos();
            });
            controls.appendChild(playBtn);
        }

        // Stay still while someone points at it or uses the keyboard on it (the clips keep playing).
        box.addEventListener('mouseenter', function () { holding = true; stopTimer(); });
        box.addEventListener('mouseleave', function () { holding = false; startTimer(); });
        box.addEventListener('focusin', function () { holding = true; stopTimer(); });
        box.addEventListener('focusout', function () { holding = false; startTimer(); });

        box.addEventListener('keydown', function (e) {
            if (slides.length < 2) { return; }
            if (e.key === 'ArrowLeft') { e.preventDefault(); show(current - 1); startTimer(); }
            if (e.key === 'ArrowRight') { e.preventDefault(); show(current + 1); startTimer(); }
        });

        // A swipe with the finger.
        var startX = null;
        box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
        box.addEventListener('touchend', function (e) {
            if (startX === null || slides.length < 2) { return; }
            var dx = e.changedTouches[0].clientX - startX;
            startX = null;
            if (Math.abs(dx) > 45) { show(current + (dx < 0 ? 1 : -1)); startTimer(); }
        }, { passive: true });

        // A clip stops while the page is in another tab, and goes on when it comes back.
        document.addEventListener('visibilitychange', syncVideos);

        controls.hidden = false;
        show(0, true);
        startTimer();
    }

    function init() {
        document.querySelectorAll('[data-hero]').forEach(setup);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
