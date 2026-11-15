/* The header for phones: the Menu button opens and closes the menu under the bar.
   Without this script the button stays hidden and the menu is shown open under the bar, so nothing is lost. */
(function () {
    'use strict';
    var header = document.querySelector('[data-m-header]');
    if (!header) { return; }
    var button = header.querySelector('[data-m-toggle]');
    var drawer = header.querySelector('[data-m-drawer]');
    if (!button || !drawer) { return; }

    header.classList.add('m-header--js');
    button.hidden = false;

    function set(open) {
        header.classList.toggle('is-open', open);
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    button.addEventListener('click', function () { set(!header.classList.contains('is-open')); });

    document.addEventListener('click', function (event) {
        if (header.classList.contains('is-open') && !header.contains(event.target)) { set(false); }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && header.classList.contains('is-open')) { set(false); button.focus(); }
    });
    // A link inside the menu leaves the page; the menu closes for pages that stay (a link to a part of this page).
    drawer.addEventListener('click', function (event) {
        var a = event.target.closest ? event.target.closest('a[href^="#"]') : null;
        if (a) { set(false); }
    });
    window.addEventListener('resize', function () { if (window.innerWidth > 760) { set(false); } });
})();
