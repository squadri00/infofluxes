/* Browse all topics: on a phone the lists of sub-categories start closed; Open all / Close all work on every card. */
(function () {
    'use strict';
    var root = document.querySelector('[data-topics]');
    if (!root) { return; }
    var all = function () { return root.querySelectorAll('[data-topics-details]'); };
    var set = function (open) {
        Array.prototype.forEach.call(all(), function (d) { if (open) { d.setAttribute('open', ''); } else { d.removeAttribute('open'); } });
    };
    if (window.matchMedia && window.matchMedia('(max-width: 700px)').matches) { set(false); }
    var o = document.querySelector('[data-topics-open]');
    var c = document.querySelector('[data-topics-close]');
    if (o) { o.addEventListener('click', function () { set(true); }); }
    if (c) { c.addEventListener('click', function () { set(false); }); }
})();
