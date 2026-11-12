// "Back to top": appears after the page has been scrolled down, and glides to the top (or jumps if the reader prefers less motion).
(function () {
    'use strict';

    var button = document.querySelector('[data-to-top]');
    if (!button) { return; }

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var timer = null;
    var SHOW_AFTER = 500;

    function update() {
        var y = window.pageYOffset || document.documentElement.scrollTop;
        var show = y > SHOW_AFTER;

        if (show) {
            button.hidden = false;
            button.classList.add('is-shown');
            // One moment later, so the fade-in can run.
            requestAnimationFrame(function () { button.classList.add('is-visible'); });
            setTimeout(function () { button.classList.add('is-visible'); }, 30);
        } else {
            button.classList.remove('is-visible');
            setTimeout(function () {
                if (!button.classList.contains('is-visible')) {
                    button.classList.remove('is-shown');
                    button.hidden = true;
                }
            }, 260);
        }
    }

    window.addEventListener('scroll', function () {
        clearTimeout(timer);
        timer = setTimeout(update, 40);
    }, { passive: true });

    button.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
        // The button hides itself at the top, so the next Tab starts from the top of the page.
        button.blur();
    });

    update();
})();
