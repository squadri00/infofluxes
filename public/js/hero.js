// The hero banner slider: fades from one picture (or video clip) to the next, with arrows, dots and a pause button.
// A clip plays by itself, without sound, to its end, and then the next slide comes (a visitor can turn the sound on with the sound button). It does not play for people who asked their device for less motion
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

    // onRefused: called when the browser refuses to play a clip WITH sound (so the clip can go on without sound).
    function play(video, onRefused) {
        var p = video.play();
        if (p && p.catch) {
            p.catch(function () {
                if (!video.muted && onRefused) {
                    onRefused();
                    var again = video.play();
                    if (again && again.catch) { again.catch(function () { /* the still picture stays */ }); }
                }
                /* otherwise the browser said no: the still picture stays */
            });
        }
    }

    var ICON_OFF = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M4 9v6h4l5 4V5L8 9H4z" fill="currentColor"/><path d="M16 9l5 6M21 9l-5 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>';
    var ICON_ON = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M4 9v6h4l5 4V5L8 9H4z" fill="currentColor"/><path d="M16 8.5a5 5 0 0 1 0 7M18.5 6a8.5 8.5 0 0 1 0 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>';

    function setup(box) {
        var slides = Array.prototype.slice.call(box.querySelectorAll('[data-hero-slide]'));
        var controls = box.querySelector('[data-hero-controls]');
        var hasVideo = !!box.querySelector('[data-hero-video]');

        // A clip plays to its END; then the banner moves on to the next slide. Only when the banner has a single slide does the clip loop.
        var single = slides.length < 2;
        Array.prototype.forEach.call(box.querySelectorAll('[data-hero-video]'), function (v) { v.loop = single; });

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
        var sound = false;          // clips start without sound; a visitor can turn it on with the sound button
        var soundBtn = null;

        function isVideo(i) { return !!slides[i].querySelector('[data-hero-video]'); }

        // A slide whose clip really plays. Its length decides how long it stays; a picture (or a clip that may not play) uses the seconds.
        function playsClip(i) { return isVideo(i) && canPlay; }

        function paintSound() {
            if (!soundBtn) { return; }
            soundBtn.hidden = !isVideo(current);   // the button is only there while a clip is showing
            soundBtn.innerHTML = sound ? ICON_ON : ICON_OFF;
            var label = box.getAttribute(sound ? 'data-label-sound-off' : 'data-label-sound-on');
            soundBtn.setAttribute('aria-label', label);
            soundBtn.setAttribute('title', label);
            soundBtn.setAttribute('aria-pressed', sound ? 'true' : 'false');
        }

        // The clip of the slide that is showing plays; every other clip is stopped (so it does not use the processor or the data).
        function syncVideos() {
            slides.forEach(function (slide, i) {
                var v = slide.querySelector('[data-hero-video]');
                if (!v) { return; }
                v.muted = !(sound && i === current);
                if (i === current && canPlay && !paused && !document.hidden) {
                    if (v.preload === 'none') { v.preload = 'auto'; }
                    play(v, function () { sound = false; v.muted = true; paintSound(); });
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
            paintSound();
            startTimer();
        }

        function stopTimer() { clearTimeout(timer); timer = null; }

        // After every change of slide: a picture waits for its seconds, then the next slide comes.
        // A clip needs no timer: its own end moves the banner on (see the "ended" listeners below).
        function startTimer() {
            stopTimer();
            if (!seconds || paused || holding || playsClip(current)) { return; }
            timer = setTimeout(function () {
                if (document.hidden) { startTimer(); } else { show(current + 1); }
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

        // The sound button: a clip plays without sound until a visitor asks for it (browsers only allow sound after a click).
        if (hasVideo && canPlay && box.getAttribute('data-label-sound-on')) {
            soundBtn = button('', box.getAttribute('data-label-sound-on'), 'hero__btn--sound');
            soundBtn.addEventListener('click', function () {
                sound = !sound;
                syncVideos();
                paintSound();
            });
            controls.insertBefore(soundBtn, controls.firstChild);
        }

        // A clip that ends moves the banner on to the next slide. (A clip that cannot load is skipped too.)
        if (!single) {
            slides.forEach(function (slide, i) {
                var v = slide.querySelector('[data-hero-video]');
                if (!v) { return; }
                function next() { if (i === current && !paused) { show(current + 1); } }
                v.addEventListener('ended', next);
                var src = v.querySelector('source');
                if (src) { src.addEventListener('error', function () { setTimeout(next, 800); }); }
            });

            // If the clip on show stops moving for 10 seconds (a slow or broken file), the banner does not wait for ever.
            var lastTime = -1;
            var lastMove = Date.now();
            setInterval(function () {
                var v = (!paused && !document.hidden && playsClip(current)) ? slides[current].querySelector('[data-hero-video]') : null;
                if (!v) { lastTime = -1; return; }
                if (v.currentTime !== lastTime || v.ended) { lastTime = v.currentTime; lastMove = Date.now(); return; }
                if (Date.now() - lastMove > 10000) { lastMove = Date.now(); lastTime = -1; show(current + 1); }
            }, 2000);
        }

        // The banner waits only while someone uses the KEYBOARD on it (and the pause button stops it for everyone).
        // A mouse resting on the banner, a touch on a phone, or a click on a button must NOT hold it: that made the banner seem stuck.
        box.addEventListener('focusin', function (e) {
            var keyboard = true;
            try { keyboard = e.target.matches(':focus-visible'); } catch (err) { /* older browser: treat as keyboard */ }
            if (keyboard) { holding = true; stopTimer(); }
        });
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
