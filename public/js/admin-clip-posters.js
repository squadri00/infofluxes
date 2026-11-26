/* Clips screen of the admin: makes the still picture of every clip that has none, from its video, in this browser, and sends it to the site. */
(function () {
    var busy = false;
    function onScreen() { return /\/admin\/clips\/?$/.test(location.pathname); }
    function token() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; }

    function make(item) {
        return new Promise(function (resolve) {
            var v = document.createElement('video');
            var done = false;
            function finish(ok) { if (!done) { done = true; v.removeAttribute('src'); v.load(); resolve(ok); } }
            v.muted = true; v.preload = 'auto'; v.playsInline = true;
            v.addEventListener('error', function () { finish(false); });
            v.addEventListener('loadedmetadata', function () { try { v.currentTime = Math.min(1, Math.max(0.1, (v.duration || 2) * 0.1)); } catch (e) { finish(false); } });
            v.addEventListener('seeked', function () {
                var w = Math.min(640, v.videoWidth || 640);
                var h = Math.round(w * (v.videoHeight || 360) / (v.videoWidth || 640));
                var c = document.createElement('canvas'); c.width = w; c.height = h;
                c.getContext('2d').drawImage(v, 0, 0, w, h);
                c.toBlob(function (blob) {
                    if (!blob) { finish(false); return; }
                    var f = new FormData(); f.append('picture', blob, 'poster.jpg');
                    fetch(item.store, { method: 'POST', body: f, headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { finish(r.ok); }).catch(function () { finish(false); });
                }, 'image/jpeg', 0.82);
            });
            setTimeout(function () { finish(false); }, 30000);
            v.src = item.src;
        });
    }

    function run() {
        if (busy || !onScreen()) { return; }
        busy = true;
        fetch('/clip-posters', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (list) { return list.reduce(function (p, item) { return p.then(function () { return make(item); }); }, Promise.resolve()); })
            .catch(function () {})
            .then(function () { busy = false; });
    }
    run();
    setInterval(run, 6000);   // a clip that was just added gets its picture within seconds
})();
