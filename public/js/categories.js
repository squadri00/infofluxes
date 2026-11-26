/* Browse all topics: the cards are packed in columns without gaps (masonry): each card goes to the shortest column, in the order of the menu.
   On a phone the lists of sub-categories start closed; Open all / Close all work on every card and pack the cards again. */
(function () {
    'use strict';
    var root = document.querySelector('[data-topics]');
    if (!root) { return; }
    var GAP = 18;       // the same as the gap in the CSS
    var MIN = 340;      // the narrowest a card gets (as in the CSS grid)
    var cards = Array.prototype.slice.call(root.children);
    var columns = 0;

    var all = function () { return root.querySelectorAll('[data-topics-details]'); };
    var set = function (open) {
        Array.prototype.forEach.call(all(), function (d) { if (open) { d.setAttribute('open', ''); } else { d.removeAttribute('open'); } });
    };

    function count() {
        var w = root.getBoundingClientRect().width || root.parentNode.getBoundingClientRect().width;
        return Math.max(1, Math.floor((w + GAP) / (MIN + GAP)));
    }

    function pack(force) {
        var n = count();
        if (!force && n === columns) { return; }
        columns = n;
        root.classList.add('is-masonry');
        // Every card back in the grid first, so the old columns can go.
        cards.forEach(function (c) { root.appendChild(c); });
        Array.prototype.slice.call(root.querySelectorAll('.topics-col')).forEach(function (col) { root.removeChild(col); });
        var cols = [];
        for (var i = 0; i < n; i++) {
            var col = document.createElement('div');
            col.className = 'topics-col';
            root.appendChild(col);
            cols.push(col);
        }
        cards.forEach(function (card) {
            var shortest = cols[0];
            cols.forEach(function (col) { if (col.offsetHeight < shortest.offsetHeight) { shortest = col; } });
            shortest.appendChild(card);
        });
    }

    if (window.matchMedia && window.matchMedia('(max-width: 700px)').matches) { set(false); }
    pack(true);

    var o = document.querySelector('[data-topics-open]');
    var c = document.querySelector('[data-topics-close]');
    if (o) { o.addEventListener('click', function () { set(true); pack(true); }); }
    if (c) { c.addEventListener('click', function () { set(false); pack(true); }); }

    var timer = 0;
    window.addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(function () { pack(false); }, 150); });
    window.addEventListener('load', function () { pack(true); });   // the fonts and pictures are in: pack once more
})();
