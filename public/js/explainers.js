/* Word notes: tap a word in an article and its note opens in a small card. Without this script the notes are listed under the article. */
(function () {
    'use strict';
    var words = Array.prototype.slice.call(document.querySelectorAll('button.exp[data-exp]'));
    if (!words.length) { return; }

    var pop = null;
    var current = null;

    function close() {
        if (pop && pop.parentNode) { pop.parentNode.removeChild(pop); }
        if (current) { current.setAttribute('aria-expanded', 'false'); }
        pop = null;
        current = null;
    }

    function open(button) {
        var card = document.getElementById('exp-card-' + button.getAttribute('data-exp'));
        if (!card) { return; }
        close();
        pop = document.createElement('div');
        pop.className = 'exp-pop';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-label', button.textContent);
        var term = card.querySelector('.exp-card__term');
        var body = card.querySelector('.exp-card__body');
        var head = document.createElement('div');
        head.className = 'exp-pop__term';
        head.textContent = term ? term.textContent : button.textContent;
        var text = document.createElement('div');
        text.className = 'exp-pop__body';
        text.textContent = body ? body.textContent : '';
        var shut = document.createElement('button');
        shut.type = 'button';
        shut.className = 'exp-pop__close';
        shut.setAttribute('aria-label', 'Close');
        shut.textContent = '×';
        shut.addEventListener('click', close);
        pop.appendChild(shut);
        pop.appendChild(head);
        pop.appendChild(text);
        document.body.appendChild(pop);

        // Under the word, kept inside the window.
        var r = button.getBoundingClientRect();
        var width = Math.min(340, window.innerWidth - 24);
        pop.style.width = width + 'px';
        var left = Math.max(12, Math.min(window.scrollX + r.left, window.scrollX + window.innerWidth - width - 12));
        pop.style.left = left + 'px';
        pop.style.top = (window.scrollY + r.bottom + 8) + 'px';
        button.setAttribute('aria-expanded', 'true');
        current = button;
    }

    words.forEach(function (button) {
        button.addEventListener('click', function (e) {
            e.stopPropagation();
            if (current === button) { close(); } else { open(button); }
        });
    });
    document.addEventListener('click', function (e) { if (pop && !pop.contains(e.target)) { close(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
    window.addEventListener('resize', close);
})();
