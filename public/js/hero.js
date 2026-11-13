// The hero banner slider: fades from one picture to the next, with arrows, dots and a pause button.
(function () {
    'use strict';

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function button(text, label, extra) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'hero__btn' + (extra ? ' ' + extra : '');
        b.setAttribute('aria-label', label);
        b.textContent = text;
        return b;
    }

    function setup(box) {
        var slides = Array.prototype.slice.call(box.querySelectorAll('[data-hero-slide]'));
        var controls = box.querySelector('[data-hero-controls]');
        if (slides.length < 2 || !controls) { return; }

        var seconds = reduced ? 0 : (parseInt(box.getAttribute('data-hero-seconds'), 10) || 0);
        var current = 0;
        var timer = null;
        var paused = false;
        var holding = false;
        var dots = [];

        function show(n) {
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
        }

        function stopTimer() { clearInterval(timer); timer = null; }

        function startTimer() {
            stopTimer();
            if (!seconds || paused || holding) { return; }
            timer = setInterval(function () {
                if (!document.hidden) { show(current + 1); }
            }, seconds * 1000);
        }

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

        var playBtn = null;
        if (seconds) {
            playBtn = button('❚❚', box.getAttribute('data-label-pause'), 'hero__btn--play');
            playBtn.addEventListener('click', function () {
                paused = !paused;
                playBtn.textContent = paused ? '▶' : '❚❚';
                playBtn.setAttribute('aria-label', box.getAttribute(paused ? 'data-label-play' : 'data-label-pause'));
                startTimer();
            });
            controls.appendChild(playBtn);
        }

        // Stay still while someone points at it or uses the keyboard on it.
        box.addEventListener('mouseenter', function () { holding = true; stopTimer(); });
        box.addEventListener('mouseleave', function () { holding = false; startTimer(); });
        box.addEventListener('focusin', function () { holding = true; stopTimer(); });
        box.addEventListener('focusout', function () { holding = false; startTimer(); });

        box.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { e.preventDefault(); show(current - 1); startTimer(); }
            if (e.key === 'ArrowRight') { e.preventDefault(); show(current + 1); startTimer(); }
        });

        // A swipe with the finger.
        var startX = null;
        box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
        box.addEventListener('touchend', function (e) {
            if (startX === null) { return; }
            var dx = e.changedTouches[0].clientX - startX;
            startX = null;
            if (Math.abs(dx) > 45) { show(current + (dx < 0 ? 1 : -1)); startTimer(); }
        }, { passive: true });

        controls.hidden = false;
        show(0);
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
