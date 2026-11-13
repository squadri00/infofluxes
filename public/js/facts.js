// "Fact or myth?": a round of statements, one at a time. Press True or False and the server says why.
// The answers are never in the page. Text is always inserted as text, never as HTML.
(function () {
    'use strict';

    var root = document.querySelector('[data-facts]');
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

        var buttons = el('div', 'quiz__tf');
        buttons.setAttribute('role', 'group');
        buttons.setAttribute('aria-label', words.question);

        [['1', words['true']], ['0', words['false']]].forEach(function (pair) {
            var b = el('button', 'btn quiz__tf-button', pair[1]);
            b.type = 'button';
            b.value = pair[0];
            b.addEventListener('click', function () { answer(buttons, Number(pair[0]), b); });
            buttons.appendChild(b);
        });

        stage.appendChild(buttons);
        stage.appendChild(el('p', 'quiz__actions'));
        buttons.querySelector('button').focus();
    }

    function answer(buttons, choice, pressed) {
        buttons.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        choices[index] = choice;

        call(root.getAttribute('data-answer-url'), { token: token, question: index, choice: choice })
            .then(function (result) { reveal(buttons, pressed, result); })
            .catch(fail);
    }

    function reveal(buttons, pressed, result) {
        buttons.querySelectorAll('button').forEach(function (b) {
            if (Number(b.value) === (result.is_true ? 1 : 0)) { b.classList.add('is-answer'); }
        });
        if (!result.correct) { pressed.classList.add('is-mistaken'); }

        var message = fill(result.correct ? words.right : words.wrong, { verdict: result.verdict.toUpperCase() });
        var banner = el('p', 'quiz__verdict ' + (result.correct ? 'is-right' : 'is-wrong'), message);
        banner.setAttribute('role', 'status');
        stage.insertBefore(banner, buttons);

        var box = el('div', 'quiz__explain');
        box.appendChild(el('span', null, result.explanation));
        if (result.source) {
            box.appendChild(el('br'));
            box.appendChild(el('em', null, words.source + ' ' + result.source));
        }
        var actions = stage.querySelector('.quiz__actions');
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
