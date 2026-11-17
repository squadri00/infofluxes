/*
 * Visual Stories: one slide at a time.
 * The page already holds every slide in order, so without this script the story is simply a long page.
 * With it: one slide shows, with Previous / Next / Next chapter, dots, chapter buttons, the arrow keys, a swipe,
 * and the slide number in the web address (#slide-4). A button that cannot be used looks muted
 * (aria-disabled), it is never "disabled".
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-story]');
    if (!root) { return; }

    // A wide viewer inside a narrow page (a topic) is as wide as the window without its scroll bar
    function pageWidth() { document.documentElement.style.setProperty('--page-w', document.documentElement.clientWidth + 'px'); }
    pageWidth();
    window.addEventListener('resize', pageWidth);

    var stage = root.querySelector('[data-story-stage]');
    var track = root.querySelector('[data-story-track]');
    var slides = Array.prototype.slice.call(root.querySelectorAll('.slide'));
    if (!stage || slides.length === 0) { return; }

    var controls = root.querySelector('[data-story-controls]');
    var prev = root.querySelector('[data-story-prev]');
    var next = root.querySelector('[data-story-next]');
    var nextChapter = root.querySelector('[data-story-next-chapter]');
    var arrowPrev = root.querySelector('[data-story-arrow-prev]');
    var arrowNext = root.querySelector('[data-story-arrow-next]');
    var dotsBox = root.querySelector('[data-story-dots]');
    var counter = root.querySelector('[data-story-current]');
    var chaptersNav = root.querySelector('[data-story-chapters]');
    var homeBtn = root.querySelector('[data-story-home]');
    var menuBtn = root.querySelector('[data-story-menu-btn]');
    var menuList = root.querySelector('[data-story-menu-list]');
    var menuLabel = root.querySelector('[data-story-menu-label]');
    var hint = root.querySelector('[data-story-hint]');
    var labels = window.STORY_LABELS || { goto: 'Go to slide :n' };

    // The choices made in the admin (Settings > Slide viewer), read from the viewer element
    var box = root.querySelector('[data-viewer]') || root;
    var cfg = {
        autoplay: parseInt(box.getAttribute('data-autoplay') || '0', 10),
        pauseHover: box.getAttribute('data-pause-hover') !== '0',
        loop: box.getAttribute('data-loop') === '1',
        keys: box.getAttribute('data-keys') !== '0',
        swipe: box.getAttribute('data-swipe') !== '0',
        fade: box.getAttribute('data-transition') === 'fade' || box.getAttribute('data-transition') === 'book',   // the book shows one spread at a time, like the fade
        book: box.getAttribute('data-transition') === 'book'
    };

    var total = slides.length;
    var current = 0;
    var ready = false;

    // ---------- Spreads ----------
    // A spread is what is on the two pages at the same time. In the book, two text-only slides that follow each other share ONE spread:
    // the first on the left page (the page where a picture would be), the next on the right page. A slide with a picture is a whole spread.
    // In every other viewer a spread is simply one slide.
    // The page-turning helper (StPageFlip) gives the book a real page turn: a curl, shadows, and a corner that can be dragged. Without it the book uses the page turn below.
    var St = window.St && window.St.PageFlip ? window.St : null;

    var spreads = [];          // a list of lists of slide numbers
    var spreadOf = [];         // slide number => spread number
    var totalEl = root.querySelector('[data-story-total]');
    function pageNumbers() { return cfg.book && window.innerWidth >= 720; }      // page numbers (two for every spread) only in the book on a wide screen

    function isPageSlide(s) { return s.classList.contains('slide--text') && !!s.querySelector(':scope > .slide__text > .slide__body'); }

    function layout() {
        var paged = cfg.book && window.innerWidth >= 720 && !St;      // with the page-turning helper the pages are made by fbPages() instead
        spreads = [];
        spreadOf = [];
        slides.forEach(function (s) { s.removeAttribute('data-page'); });
        var i = 0;
        while (i < total) {
            var pair = [i];
            if (paged && isPageSlide(slides[i])) {
                slides[i].setAttribute('data-page', 'left');
                if (i + 1 < total && isPageSlide(slides[i + 1])) {      // the next text slide goes on the right page, even when a new chapter starts there
                    pair.push(i + 1);
                    slides[i + 1].setAttribute('data-page', 'right');
                }
            }
            spreads.push(pair);
            pair.forEach(function (k) { spreadOf[k] = spreads.length - 1; });
            i += pair.length;
        }
        // The book numbers its PAGES, two for every spread: "3 · 4 / 40". The other viewers count slides: "2 / 20".
        if (totalEl) { totalEl.textContent = String(pageNumbers() ? spreads.length * 2 : total); }
    }
    layout();

    root.classList.add('story--js');
    if (controls) { controls.hidden = false; }
    if (arrowPrev) { arrowPrev.hidden = false; }
    if (arrowNext) { arrowNext.hidden = false; }
    if (chaptersNav) { chaptersNav.hidden = false; }
    if (hint) { hint.hidden = false; }

    // The dots, one for each slide
    var dots = slides.map(function (slide, i) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'story__dot';
        b.setAttribute('aria-label', labels.goto.replace(':n', i + 1));
        b.addEventListener('click', function () { show(i, true); });
        if (dotsBox) { dotsBox.appendChild(b); }
        return b;
    });

    if (homeBtn) { homeBtn.addEventListener('click', function () { show(0, true); }); }

    // The "Chapters" menu (used for many chapters or long names)
    function menuOpen(open) {
        if (!menuList || !menuBtn) { return; }
        menuList.hidden = !open;
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (menuBtn && menuList) {
        menuBtn.addEventListener('click', function (e) { e.stopPropagation(); menuOpen(menuList.hidden); });
        document.addEventListener('click', function (e) { if (!menuList.hidden && !menuList.contains(e.target)) { menuOpen(false); } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menuList.hidden) { menuOpen(false); menuBtn.focus(); } });
    }

    var chapterButtons = chaptersNav ? Array.prototype.slice.call(chaptersNav.querySelectorAll('[data-chapter]')) : [];
    chapterButtons.forEach(function (b) {
        b.addEventListener('click', function () {
            var c = b.getAttribute('data-chapter');
            for (var i = 0; i < slides.length; i++) {
                if (slides[i].getAttribute('data-chapter') === c) { show(i, true); return; }
            }
        });
    });

    function chapterOf(i) { return slides[i].getAttribute('data-chapter'); }

    function muted(button, yes) {
        if (!button) { return; }
        button.classList.toggle('is-muted', yes);
        if (yes) { button.setAttribute('aria-disabled', 'true'); } else { button.removeAttribute('aria-disabled'); }
    }

    // ---------- The book: a page turns over like the page of an open book ----------
    // A slide is a spread (the picture is the left page, the words are the right page). When the reader moves on, a "leaf" lifts from the spine:
    // its front is the page that was being read, its back is the first page of the next spread. Under it the new spread is already in place.
    // Copies of the slides are used for the leaf, so the real slides (and Listen, the keys, the dots) are never touched.
    var flipEl = null;
    var flipTimer = null;

    function canFlip() {
        var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        return cfg.book && ready && window.innerWidth >= 720 && !calm;
    }

    function endFlip() {
        if (flipTimer) { clearTimeout(flipTimer); flipTimer = null; }
        if (flipEl && flipEl.parentNode) { flipEl.parentNode.removeChild(flipEl); }
        flipEl = null;
    }

    // One page: the left or the right half of copies of the slides of a spread (one slide, or two text slides side by side).
    function pageOf(spread, side, W, H, cls) {
        var wrap = document.createElement('div');
        wrap.className = cls;
        var inner = document.createElement('div');
        inner.className = 'book-page__inner story__track';
        inner.style.width = W + 'px';
        inner.style.height = H + 'px';
        if (side === 'right') { inner.style.marginLeft = (-W / 2) + 'px'; }
        spreads[spread].forEach(function (k) {
            var copy = slides[k].cloneNode(true);
            copy.removeAttribute('id');
            copy.classList.add('is-active', 'book-clone');
            copy.setAttribute('aria-hidden', 'true');
            Array.prototype.forEach.call(copy.querySelectorAll('[id]'), function (n) { n.removeAttribute('id'); });
            Array.prototype.forEach.call(copy.querySelectorAll('a, button'), function (n) { n.tabIndex = -1; });
            inner.appendChild(copy);
        });
        wrap.appendChild(inner);
        return wrap;
    }

    function flip(from, to) {      // from and to are numbers of SPREADS
        endFlip();
        var a = from;
        var b = to;
        var W = (track || stage).offsetWidth;
        var H = (track || stage).offsetHeight;
        if (!W || !H) { return; }
        var speed = parseFloat(getComputedStyle(box).getPropertyValue('--v-speed')) || 550;
        var dur = Math.max(650, Math.min(1500, Math.round(speed * 1.5)));
        var forward = to > from;

        var overlay = document.createElement('div');
        overlay.className = 'book-flip';
        overlay.setAttribute('aria-hidden', 'true');
        // Forward: the right page lifts and lands on the left. Backward: the left page lifts and lands on the right.
        var under = pageOf(a, forward ? 'left' : 'right', W, H, 'book-under book-under--' + (forward ? 'left' : 'right'));
        var leaf = document.createElement('div');
        leaf.className = 'book-leaf book-leaf--' + (forward ? 'forward' : 'backward');
        leaf.style.transitionDuration = dur + 'ms';
        leaf.appendChild(pageOf(a, forward ? 'right' : 'left', W, H, 'book-face book-face--front'));
        leaf.appendChild(pageOf(b, forward ? 'left' : 'right', W, H, 'book-face book-face--back'));
        overlay.appendChild(under);
        overlay.appendChild(leaf);
        stage.appendChild(overlay);
        flipEl = overlay;

        void leaf.offsetWidth;   // start from where the leaf lies now
        leaf.style.transform = 'rotateY(' + (forward ? -180 : 180) + 'deg)';
        flipTimer = setTimeout(endFlip, dur + 80);
    }

    // ---------- The flipbook (StPageFlip) ----------
    // The slides stay in the page (hidden): Listen and the other scripts read them. The book is made of COPIES of their parts, as single pages:
    // a slide with a picture is two pages (the picture on the left, the words on the right), a text-only slide is one page.
    // A blank page is added when a picture would land on a right page. The pages of the book are numbered from the first one.
    var fb = null;             // the StPageFlip object
    var fbBox = null;          // the element it lives in
    var fbBar = null;          // the row under the book: Previous, the page number, Next, Fullscreen
    var fbBarPage = null;
    var fbFsBtn = null;
    var fbPageSlide = [];      // page number => slide number
    var fbSlidePage = [];      // slide number => its first page number

    function fbWanted() { return cfg.book && !!St && window.innerWidth >= 720; }

    function fbCopy(node) {
        var copy = node.cloneNode(true);
        copy.removeAttribute('id');
        Array.prototype.forEach.call(copy.querySelectorAll('[id]'), function (n) { n.removeAttribute('id'); });
        return copy;
    }

    function fbPage(kind, content) {
        var page = document.createElement('div');
        page.className = 'fb-page fb-page--' + kind;
        var inner = document.createElement('div');
        inner.className = 'fb-page__in';
        if (content) { inner.appendChild(content); }
        page.appendChild(inner);
        return page;
    }

    function fbPages() {
        var pages = [];
        fbPageSlide = [];
        fbSlidePage = [];
        slides.forEach(function (s, k) {
            var media = s.querySelector(':scope > .slide__media');
            var text = s.querySelector(':scope > .slide__text');
            if (media && pages.length % 2 === 1) { pages.push(fbPage('blank')); fbPageSlide.push(Math.max(k - 1, 0)); }
            fbSlidePage[k] = pages.length;
            if (media) { pages.push(fbPage('pic', fbCopy(media))); fbPageSlide.push(k); }
            if (text) { pages.push(fbPage('text', fbCopy(text))); fbPageSlide.push(k); }
        });
        return pages;
    }

    function fbSize() {
        var full = !!document.fullscreenElement;
        return {
            W: Math.max(stage.clientWidth || 0, 320),
            H: full ? Math.round(window.innerHeight * 0.8) : Math.round(Math.max(540, Math.min(700, window.innerHeight * 0.64)))
        };
    }

    function fbSpreadLeft(slide) { var p = fbSlidePage[slide] || 0; return p - (p % 2); }

    function fbShown(left) {
        var set = [];
        [left, left + 1].forEach(function (p) {
            if (p < fbPageSlide.length && set.indexOf(fbPageSlide[p]) < 0) { set.push(fbPageSlide[p]); }
        });
        return set;
    }

    // The book was turned (by Next, by the dots, or by a finger or mouse dragging a corner): the rest of the viewer follows.
    function fbSync(page) {
        var left = page - (page % 2);
        if (fbShown(left).indexOf(current) >= 0) { return; }       // the viewer already shows these slides
        var s = fbPageSlide[left];
        if (s !== undefined) { show(s, true); }
    }

    function fbButton(text, fn) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn--small fb-bar__btn';
        b.textContent = text;
        b.addEventListener('click', fn);
        return b;
    }

    function fbBuildBar() {
        fbBar = document.createElement('div');
        fbBar.className = 'fb-bar';
        fbBarPage = document.createElement('span');
        fbBarPage.className = 'fb-bar__page';
        fbBar.appendChild(fbButton('‹ ' + (labels.prev || 'Previous'), function () { go(-1); }));
        fbBar.appendChild(fbBarPage);
        fbBar.appendChild(fbButton((labels.next || 'Next') + ' ›', function () { go(1); }));
        if (root.requestFullscreen) {
            fbFsBtn = fbButton(labels.fullscreen || 'Fullscreen', function () {
                if (document.fullscreenElement) { document.exitFullscreen(); } else { root.requestFullscreen(); }
            });
            fbBar.appendChild(fbFsBtn);
        }
        box.appendChild(fbBar);
    }

    function fbBuild(startSlide) {
        var size = fbSize();
        var pages = fbPages();
        root.classList.add('story--fb');
        fbBox = document.createElement('div');
        fbBox.className = 'fb';
        fbBox.style.height = size.H + 'px';
        stage.appendChild(fbBox);
        fb = new St.PageFlip(fbBox, {
            width: Math.round(size.W / 2), height: size.H, size: 'stretch',
            minWidth: 280, maxWidth: 1800, minHeight: 300, maxHeight: 1400,
            showCover: false, usePortrait: false, startPage: fbSpreadLeft(startSlide),
            drawShadow: true, maxShadowOpacity: 0.45, flippingTime: 850,
            showPageCorners: true, mobileScrollSupport: true, swipeDistance: 30,
            useMouseEvents: true, clickEventForward: true
        });
        fb.loadFromHTML(pages);
        fb.on('flip', function (e) { fbSync(e.data); });
        fbBuildBar();
        if (totalEl) { totalEl.textContent = String(fb.getPageCount()); }
    }

    function fbDestroy() {
        if (fb) { try { fb.destroy(); } catch (e) { /* already gone */ } fb = null; }
        if (fbBox && fbBox.parentNode) { fbBox.parentNode.removeChild(fbBox); }
        if (fbBar && fbBar.parentNode) { fbBar.parentNode.removeChild(fbBar); }
        fbBox = null;
        fbBar = null;
        fbBarPage = null;
        fbFsBtn = null;
        root.classList.remove('story--fb');
    }

    function fbResize() {
        if (!fb || !fbBox) { return; }
        fbBox.style.height = fbSize().H + 'px';
        try { fb.update(); } catch (e) { /* the next resize will do */ }
    }

    function show(i, writeHash) {
        if (i < 0 || i >= total) { return; }
        var fromSpread = spreadOf[current];
        current = i;
        var spreadNow = spreadOf[i];
        var shown = spreads[spreadNow];       // the slides that are on the two pages now (one, or two text slides in the book)
        if (fb) {
            // the flipbook: the spread is the pair of pages the slide starts on
            var left = fbSpreadLeft(i);
            spreadNow = left / 2;
            shown = fbShown(left);
            if (fb.getCurrentPageIndex() !== left) {
                if (ready) { fb.flip(left); } else { fb.turnToPage(left); }
            }
        }

        // The row of slides glides sideways to the chosen one. The first time it is placed without gliding.
        if (track) {
            track.classList.toggle('is-instant', !ready);
            track.style.transform = cfg.fade ? '' : 'translateX(' + (-100 * i) + '%)';
        }
        slides.forEach(function (s, n) {
            var on = shown.indexOf(n) >= 0;
            s.classList.toggle('is-active', on);
            s.setAttribute('aria-hidden', on ? 'false' : 'true');
            if ('inert' in s) { s.inert = !on; }                    // a slide that is out of sight cannot be reached with the keyboard
            if (on && ready && fromSpread !== spreadNow) {
                var text = s.querySelector('.slide__text');
                if (text) { text.scrollTop = 0; }
            }
        });
        if (!fb && fromSpread !== spreadNow && canFlip()) { flip(fromSpread, spreadNow); }
        dots.forEach(function (d, n) {
            d.classList.toggle('is-active', shown.indexOf(n) >= 0);
            if (n === i) { d.setAttribute('aria-current', 'true'); } else { d.removeAttribute('aria-current'); }
        });
        chapterButtons.forEach(function (b) {
            var on = b.getAttribute('data-chapter') === chapterOf(i);
            b.classList.toggle('is-active', on);
            if (on) { b.setAttribute('aria-current', 'true'); } else { b.removeAttribute('aria-current'); }
        });
        if (menuBtn && menuLabel) {
            // the menu button shows the chapter the reader is in (or "Chapters" on the cover)
            var inChapter = chapterButtons.filter(function (b) { return b.getAttribute('data-chapter') === chapterOf(i); })[0];
            menuLabel.textContent = inChapter ? inChapter.textContent.trim() : (menuBtn.getAttribute('data-default') || '');
            menuBtn.classList.toggle('is-active', !!inChapter);
            menuOpen(false);
        }
        if (homeBtn) {
            homeBtn.classList.toggle('is-active', i === 0);
            if (i === 0) { homeBtn.setAttribute('aria-current', 'true'); } else { homeBtn.removeAttribute('aria-current'); }
        }
        if (counter) { counter.textContent = pageNumbers() ? (2 * spreadNow + 1) + ' · ' + (2 * spreadNow + 2) : String(i + 1); }

        if (fbBarPage && counter) { fbBarPage.textContent = counter.textContent + ' / ' + (totalEl ? totalEl.textContent : ''); }
        var firstSpread = spreadNow === 0;
        var lastSpread = spreadNow === (fb ? Math.ceil(fb.getPageCount() / 2) - 1 : spreads.length - 1);
        muted(prev, !cfg.loop && firstSpread);
        muted(next, !cfg.loop && lastSpread);
        muted(arrowPrev, !cfg.loop && firstSpread);
        muted(arrowNext, !cfg.loop && lastSpread);
        muted(nextChapter, !hasNextChapter());

        if (writeHash && window.history && history.replaceState) {
            history.replaceState(null, '', '#slide-' + (i + 1));
        }
        document.dispatchEvent(new CustomEvent('story:change', { detail: { index: i } }));
    }

    // Other scripts (Listen) can move the viewer
    document.addEventListener('story:goto', function (e) {
        var n = e && e.detail ? parseInt(e.detail.index, 10) : -1;
        if (n >= 0 && n < total && n !== current) { show(n, true); }
    });

    function hasNextChapter() {
        var c = chapterOf(current);
        for (var i = current + 1; i < total; i++) { if (chapterOf(i) !== c) { return true; } }
        return false;
    }

    // Next and Previous move from spread to spread (in the book that is two text slides at a time).
    function go(step) {
        if (fb) { if (step > 0) { fb.flipNext(); } else { fb.flipPrev(); } return; }     // the flipbook turns one spread
        var n = spreadOf[current] + step;
        if (cfg.loop) { n = (n + spreads.length) % spreads.length; }
        if (n >= 0 && n < spreads.length) { show(spreads[n][0], true); }
    }

    function goNextChapter() {
        var c = chapterOf(current);
        for (var i = current + 1; i < total; i++) { if (chapterOf(i) !== c) { show(i, true); return; } }
    }

    if (prev) { prev.addEventListener('click', function () { go(-1); }); }
    if (next) { next.addEventListener('click', function () { go(1); }); }
    if (arrowPrev) { arrowPrev.addEventListener('click', function () { go(-1); }); }
    if (arrowNext) { arrowNext.addEventListener('click', function () { go(1); }); }
    if (nextChapter) { nextChapter.addEventListener('click', goNextChapter); }

    // The arrow keys (not while someone is typing, and not with Ctrl, Alt or Cmd)
    document.addEventListener('keydown', function (e) {
        if (!cfg.keys || e.defaultPrevented || e.ctrlKey || e.altKey || e.metaKey || e.shiftKey) { return; }
        var t = e.target;
        if (t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable)) { return; }
        if (e.key === 'ArrowRight') { go(1); } else if (e.key === 'ArrowLeft') { go(-1); }
    });

    // A swipe on a phone
    var startX = 0, startY = 0, tracking = false;
    stage.addEventListener('touchstart', function (e) {
        if (!cfg.swipe || e.touches.length !== 1) { tracking = false; return; }
        tracking = true;
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
    }, { passive: true });
    stage.addEventListener('touchend', function (e) {
        if (fb) { tracking = false; return; }           // the flipbook handles the finger itself
        if (!tracking || !e.changedTouches.length) { return; }
        tracking = false;
        var dx = e.changedTouches[0].clientX - startX;
        var dy = e.changedTouches[0].clientY - startY;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) { go(dx < 0 ? 1 : -1); }
    }, { passive: true });

    // The slide number in the address: opening #slide-4 opens that slide
    function fromHash() {
        var m = /^#slide-(\d+)$/.exec(window.location.hash || '');
        return m ? Math.min(Math.max(parseInt(m[1], 10) - 1, 0), total - 1) : null;
    }
    window.addEventListener('hashchange', function () {
        var i = fromHash();
        if (i !== null && i !== current) { show(i, false); }
    });

    // Links inside the slides (the list of chapters on the cover) glide to a slide
    root.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('[data-story-goto]') : null;
        if (!a) { return; }
        e.preventDefault();
        show(parseInt(a.getAttribute('data-story-goto'), 10) - 1, true);
    });

    // Pictures cannot be dragged away or saved with a right click or a long press
    ['contextmenu', 'dragstart'].forEach(function (name) {
        root.addEventListener(name, function (e) {
            if (e.target.closest && e.target.closest('.slide__media.is-protected')) { e.preventDefault(); }
        });
    });

    var first = fromHash();
    var firstSlide = first === null ? 0 : first;
    if (fbWanted()) {
        try { fbBuild(firstSlide); } catch (err) { fbDestroy(); St = null; layout(); }       // if the helper fails, the book uses its own page turn
    }
    show(firstSlide, false);
    // From now on a change glides (unless the person asked their device for less movement: the style then turns it off).
    window.setTimeout(function () { ready = true; if (track) { track.classList.remove('is-instant'); } }, 80);

    // The book is made again when the window crosses the width of a phone, and the flipbook is fitted to a window that changes size.
    var wasWide = window.innerWidth >= 720;
    var fitTimer = null;
    window.addEventListener('resize', function () {
        var wide = window.innerWidth >= 720;
        if (wide !== wasWide) {
            wasWide = wide;
            var wasReady = ready;
            ready = false;               // no page turn for this change
            endFlip();
            fbDestroy();
            layout();
            if (fbWanted()) {
                try { fbBuild(current); } catch (err) { fbDestroy(); St = null; layout(); }
            }
            show(current, false);
            ready = wasReady;
            return;
        }
        if (fb) { window.clearTimeout(fitTimer); fitTimer = window.setTimeout(fbResize, 120); }
    });
    // Fullscreen changes the size of the book
    document.addEventListener('fullscreenchange', function () {
        if (fbFsBtn) { fbFsBtn.textContent = document.fullscreenElement ? (labels.fullscreen_exit || 'Exit fullscreen') : (labels.fullscreen || 'Fullscreen'); }
        window.setTimeout(fbResize, 150);
    });

    // Moving by itself (off unless a number of seconds is set; never for people who asked for less movement)
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (cfg.autoplay > 0 && !reduced && total > 1) {
        var paused = false;
        if (cfg.pauseHover) {
            ['mouseenter', 'focusin'].forEach(function (n) { box.addEventListener(n, function () { paused = true; }); });
            ['mouseleave', 'focusout'].forEach(function (n) { box.addEventListener(n, function () { paused = false; }); });
        }
        var timer = window.setInterval(function () {
            if (paused || document.hidden) { return; }
            if (current >= total - 1 && !cfg.loop) { window.clearInterval(timer); return; }
            go(1);
        }, cfg.autoplay * 1000);
    }
})();
