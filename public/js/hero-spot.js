/* Home page, dark mode: the two spotlights over the purple box lean a little towards the pointer (the drifting itself is CSS). */
(function () {
    var box = document.querySelector('.hero:not(.hero--full):not(.hero--media-only) .hero__text');
    if (!box || !window.matchMedia || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    box.addEventListener('pointermove', function (e) {
        if (e.pointerType === 'touch') { return; }
        var r = box.getBoundingClientRect();
        box.style.setProperty('--px', (((e.clientX - r.left) / r.width) * 2 - 1).toFixed(3));
        box.style.setProperty('--py', (((e.clientY - r.top) / r.height) * 2 - 1).toFixed(3));
    });
    box.addEventListener('pointerleave', function () {
        box.style.setProperty('--px', '0');
        box.style.setProperty('--py', '0');
    });
})();
