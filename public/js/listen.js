/*
 * Listen: reads a topic aloud, from the start to the end, like telling a story.
 * For each part it uses the best voice there is:
 *   1. an approved audio file (one for the whole topic, or one for each slide), if there is one;
 *   2. otherwise the voice of the visitor's own device (free, no account).
 * The text is cut into short pieces (sentences), because long texts are cut off by some browsers. The slide viewer follows the reading.
 * What is used is set in Settings > Listen.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-listen]');
    if (!root) { return; }

    var cfg = {};
    try { cfg = JSON.parse(root.getAttribute('data-listen') || '{}'); } catch (e) { cfg = {}; }
    var labels = cfg.labels || {};
    var tracks = cfg.audio || { topic: null, slides: {} };
    var slideAudio = tracks.slides || {};
    var fullSlides = tracks.full || [];          // slides whose audio already says the cover words and the chapter name
    var useAudio = cfg.source !== 'device';
    var audioOnly = cfg.source === 'audio';

    var playBtn = root.querySelector('[data-listen-play]');
    var pauseBtn = root.querySelector('[data-listen-pause]');
    var stopBtn = root.querySelector('[data-listen-stop]');
    var prevBtn = root.querySelector('[data-listen-prev]');
    var nextBtn = root.querySelector('[data-listen-next]');
    var speedSel = root.querySelector('[data-listen-speed]');
    var voiceSel = root.querySelector('[data-listen-voice]');
    var voiceBox = root.querySelector('.listen__voice');
    var statusEl = root.querySelector('[data-listen-status]');

    function say(text) { if (statusEl) { statusEl.textContent = text || ''; } }

    var hasSynth = ('speechSynthesis' in window) && typeof window.SpeechSynthesisUtterance !== 'undefined';
    var hasAudioFiles = !!(tracks.topic || tracks.cover || Object.keys(slideAudio).length);

    // ---------- nothing can speak: say so and stop
    if (!hasSynth && !(useAudio && hasAudioFiles)) {
        root.hidden = false;
        root.querySelector('.listen__buttons').hidden = true;
        say(labels.unsupported || '');
        return;
    }
    root.hidden = false;
    if (!hasSynth && voiceBox) { voiceBox.hidden = true; }

    var synth = hasSynth ? window.speechSynthesis : null;
    var voices = [];
    var chosenVoice = null;
    var rate = cfg.rate || 0.95;
    var chunks = [];
    var pos = 0;
    var token = 0;            // a new number every time the reading starts or stops, so late events of an old reading are ignored
    var playing = false;
    var paused = false;
    var lastSlide = null;
    var timer = null;
    var player = null;        // one audio element, made when the button is pressed and used again for every file
    var playingAudio = false;
    var selfMove = false;     // true while this script moves the viewer, so that a click of the reader can be told apart

    function store(key, value) { try { window.localStorage.setItem('listen_' + key, value); } catch (e) { /* not available */ } }
    function stored(key) { try { return window.localStorage.getItem('listen_' + key); } catch (e) { return null; } }

    // ---------- the voice of the device
    function langPrefix() { return String(cfg.lang || document.documentElement.lang || 'en').toLowerCase().split('-')[0]; }

    function rank(v) {
        var n = (v.name || '') + ' ' + (v.voiceURI || '');
        if (/natural|neural/i.test(n)) { return 0; }
        if (/online/i.test(n)) { return 1; }
        if (/google/i.test(n)) { return 2; }
        if (/premium|enhanced|siri/i.test(n)) { return 3; }
        return v.localService ? 5 : 4;
    }

    function bestVoice() {
        var list = voices.filter(function (v) { return String(v.lang || '').toLowerCase().indexOf(langPrefix()) === 0; });
        if (!list.length) { list = voices.slice(); }
        var wanted = cfg.voices || [];
        for (var i = 0; i < wanted.length; i++) {
            var w = String(wanted[i]).toLowerCase();
            var hit = list.filter(function (v) { return ((v.name || '') + ' ' + (v.voiceURI || '')).toLowerCase().indexOf(w) !== -1; });
            if (hit.length) { return hit.sort(function (a, b) { return rank(a) - rank(b); })[0]; }
        }
        return list.sort(function (a, b) { return rank(a) - rank(b); })[0] || null;
    }

    function fillVoices() {
        if (!synth) { return; }
        voices = synth.getVoices() || [];
        if (!voices.length) { return; }
        var mine = voices.filter(function (v) { return String(v.lang || '').toLowerCase().indexOf(langPrefix()) === 0; });
        var show = (mine.length ? mine : voices).slice().sort(function (a, b) { return rank(a) - rank(b); });
        var saved = stored('voice');
        chosenVoice = null;
        voices.forEach(function (v) { if (saved && v.name === saved) { chosenVoice = v; } });
        if (!chosenVoice) { chosenVoice = bestVoice(); }
        if (voiceSel) {
            voiceSel.innerHTML = '';
            show.forEach(function (v) {
                var o = document.createElement('option');
                o.value = v.name;
                o.textContent = v.name.replace(/^Microsoft\s+/, '') + (rank(v) <= 1 ? ' ★' : '');
                if (chosenVoice && v.name === chosenVoice.name) { o.selected = true; }
                voiceSel.appendChild(o);
            });
        }
    }
    /** The list of device voices matters only for a part that has no audio file (or when a file cannot be played). */
    function needsDeviceVoice() {
        if (!hasSynth) { return false; }
        if (!useAudio || !hasAudioFiles) { return true; }
        if (tracks.topic) { return false; }
        return collect().some(function (item) { return !item.audio; });
    }
    function updateVoiceBox() { if (voiceBox) { voiceBox.hidden = !needsDeviceVoice(); } }

    fillVoices();
    if (synth && typeof synth.onvoiceschanged !== 'undefined') { synth.onvoiceschanged = fillVoices; }

    if (speedSel) {
        var savedRate = parseFloat(stored('rate'));
        if (savedRate > 0.5 && savedRate < 2) { rate = savedRate; }
        // the nearest speed in the list
        var best = null;
        Array.prototype.forEach.call(speedSel.options, function (o) {
            if (best === null || Math.abs(parseFloat(o.value) - rate) < Math.abs(parseFloat(best.value) - rate)) { best = o; }
        });
        if (best) { best.selected = true; }
        speedSel.addEventListener('change', function () {
            rate = parseFloat(speedSel.value) || 1;
            store('rate', rate);
            if (playingAudio && player) { player.playbackRate = rate; return; }
            if (playing && !paused) { restartHere(); }
        });
    }
    if (voiceSel) {
        voiceSel.addEventListener('change', function () {
            voices.forEach(function (v) { if (v.name === voiceSel.value) { chosenVoice = v; } });
            store('voice', voiceSel.value);
            if (playing && !paused && !playingAudio) { restartHere(); }
        });
    }

    // ---------- the text: what is said, and how
    function speakable(text) {
        var t = String(text || '');
        (cfg.replace || []).forEach(function (pair) { t = t.split(pair[0]).join(' ' + pair[1] + ' '); });
        t = t.replace(/ /g, ' ').replace(/[‎‏]/g, '').replace(/@/g, '').replace(/\s+/g, ' ').replace(/\s+([,.;:!?])/g, '$1').trim();

        return t;
    }

    /** Cuts a text into pieces of about 200 characters, at the end of a sentence where possible. */
    function pieces(text) {
        var sentences = text.match(/[^.!?…]+[.!?…]+["'”’)\]]*\s*|[^.!?…]+$/g) || [text];
        var out = [];
        var cur = '';
        sentences.forEach(function (s) {
            s = s.trim();
            if (!s) { return; }
            if (s.length > 240) {
                // a very long sentence: cut it at a comma, a semicolon or a space
                var parts = s.replace(/([,;:])\s+/g, '$1\u0001').split('\u0001');
                parts.forEach(function (p) {
                    while (p.length > 240) {
                        var cut = p.lastIndexOf(' ', 220);
                        if (cut < 60) { cut = 220; }
                        if (cur) { out.push(cur); cur = ''; }
                        out.push(p.slice(0, cut).trim());
                        p = p.slice(cut).trim();
                    }
                    if ((cur + ' ' + p).trim().length > 200) { if (cur) { out.push(cur); } cur = p; } else { cur = (cur + ' ' + p).trim(); }
                });
                return;
            }
            if ((cur + ' ' + s).trim().length > 200) { if (cur) { out.push(cur); } cur = s; } else { cur = (cur + ' ' + s).trim(); }
        });
        if (cur) { out.push(cur); }

        return out;
    }

    /** The text of an element without the small number tag (#14) and without hidden parts. */
    function textOf(el) {
        if (!el) { return ''; }
        var copy = el.cloneNode(true);
        Array.prototype.forEach.call(copy.querySelectorAll('.id-tag, [data-id-tag], [hidden], .visually-hidden, script, style'), function (n) { n.parentNode.removeChild(n); });

        return copy.textContent || '';
    }

    function endSentence(t) { t = t.trim(); return t && !/[.!?…]$/.test(t) ? t + '.' : t; }

    /** The list of things to say, each with the number of its slide (or null) and, when there is one, the audio file. */
    function collect() {
        var items = [];
        var story = document.querySelector('[data-story]');
        var slides = story ? Array.prototype.slice.call(story.querySelectorAll('.slide')) : [];

        if (slides.length) {
            var chapterTitles = {};
            Array.prototype.forEach.call(story.querySelectorAll('[data-story-chapters] [data-chapter]'), function (b) { chapterTitles[b.getAttribute('data-chapter')] = b.textContent.trim(); });
            var lastChapter = null;
            // older audio has the cover words inside the audio of the first slide: then the cover is not read again
            var coverInAudio = useAudio && !tracks.cover && !!tracks.coverInFirst;

            slides.forEach(function (slide, i) {
                var chapter = slide.getAttribute('data-chapter');
                var heading = slide.querySelector('.slide__heading');
                var file = useAudio ? slideAudio[i] : null;

                if (slide.querySelector('.slide__heading--cover')) {
                    var parts = [textOf(heading), textOf(slide.querySelector('.slide__period')), textOf(slide.querySelector('.slide__lead'))];
                    var coverWords = parts.map(endSentence).join(' ');
                    if (useAudio && tracks.cover) { items.push({ slide: i, audio: tracks.cover, text: coverWords }); return; }      // the cover has its own audio
                    if (coverInAudio) { return; }
                    if (audioOnly) { return; }
                    items.push({ slide: i, text: coverWords });
                    return;
                }
                if (slide.querySelector('.slide__debates')) { return; }

                var announce = cfg.chapters && chapter !== null && chapter !== '-1' && chapter !== lastChapter && chapterTitles[chapter];
                lastChapter = chapter;
                var inAudio = file && fullSlides.indexOf(i) !== -1;
                if (announce && !inAudio && !(file && audioOnly)) {
                    items.push({ slide: i, text: (labels.chapter || 'Chapter') + ': ' + endSentence(chapterTitles[chapter]) });
                }
                var spoken = endSentence(textOf(heading)) + ' ' + textOf(slide.querySelector('.slide__body'));
                if (file) {
                    items.push({ slide: i, audio: file, text: spoken });         // the text is kept in case the file cannot be played
                } else if (!audioOnly) {
                    items.push({ slide: i, text: spoken });
                }
            });

            return items;
        }

        // a topic without slides: the question, the hook and the context
        var title = document.querySelector('.topic h1');
        if (title) { items.push({ slide: null, text: endSentence(textOf(title)) }); }
        Array.prototype.forEach.call(document.querySelectorAll('.topic__hook, .topic__context, .article__body, .fact-answer__text, .fact-method__text'), function (el) {
            items.push({ slide: null, text: textOf(el) });
        });

        return items;
    }

    function chunksOfText(item) {
        var out = [];
        var t = speakable(item.text);
        if (!t) { return out; }
        pieces(t).forEach(function (p, n) { out.push({ slide: item.slide, text: p, first: n === 0 }); });

        return out;
    }

    function build() {
        chunks = [];
        if (useAudio && tracks.topic) {
            // one file for the whole topic: it plays on its own
            chunks.push({ slide: null, audio: tracks.topic, first: true, whole: true, fallback: collect() });
        } else {
            collect().forEach(function (item) {
                if (item.audio) { chunks.push({ slide: item.slide, audio: item.audio, first: true, fallback: [item] }); return; }
                chunks = chunks.concat(chunksOfText(item));
            });
        }
        pos = 0;
        lastSlide = null;
    }

    // ---------- the reading
    function setState(state) {
        playing = state !== 'stopped';
        paused = state === 'paused';
        if (pauseBtn) { pauseBtn.hidden = !playing; pauseBtn.textContent = paused ? (pauseBtn.getAttribute('data-resume') || 'Resume') : (pauseBtn.getAttribute('data-pause') || 'Pause'); }
        if (stopBtn) { stopBtn.hidden = !playing; }
        if (prevBtn) { prevBtn.hidden = !playing; }
        if (nextBtn) { nextBtn.hidden = !playing; }
        if (playBtn) { playBtn.hidden = playing; }
    }

    function showSlide(i) {
        if (!cfg.follow || i === null || i === lastSlide) { return false; }
        var changed = lastSlide !== null;
        lastSlide = i;
        selfMove = true;
        document.dispatchEvent(new CustomEvent('story:goto', { detail: { index: i } }));
        selfMove = false;
        var total = document.querySelectorAll('[data-story] .slide').length;
        say((labels.reading || '').replace(':n', i + 1).replace(':total', total));

        return changed;
    }

    function finish() {
        token++;
        playingAudio = false;
        setState('stopped');
        say(labels.done || '');
        lastSlide = null;
    }

    /** An audio file that cannot be played is replaced by the voice of the device, for that part only. */
    function fallBack(mine) {
        if (mine !== token) { return; }
        playingAudio = false;
        var chunk = chunks[pos];
        var replacement = [];
        (chunk.fallback || []).forEach(function (item) { replacement = replacement.concat(chunksOfText(item)); });
        if (!hasSynth || !replacement.length) { pos++; next(); return; }
        chunks.splice.apply(chunks, [pos, 1].concat(replacement));
        next();
    }

    function playFile(chunk, mine) {
        playingAudio = true;
        if (!player) { player = new window.Audio(); player.preload = 'auto'; }
        player.onended = function () { if (mine === token) { playingAudio = false; pos++; next(); } };
        player.onerror = function () { fallBack(mine); };
        player.src = chunk.audio;
        player.playbackRate = rate;
        var p = player.play();
        if (p && typeof p.catch === 'function') { p.catch(function () { fallBack(mine); }); }
    }

    function speakChunk(chunk, mine) {
        playingAudio = false;
        if (!synth) { pos++; next(); return; }
        var u = new window.SpeechSynthesisUtterance(chunk.text);
        if (chosenVoice) { u.voice = chosenVoice; u.lang = chosenVoice.lang; } else { u.lang = cfg.lang || 'en'; }
        u.rate = rate;
        u.onend = function () { if (mine === token) { pos++; next(); } };
        u.onerror = function (e) {
            if (mine !== token) { return; }
            if (e && (e.error === 'interrupted' || e.error === 'canceled')) { return; }
            pos++; next();
        };
        synth.speak(u);
    }

    function next() {
        var mine = token;
        if (!playing || mine !== token) { return; }
        if (pos >= chunks.length) { finish(); return; }

        var chunk = chunks[pos];
        var slideChanged = showSlide(chunk.slide);
        var wait = slideChanged && chunk.first && cfg.pause ? cfg.pause : 0;

        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
            if (mine !== token || !playing) { return; }
            if (chunk.audio) { playFile(chunk, mine); } else { speakChunk(chunk, mine); }
        }, wait);
    }

    function start() {
        if (synth) { synth.cancel(); }
        if (useAudio && hasAudioFiles && !player) { player = new window.Audio(); }      // made now, while the button is being pressed (phones need that)
        build();
        if (!chunks.length) { return; }
        token++;
        setState('playing');
        next();
    }

    /** Starts again from the piece that is being read (after a change of speed or voice). */
    function restartHere() {
        token++;
        if (synth) { synth.cancel(); }
        setState('playing');
        next();
    }

    /** Goes on from another piece (a slide that was clicked, or Back and Forward). */
    function jumpTo(newPos) {
        token++;
        window.clearTimeout(timer);
        if (synth) { synth.cancel(); }
        if (player) { try { player.pause(); } catch (e) { /* nothing */ } }
        playingAudio = false;
        pos = Math.max(0, Math.min(chunks.length - 1, newPos));
        lastSlide = null;
        setState('playing');
        next();
    }

    function slideStart(from) {
        var s = chunks[from] ? chunks[from].slide : null;
        var i = from;
        while (i > 0 && chunks[i - 1].slide === s) { i--; }
        return i;
    }

    function slideEnd(from) {
        var s = chunks[from] ? chunks[from].slide : null;
        var i = from;
        while (i < chunks.length - 1 && chunks[i + 1].slide === s) { i++; }
        return i;
    }

    /** Back and Forward. One long file: 15 seconds. Otherwise a slide at a time (Back first goes to the start of the slide). */
    function skip(direction) {
        if (!playing || !chunks.length) { return; }
        var chunk = chunks[pos];
        if (chunk && chunk.whole && player) {
            var t = (player.currentTime || 0) + direction * 15;
            player.currentTime = Math.max(0, isFinite(player.duration) ? Math.min(player.duration, t) : t);
            return;
        }
        if (direction > 0) {
            var end = slideEnd(pos);
            if (end >= chunks.length - 1) { finish(); return; }
            jumpTo(end + 1);
            return;
        }
        var start = slideStart(pos);
        var late = pos > start || (playingAudio && player && player.currentTime > 3);
        if (late || start === 0) { jumpTo(start); return; }
        jumpTo(slideStart(start - 1));
    }

    /** The reader clicked a slide in the viewer: the voice goes on from that slide. */
    document.addEventListener('story:change', function (e) {
        if (!playing || selfMove || !cfg.follow || tracks.topic) { return; }
        var index = e && e.detail ? e.detail.index : null;
        if (index === null || index === lastSlide) { return; }
        var target = -1;
        for (var i = 0; i < chunks.length; i++) { if (chunks[i].slide !== null && chunks[i].slide >= index) { target = i; break; } }
        if (target === -1) { return; }
        jumpTo(slideStart(target));
    });

    function stop() {
        token++;
        window.clearTimeout(timer);
        if (synth) { synth.cancel(); }
        if (player) { try { player.pause(); } catch (e) { /* nothing */ } }
        playingAudio = false;
        setState('stopped');
        say('');
        lastSlide = null;
    }

    if (playBtn) { playBtn.addEventListener('click', start); }
    if (stopBtn) { stopBtn.addEventListener('click', stop); }
    if (prevBtn) { prevBtn.addEventListener('click', function () { skip(-1); }); }
    if (nextBtn) { nextBtn.addEventListener('click', function () { skip(1); }); }
    if (pauseBtn) {
        pauseBtn.addEventListener('click', function () {
            if (!playing) { return; }
            if (paused) {
                if (playingAudio && player) { player.play(); } else if (synth) { synth.resume(); }
                setState('playing');
            } else {
                if (playingAudio && player) { player.pause(); } else if (synth) { synth.pause(); }
                setState('paused');
            }
        });
    }

    // never keep talking after the page is left
    window.addEventListener('pagehide', function () { if (synth) { synth.cancel(); } if (player) { try { player.pause(); } catch (e) { /* nothing */ } } });
    window.addEventListener('beforeunload', function () { if (synth) { synth.cancel(); } });

    setState('stopped');
    // the story viewer builds its slides before this script is ready to read them
    window.setTimeout(updateVoiceBox, 0);

    // for tests and for other scripts
    window.InfoFluxesListen = { speakable: speakable, pieces: pieces };
})();
