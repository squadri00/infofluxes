/* Home page: faint Arabic letters drift down the background, slowly. Still (no movement) for people who asked for less motion; paused when the tab is hidden. */
(function () {
    var canvas = document.querySelector('[data-letter-rain]');
    if (!canvas || !canvas.getContext) { return; }
    var ctx = canvas.getContext('2d');
    var LETTERS = 'ابتثجحخدذرزسشصضطظعغفقكلمنهوي'.split('');
    var STEP = 46;          // distance between the columns, in pixels
    var TRAIL = 9;          // letters in one falling line
    var SIZE = 26;
    var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var w = 0, h = 0, cols = [], color = '120,130,150', frame = 0, raf = 0;

    function pick() { return LETTERS[Math.floor(Math.random() * LETTERS.length)]; }

    function readColor() {
        var c = getComputedStyle(document.documentElement).getPropertyValue('--text').trim();
        var m = /^#([0-9a-f]{6})$/i.exec(c);
        if (m) { color = parseInt(m[1].slice(0, 2), 16) + ',' + parseInt(m[1].slice(2, 4), 16) + ',' + parseInt(m[1].slice(4, 6), 16); }
    }

    function size() {
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        w = window.innerWidth; h = window.innerHeight;
        canvas.width = w * dpr; canvas.height = h * dpr;
        canvas.style.width = w + 'px'; canvas.style.height = h + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        var n = Math.ceil(w / STEP);
        cols = [];
        for (var i = 0; i < n; i++) {
            var letters = [];
            for (var k = 0; k < TRAIL; k++) { letters.push(pick()); }
            cols.push({ x: i * STEP + STEP / 2, y: Math.random() * (h + TRAIL * SIZE) - TRAIL * SIZE, v: 0.25 + Math.random() * 0.45, letters: letters });
        }
        draw();
    }

    function draw() {
        ctx.clearRect(0, 0, w, h);
        ctx.font = SIZE + 'px "Segoe UI", "Noto Naskh Arabic", "Traditional Arabic", serif';
        ctx.textAlign = 'center';
        for (var i = 0; i < cols.length; i++) {
            var c = cols[i];
            for (var k = 0; k < TRAIL; k++) {
                var y = c.y - k * SIZE * 1.25;
                if (y < -SIZE || y > h + SIZE) { continue; }
                ctx.fillStyle = 'rgba(' + color + ',' + (k === 0 ? 0.2 : 0.14 * (1 - k / TRAIL)).toFixed(3) + ')';
                ctx.fillText(c.letters[k], c.x, y);
            }
        }
    }

    function tick() {
        frame++;
        if (frame % 120 === 1) { readColor(); }
        for (var i = 0; i < cols.length; i++) {
            var c = cols[i];
            c.y += c.v;
            if (c.y - TRAIL * SIZE * 1.25 > h) {
                c.y = -SIZE; c.v = 0.25 + Math.random() * 0.45;
                for (var k = 0; k < TRAIL; k++) { c.letters[k] = pick(); }
            }
            if (frame % 90 === 0) { c.letters[Math.floor(Math.random() * TRAIL)] = pick(); }
        }
        draw();
        raf = requestAnimationFrame(tick);
    }

    function start() { if (!still && !raf) { raf = requestAnimationFrame(tick); } }
    function stop() { if (raf) { cancelAnimationFrame(raf); raf = 0; } }

    readColor();
    size();
    window.addEventListener('resize', size);
    document.addEventListener('visibilitychange', function () { if (document.hidden) { stop(); } else { start(); } });
    window.addEventListener('storage', readColor);
    document.addEventListener('click', function (e) { if (e.target.closest('[data-theme-toggle]')) { setTimeout(function () { readColor(); draw(); }, 50); } });
    start();
})();
