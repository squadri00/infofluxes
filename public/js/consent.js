/* Cookie notice. Counting (Google Tag Manager / Analytics) starts only after the visitor says OK.
   The choice is kept in the cookie "if_consent" (all or necessary) for about 6 months. */
(function () {
    var banner = document.querySelector('[data-consent-banner]');
    if (!banner) { return; }

    function read() {
        var m = document.cookie.match(/(?:^|; )if_consent=([^;]*)/);
        return m ? decodeURIComponent(m[1]) : null;
    }
    function write(value) {
        document.cookie = 'if_consent=' + value + '; max-age=15552000; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    }
    function clearCounting() {
        document.cookie.split('; ').forEach(function (c) {
            var name = c.split('=')[0];
            if (/^(_ga|_gid|_gat|_gcl)/.test(name)) {
                document.cookie = name + '=; max-age=0; path=/';
                document.cookie = name + '=; max-age=0; path=/; domain=' + location.hostname;
                document.cookie = name + '=; max-age=0; path=/; domain=.' + location.hostname.replace(/^www\./, '');
            }
        });
    }

    var started = false;
    function startCounting() {
        if (started) { return; }
        started = true;
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({ event: 'consent_update', analytics_consent: 'granted' });
        document.querySelectorAll('script[data-consent="analytics"]').forEach(function (el) {
            var s = document.createElement('script');
            if (el.getAttribute('data-src')) { s.src = el.getAttribute('data-src'); s.async = true; } else { s.text = el.text; }
            el.parentNode.insertBefore(s, el);
            el.parentNode.removeChild(el);
        });
    }

    function show() { banner.hidden = false; }
    function hide() { banner.hidden = true; }

    var choice = read();
    if (choice === 'all') { startCounting(); } else if (choice === null) { show(); }

    banner.querySelector('[data-consent-accept]').addEventListener('click', function () {
        write('all');
        hide();
        startCounting();
    });
    banner.querySelector('[data-consent-reject]').addEventListener('click', function () {
        var before = read();
        write('necessary');
        hide();
        if (before === 'all' || started) { clearCounting(); location.reload(); }
    });
    document.querySelectorAll('[data-consent-open]').forEach(function (b) {
        b.addEventListener('click', show);
    });
})();
