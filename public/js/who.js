// "Who said it?": a round of quotes, one at a time. The reader picks a tradition; the server says who really spoke.
// The answers are never in the page. Text is always inserted as text, never as HTML.
(function () {
    'use strict';

    var root = document.querySelector('[data-who]');
    if (!root) { return; }

    var words = {};
    try { words = JSON.parse(root.getAttribute('data-words') || '{}'); } catch (e) { words = {}; }

    var intro = root.querySelector('[data-quiz-intro]');
    var stage = root.querySelector('[data-quiz-stage]');
    var token = null;
    var questions = [];
    var index = 0;
    var choices = [];

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

    function restartButton() {
        var b = el('button', 'btn btn--primary', words.try_again);
        b.type = 'button';
        b.addEventListener('click', begin);
        return b;
    }

    function fail() {
        clear();
        stage.appendChild(el('p', 'notice notice--error', words.error));
        stage.appendChild(restartButton());
    }

    function begin() {
        intro.hidden = true;
        stage.hidden = false;
        clear();
        stage.appendChild(el('p', 'quiz__loading', words.loading));

        call(root.getAttribute('data-start-url')).then(function (data) {
            token = data.token;
            questions = data.questions;
            index = 0;
            choices = [];
            show();
        }).catch(fail);
    }

    function show() {
        clear();
        var q = questions[index];

        stage.appendChild(el('p', 'quiz__count', fill(words.question_of, { n: index + 1, total: questions.length })));
        stage.appendChild(el('h3', 'quiz__question', words.question));
        stage.appendChild(el('blockquote', 'quiz__passage', q.text));

        var list = el('div', 'quiz__options');
        list.setAttribute('role', 'radiogroup');
        list.setAttribute('aria-label', words.question);

        var chosen = null;
        var check = el('button', 'btn btn--primary', words.check);
        check.type = 'button';
        check.disabled = true;

        q.options.forEach(function (option, i) {
            var label = el('label', 'quiz-option quiz-option--plain');
            var input = document.createElement('input');
            input.type = 'radio';
            input.name = 'who-choice';
            input.value = String(i);
            input.addEventListener('change', function () { chosen = i; check.disabled = false; });

            label.appendChild(input);
            label.appendChild(el('span', 'quiz-option__key', option.key));
            label.appendChild(el('span', 'quiz-option__text', option.label));
            list.appendChild(label);
        });

        stage.appendChild(list);
        var actions = el('p', 'quiz__actions');
        actions.appendChild(check);
        stage.appendChild(actions);

        check.addEventListener('click', function () {
            if (chosen === null) { return; }
            check.disabled = true;
            list.querySelectorAll('input').forEach(function (input) { input.disabled = true; });
            choices[index] = chosen;

            call(root.getAttribute('data-answer-url'), { token: token, question: index, choice: chosen })
                .then(function (result) { reveal(list, actions, chosen, result); })
                .catch(fail);
        });

        var first = list.querySelector('input');
        if (first) { first.focus(); }
    }

    function reveal(list, actions, chosen, result) {
        var options = list.querySelectorAll('.quiz-option');
        if (options[result.answer]) { options[result.answer].classList.add('is-answer'); }
        if (!result.correct && options[chosen]) { options[chosen].classList.add('is-mistaken'); }

        var role = [result.speaker_note].filter(function (x) { return x; }).join('');
        var values = { kind: result.kind_name, key: result.answer_key, speaker: result.speaker, role: role };
        var banner = el('p', 'quiz__verdict ' + (result.correct ? 'is-right' : 'is-wrong'), fill(result.correct ? words.right : words.wrong, values));
        banner.setAttribute('role', 'status');
        stage.insertBefore(banner, list);

        var box = el('div', 'quiz__explain');
        if (result.note) { box.appendChild(el('span', null, result.note)); }
        if (result.source) {
            box.appendChild(el('br'));
            box.appendChild(el('em', null, words.source + ' ' + result.source));
        }
        if (result.attributed_note) {
            box.appendChild(el('br'));
            box.appendChild(el('strong', null, result.attributed_note));
        }
        stage.insertBefore(box, actions);

        var last = index === questions.length - 1;
        var next = el('button', 'btn btn--primary', last ? words.finish : words.next);
        next.type = 'button';
        next.addEventListener('click', function () {
            if (last) { done(); } else { index++; show(); }
        });
        actions.appendChild(next);
        next.focus();
    }

    function done() {
        clear();
        stage.appendChild(el('p', 'quiz__loading', words.loading));

        call(root.getAttribute('data-finish-url'), { token: token, choices: choices }).then(function (result) {
            clear();
            stage.appendChild(el('h3', 'quiz__question', words.result_title));
            stage.appendChild(el('p', 'quiz__score', fill(words.result_text, { score: result.score, total: result.total })));

            var share = result.total ? result.score / result.total : 0;
            stage.appendChild(el('p', 'quiz__comment', share >= 0.9 ? words.comment_high : (share >= 0.6 ? words.comment_mid : words.comment_low)));

            var kinds = Object.keys(result.mistakes || {});
            stage.appendChild(el('h4', 'quiz__sub', words.mistakes_title));
            if (!kinds.length) {
                stage.appendChild(el('p', null, words.no_mistakes));
            } else {
                var ul = el('ul', 'quiz__mistakes');
                kinds.forEach(function (kind) {
                    ul.appendChild(el('li', null, fill(words.mistake_line, { n: result.mistakes[kind], kind: (result.kind_names && result.kind_names[kind]) || kind })));
                });
                stage.appendChild(ul);
            }

            stage.appendChild(el('p', 'quiz__fine', words.report));
            var again = restartButton();
            var p = el('p');
            p.appendChild(again);
            stage.appendChild(p);
            again.focus();
        }).catch(fail);
    }

    root.querySelector('[data-quiz-start]').addEventListener('click', begin);
})();
