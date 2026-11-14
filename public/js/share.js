/* Share and Copy link under an article. Without this script the Share panel still opens and its links still work. */
(function () {
    'use strict';
    var box = document.querySelector('[data-share]');
    if (!box) { return; }
    var title = box.getAttribute('data-title') || document.title;
    var url = box.getAttribute('data-url') || location.href;
    var summary = box.querySelector('summary');

    // A phone or a browser that can share natively: use its own share sheet instead of the panel.
    if (navigator.share && summary) {
        summary.addEventListener('click', function (e) {
            e.preventDefault();
            navigator.share({ title: title, url: url }).catch(function () { /* the person closed the sheet */ });
        });
    }

    var copy = box.querySelector('[data-share-copy]');
    if (copy && navigator.clipboard && navigator.clipboard.writeText) {
        copy.hidden = false;
        var label = copy.textContent;
        copy.addEventListener('click', function () {
            navigator.clipboard.writeText(url).then(function () {
                copy.textContent = copy.getAttribute('data-done') || label;
                setTimeout(function () { copy.textContent = label; }, 2200);
            });
        });
    }

    // Close the panel when the person clicks somewhere else or presses Escape.
    document.addEventListener('click', function (e) { if (box.open && !box.contains(e.target)) { box.open = false; } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box.open) { box.open = false; } });
})();
