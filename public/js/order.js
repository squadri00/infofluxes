// "Put the chapters in order": drag each title into its place.
//  - Test (Easy, Hard): put them all in place, then check. Scored.
//  - Practice: every title is checked the moment you place it. Right = green and it stays; wrong = red and it goes back. Not scored.
// Works with a mouse, a finger, and the keyboard (Enter on a title, then Enter on a place).
// The right order is never in the page: the server checks it. Text is always inserted as text, never as HTML.
(function () {
    'use strict';

    var root = document.querySelector('[data-order]');
    if (!root) { return; }

    var words = {};
    try { words = JSON.parse(root.getAttribute('data-words') || '{}'); } catch (e) { words = {}; }

    var intro = root.querySelector('[data-order-intro]');
    var stage = root.querySelector('[data-order-stage]');

    var round = null;        // what the server sent
    var placement = [];      // for each place, the tile put there (or null)
    var confirmed = {};      // Practice: place -> the chapter, once the server said it is right
    var flash = {};          // Practice: place -> 'wrong' for a moment
    var tries = 0;           // Practice: how many titles were tried
    var busy = false;        // Practice: waiting for the server
    var selected = null;     // the tile picked up by tap or keyboard
    var locked = false;
    var drag = null;
    var suppressClick = false;

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function call(url, body) {
        var options = { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin' };
        if (body) {
            options.method = 'POST';
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
        return fetch(url, options).then(function (response) {
            if (!response.ok) { throw new Error('bad'); }
            return response.json();
        });
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined && text !== null) { node.textContent = text; }
        return node;
    }

    function fill(template, values) {
        return Object.keys(values).reduce(function (text, key) { return text.split(':' + key).join(values[key]); }, template || '');
    }

    function clear() { while (stage.firstChild) { stage.removeChild(stage.firstChild); } }

    function fail() {
        locked = true;
        busy = false;
        clear();
        stage.appendChild(el('p', 'notice notice--error', words.error));
        stage.appendChild(levelButtons());
        showIndexTab(true);
    }

    // ---------- the chapter list at the left edge ----------

    var tab = root.querySelector('[data-chapters-open]');
    var panel = root.querySelector('[data-chapters-panel]');
    var backdrop = root.querySelector('[data-chapters-backdrop]');
    var search = root.querySelector('[data-chapters-search]');
    var listItems = root.querySelectorAll('.chapters-list__item');
    var none = root.querySelector('[data-chapters-none]');

    function openIndex() {
        if (!panel) { return; }
        panel.removeAttribute('inert');
        panel.setAttribute('aria-hidden', 'false');
        panel.classList.add('is-open');
        backdrop.hidden = false;
        tab.setAttribute('aria-expanded', 'true');
        var close = panel.querySelector('.chapters-panel__close');
        if (close) { close.focus(); }
    }

    function closeIndex(returnFocus) {
        if (!panel || !panel.classList.contains('is-open')) { return; }
        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
        panel.setAttribute('inert', '');
        backdrop.hidden = true;
        tab.setAttribute('aria-expanded', 'false');
        if (returnFocus !== false && !tab.hidden) { tab.focus(); }
    }

    // The list is a help for learning: it is put away while a scored round is on, so it cannot be copied from.
    function showIndexTab(on) {
        if (!tab) { return; }
        if (!on) { closeIndex(false); }
        tab.hidden = !on;
    }

    function markRound(numbers) {
        listItems.forEach(function (li) {
            li.classList.toggle('is-in-round', numbers.indexOf(Number(li.getAttribute('data-number'))) !== -1);
        });
    }

    if (tab) {
        showIndexTab(true);
        tab.addEventListener('click', openIndex);
        root.querySelectorAll('[data-chapters-close]').forEach(function (b) { b.addEventListener('click', function () { closeIndex(); }); });
        backdrop.addEventListener('click', function () { closeIndex(); });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { closeIndex(); } });

        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            var shown = 0;
            listItems.forEach(function (li) {
                var match = q === '' || li.getAttribute('data-find').indexOf(q) !== -1;
                li.hidden = !match;
                if (match) { shown++; }
            });
            none.hidden = shown !== 0;
        });
    }

    // ---------- starting a round ----------

    function levelButtons() {
        var size = root.getAttribute('data-size');
        var box = el('div', 'order__levels');
        [['easy', false], ['hard', false], ['easy', true], ['hard', true]].forEach(function (pair) {
            var key = pair[1] ? 'practice_' + pair[0] : pair[0];
            var b = el('button', pair[1] ? 'btn' : 'btn btn--primary', fill(words[key] || key, { n: size }));
            b.type = 'button';
            b.addEventListener('click', function () { begin(pair[0], pair[1]); });
            box.appendChild(b);
        });

        return box;
    }

    function begin(level, practice) {
        intro.hidden = true;
        stage.hidden = false;
        locked = false;
        busy = false;
        selected = null;
        confirmed = {};
        flash = {};
        tries = 0;
        clear();
        stage.appendChild(el('p', 'quiz__loading', words.loading));
        showIndexTab(!!practice);          // a scored round hides (and closes) the list

        call(root.getAttribute('data-start-url') + '?level=' + encodeURIComponent(level) + '&practice=' + (practice ? '1' : '0')).then(function (data) {
            round = data;
            placement = [];
            for (var i = 0; i < data.size; i++) { placement.push(null); }
            markRound([]);
            render();
        }).catch(fail);
    }

    root.querySelectorAll('[data-order-level]').forEach(function (b) {
        b.addEventListener('click', function () { begin(b.getAttribute('data-order-level'), b.hasAttribute('data-order-practice')); });
    });

    // ---------- drawing the round ----------

    function tileElement(tile, state, chapter) {
        var b = el('button', 'order-tile');
        b.type = 'button';
        b.setAttribute('data-tile', String(tile.id));
        b.setAttribute('aria-pressed', selected === tile.id ? 'true' : 'false');
        if (selected === tile.id) { b.classList.add('is-selected'); }
        if (state === 'right') { b.classList.add('is-correct'); b.setAttribute('data-locked', ''); }
        if (state === 'wrong') { b.classList.add('is-wrong'); b.setAttribute('data-locked', ''); }

        var ar = el('span', 'order-tile__ar', tile.arabic);
        ar.setAttribute('lang', 'ar');
        ar.setAttribute('dir', 'rtl');
        b.appendChild(ar);
        b.appendChild(el('span', 'order-tile__name', tile.name));
        b.appendChild(el('span', 'order-tile__meaning', tile.meaning));

        if (state) {
            // Colour is not the only sign: a mark and words for screen readers too.
            b.appendChild(el('span', 'order-tile__mark', state === 'right' ? '✓' : '✗'));
            var sr = el('span', 'visually-hidden', state === 'right' ? words.right_place : words.wrong_place);
            b.appendChild(sr);
        }
        if (state === 'right' && chapter) {
            b.appendChild(el('span', 'order-tile__num', fill(words.number, { n: chapter.number }) + ' · ' + fill(words.verses, { n: chapter.verses })));
        }

        return b;
    }

    function tileById(id) { return round.tiles[id]; }

    function slotLabel(k) {
        if (round.slots[k] !== null && round.slots[k] !== undefined) { return String(round.slots[k]); }
        if (k === 0) { return '1 - ' + words.first; }
        if (k === round.size - 1) { return String(k + 1) + ' - ' + words.last; }

        return String(k + 1);
    }

    function placedCount() { return Object.keys(confirmed).length; }

    function render() {
        clear();

        var instruction = round.level === 'easy'
            ? fill(words.range, { from: round.from, to: round.to })
            : fill(words.range_hard, { n: round.size });
        stage.appendChild(el('p', 'order__instruction', instruction));
        stage.appendChild(el('p', 'quiz__fine order__tap', round.practice ? words.practice_help : words.tap));

        if (round.practice) {
            var status = el('p', 'order__status');
            status.setAttribute('role', 'status');
            status.appendChild(el('span', 'order__tries', fill(words.tries, { n: tries })));
            status.appendChild(el('span', 'order__progress', fill(words.placed, { n: placedCount(), total: round.size })));
            stage.appendChild(status);
        }

        var cols = el('div', 'order__board');

        // The titles still to place
        var poolBox = el('div', 'order__col');
        poolBox.appendChild(el('h3', 'order__heading', words.pool));
        var pool = el('div', 'order-pool');
        pool.setAttribute('data-pool', '');
        round.tiles.forEach(function (tile) {
            if (placement.indexOf(tile.id) === -1) { pool.appendChild(tileElement(tile)); }
        });
        poolBox.appendChild(pool);
        cols.appendChild(poolBox);

        // The places
        var slotBox = el('div', 'order__col');
        slotBox.appendChild(el('h3', 'order__heading', words.slots));
        var list = el('ol', 'order-slots');
        placement.forEach(function (tileId, k) {
            var li = el('li', 'order-slot');
            li.appendChild(el('span', 'order-slot__label', slotLabel(k)));
            var zone = el('div', 'order-slot__drop');
            zone.setAttribute('data-slot', String(k));
            zone.setAttribute('tabindex', '0');
            zone.setAttribute('role', 'group');
            zone.setAttribute('aria-label', fill(words.place, { n: round.slots[k] !== null && round.slots[k] !== undefined ? round.slots[k] : k + 1 }));
            if (tileId === null) {
                zone.appendChild(el('span', 'order-slot__empty', words.empty));
            } else if (round.practice) {
                // Right, wrong, or still waiting for the answer (no colour yet).
                var state = confirmed[k] ? 'right' : (flash[k] || null);
                if (state) { zone.classList.add(state === 'right' ? 'is-correct' : 'is-wrong'); }
                zone.appendChild(tileElement(tileById(tileId), state, confirmed[k]));
            } else {
                zone.appendChild(tileElement(tileById(tileId)));
            }
            li.appendChild(zone);
            list.appendChild(li);
        });
        slotBox.appendChild(list);
        cols.appendChild(slotBox);
        stage.appendChild(cols);

        if (!round.practice) {
            var actions = el('p', 'quiz__actions');
            var check = el('button', 'btn btn--primary', words.check);
            check.type = 'button';
            check.setAttribute('data-check', '');
            check.disabled = placement.indexOf(null) !== -1;
            actions.appendChild(check);
            var reset = el('button', 'btn', words.reset);
            reset.type = 'button';
            reset.setAttribute('data-reset', '');
            actions.appendChild(reset);
            stage.appendChild(actions);
        }
    }

    // ---------- moving titles ----------

    // Test: a title goes into a place and stays until it is moved.
    function place(id, slot) {
        var from = placement.indexOf(id);
        var existing = placement[slot];
        placement[slot] = id;
        if (from !== -1) { placement[from] = existing; }   // swap with what was there (or leave the old place empty)
        selected = null;
        render();
    }

    function unplace(id) {
        var from = placement.indexOf(id);
        if (from !== -1) { placement[from] = null; }
        selected = null;
        render();
    }

    // Practice: the server says at once whether the title belongs in the place.
    function tryPlace(id, slot) {
        if (busy || confirmed[slot] || flash[slot] || placement[slot] !== null || placement.indexOf(id) !== -1) { return; }
        busy = true;
        selected = null;
        tries++;
        placement[slot] = id;
        render();

        call(root.getAttribute('data-try-url'), { token: round.token, slot: slot, tile: id }).then(function (result) {
            if (result.ok) {
                confirmed[slot] = result.chapter || { number: round.slots[slot], verses: '' };
                busy = false;
                render();
                if (placedCount() === round.size) { finishPractice(); }
            } else {
                flash[slot] = 'wrong';
                render();
                setTimeout(function () {
                    delete flash[slot];
                    placement[slot] = null;
                    busy = false;
                    render();
                }, 900);
            }
        }).catch(fail);
    }

    function dropOn(id, target) {
        if (!target) { return; }
        var zone = target.closest('[data-slot]');
        if (round.practice) {
            if (zone) { tryPlace(id, Number(zone.getAttribute('data-slot'))); }
            return;
        }
        if (zone) { place(id, Number(zone.getAttribute('data-slot'))); return; }
        if (target.closest('[data-pool]')) { unplace(id); }
    }

    // Dragging with mouse or finger
    stage.addEventListener('pointerdown', function (event) {
        var tile = event.target.closest('.order-tile');
        if (!tile || locked || busy || tile.hasAttribute('data-locked') || (event.button !== undefined && event.button !== 0)) { return; }
        drag = { id: Number(tile.getAttribute('data-tile')), x: event.clientX, y: event.clientY, moved: false, ghost: null, source: tile };
    });

    document.addEventListener('pointermove', function (event) {
        if (!drag) { return; }
        if (!drag.moved) {
            if (Math.abs(event.clientX - drag.x) + Math.abs(event.clientY - drag.y) < 8) { return; }
            drag.moved = true;
            var ghost = drag.source.cloneNode(true);
            ghost.classList.add('order-ghost');
            ghost.style.width = drag.source.offsetWidth + 'px';
            document.body.appendChild(ghost);
            drag.ghost = ghost;
            drag.source.classList.add('is-dragging');
        }
        drag.ghost.style.left = (event.clientX - drag.ghost.offsetWidth / 2) + 'px';
        drag.ghost.style.top = (event.clientY - 20) + 'px';

        stage.querySelectorAll('.is-over').forEach(function (n) { n.classList.remove('is-over'); });
        var under = document.elementFromPoint(event.clientX, event.clientY);
        var target = under && (under.closest('[data-slot]') || under.closest('[data-pool]'));
        if (target) { target.classList.add('is-over'); }
        event.preventDefault();
    }, { passive: false });

    function endDrag(event, drop) {
        if (!drag) { return; }
        var d = drag;
        drag = null;
        stage.querySelectorAll('.is-over').forEach(function (n) { n.classList.remove('is-over'); });
        if (d.ghost) { d.ghost.parentNode.removeChild(d.ghost); }
        if (d.moved) {
            suppressClick = true;                           // the click that follows a drag is not a tap
            setTimeout(function () { suppressClick = false; }, 0);
            if (drop) { dropOn(d.id, document.elementFromPoint(event.clientX, event.clientY)); } else { render(); }
        }
    }

    document.addEventListener('pointerup', function (event) { endDrag(event, true); });
    document.addEventListener('pointercancel', function (event) { endDrag(event, false); });

    // Tapping and the keyboard
    stage.addEventListener('click', function (event) {
        if (suppressClick || locked || busy) { return; }

        if (event.target.closest('[data-check]')) { check(); return; }
        if (event.target.closest('[data-reset]')) { placement = placement.map(function () { return null; }); selected = null; render(); return; }

        var tile = event.target.closest('.order-tile');
        var zone = event.target.closest('[data-slot]');

        if (round.practice) {
            if (tile && !tile.hasAttribute('data-locked')) {
                var pid = Number(tile.getAttribute('data-tile'));
                selected = selected === pid ? null : pid;
                render(); focusTile(pid);
                return;
            }
            if (zone && selected !== null) { tryPlace(selected, Number(zone.getAttribute('data-slot'))); }
            return;
        }

        if (tile) {
            var id = Number(tile.getAttribute('data-tile'));
            if (selected === null) { selected = id; render(); focusTile(id); return; }
            if (selected === id) { selected = null; render(); focusTile(id); return; }
            if (zone) { place(selected, Number(zone.getAttribute('data-slot'))); return; }   // swap into that place
            selected = id; render(); focusTile(id);
            return;
        }

        if (zone && selected !== null) { place(selected, Number(zone.getAttribute('data-slot'))); return; }
        if (event.target.closest('[data-pool]') && selected !== null) { unplace(selected); }
    });

    stage.addEventListener('keydown', function (event) {
        if (locked) { return; }
        if (event.key === 'Escape' && selected !== null) { selected = null; render(); return; }
        var zone = event.target.closest && event.target.closest('[data-slot]');
        if (zone && event.target === zone && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            zone.click();
        }
    });

    function focusTile(id) {
        var t = stage.querySelector('.order-tile[data-tile="' + id + '"]');
        if (t) { t.focus(); }
    }

    // ---------- the end of Practice ----------

    function finishPractice() {
        locked = true;
        var numbers = [];
        Object.keys(confirmed).forEach(function (k) { numbers.push(confirmed[k].number); });
        markRound(numbers);

        var message = fill(words.done, { total: round.size, n: tries }) + (tries === round.size ? ' ' + words.done_best : '');
        var box = el('div', 'order__done');
        box.setAttribute('role', 'status');
        box.appendChild(el('p', 'quiz__score', message));
        box.appendChild(el('h4', 'quiz__sub', words.practice_again));
        box.appendChild(levelButtons());
        stage.insertBefore(box, stage.querySelector('.order__board'));
        box.scrollIntoView({ block: 'nearest' });
    }

    // ---------- the result of a scored round ----------

    function check() {
        if (placement.indexOf(null) !== -1) { return; }
        locked = true;
        stage.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        call(root.getAttribute('data-check-url'), { token: round.token, placement: placement }).then(showResult).catch(fail);
    }

    function showResult(result) {
        clear();
        showIndexTab(true);                 // the list is open again: study the result against it
        markRound(result.rows.map(function (r) { return r.number; }));

        stage.appendChild(el('h3', 'quiz__question', words.result_title));
        stage.appendChild(el('p', 'quiz__score', fill(words.score, { score: result.score, total: result.total })));

        var share = result.total ? result.score / result.total : 0;
        var comment = result.perfect ? words.perfect : (share >= 0.9 ? words.comment_high : (share >= 0.6 ? words.comment_mid : words.comment_low));
        stage.appendChild(el('p', 'quiz__comment', comment));

        var list = el('ol', 'order-result');
        result.rows.forEach(function (row) {
            var li = el('li', 'order-result__row ' + (row.ok ? 'is-right' : 'is-wrong'));
            li.appendChild(el('span', 'order-result__mark', row.ok ? '✓' : '✗'));

            var body = el('span', 'order-result__body');
            body.appendChild(el('strong', null, fill(words.number, { n: row.number }) + ': '));
            var ar = el('span', 'order-result__ar', row.arabic);
            ar.setAttribute('lang', 'ar');
            ar.setAttribute('dir', 'rtl');
            body.appendChild(ar);
            body.appendChild(document.createTextNode(' ' + row.name + ' (' + row.meaning + ') - ' + fill(words.verses, { n: row.verses })));
            if (!row.ok) {
                var mine = row.yours ? row.yours.name + ' (' + fill(words.number, { n: row.yours.number }) + ')' : words.nothing;
                body.appendChild(el('span', 'order-result__yours', words.you_put + ' ' + mine));
            }
            li.appendChild(body);
            list.appendChild(li);
        });
        stage.appendChild(list);

        stage.appendChild(el('p', 'quiz__fine', words.report));
        stage.appendChild(el('h4', 'quiz__sub', words.again));
        stage.appendChild(levelButtons());
    }
})();
