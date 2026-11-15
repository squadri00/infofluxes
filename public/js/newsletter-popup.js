/* The newsletter card (bottom left). It shows a few seconds after the page opens, once the cookie notice has been answered.
   Closing it hides it for a number of days; subscribing hides it for good (kept only in this browser). The Subscribe link in the footer opens it.
   Without this script the Subscribe link goes to the newsletter page, so nothing is lost. */
(function () {
    'use strict';
    var card = document.querySelector('[data-nl-card]');
    if (!card) { return; }

    var KEY_HIDDEN = 'infofluxes.nl.hiddenUntil';
    var KEY_DONE = 'infofluxes.nl.done';
    var emailForm = card.querySelector('[data-nl-email]');
    var codeForm = card.querySelector('[data-nl-code]');
    var message = card.querySelector('[data-nl-message]');
    var text = card.querySelector('[data-nl-text]');
    var fine = card.querySelector('[data-nl-fine]');
    var days = parseInt(card.getAttribute('data-days'), 10) || 14;
    var delay = Math.max(0, parseInt(card.getAttribute('data-delay'), 10) || 0) * 1000;

    function read(key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } }
    function write(key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* kept for this visit only */ } }

    function blocked() {
        if (read(KEY_DONE) === '1') { return true; }
        var until = parseInt(read(KEY_HIDDEN), 10);
        return !!until && until > Date.now();
    }

    function show(focus) {
        card.hidden = false;
        if (focus) {
            var first = (!emailForm.hidden ? emailForm : codeForm).querySelector('input:not([type="hidden"]):not([name="website_url"])');
            if (first) { first.focus(); }
        }
    }

    function close(rememberDays) {
        card.hidden = true;
        if (rememberDays) { write(KEY_HIDDEN, String(Date.now() + rememberDays * 86400000)); }
    }

    function say(textValue, good) {
        message.hidden = !textValue;
        message.textContent = textValue || '';
        message.className = 'nlc__message' + (good ? ' is-good' : ' is-bad');
    }

    function post(form, extra) {
        var data = new FormData(form);
        Object.keys(extra || {}).forEach(function (k) { data.set(k, extra[k]); });
        return fetch(form.action, { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function toCode(email) {
        emailForm.hidden = true;
        codeForm.hidden = false;
        codeForm.querySelector('[name="email"]').value = email || '';
        fine.hidden = true;
        var input = codeForm.querySelector('[name="code"]');
        if (input) { input.value = ''; input.focus(); }
    }

    function busy(form, on) {
        Array.prototype.forEach.call(form.querySelectorAll('button'), function (b) { b.disabled = on; });
    }

    emailForm.addEventListener('submit', function (event) {
        event.preventDefault();
        busy(emailForm, true);
        post(emailForm).then(function (r) {
            busy(emailForm, false);
            say(r.message, r.ok);
            if (r.step === 'code') { toCode(r.email); }
        }).catch(function () { busy(emailForm, false); say('', false); emailForm.submit(); });
    });

    codeForm.addEventListener('submit', function (event) {
        event.preventDefault();
        busy(codeForm, true);
        post(codeForm).then(function (r) {
            busy(codeForm, false);
            say(r.message, r.ok);
            if (r.step === 'done') {
                write(KEY_DONE, '1');
                codeForm.hidden = true;
                emailForm.hidden = true;
                if (text) { text.hidden = true; }
                setTimeout(function () { close(0); }, 5000);
            } else if (r.step === 'form') {
                codeForm.hidden = true;
                emailForm.hidden = false;
                fine.hidden = false;
            }
        }).catch(function () { busy(codeForm, false); codeForm.submit(); });
    });

    card.querySelector('[data-nl-resend]').addEventListener('click', function () {
        var email = codeForm.querySelector('[name="email"]').value;
        var fake = emailForm.cloneNode(true);
        fake.querySelector('[name="email"]').value = email;
        busy(codeForm, true);
        post(fake).then(function (r) { busy(codeForm, false); say(r.message, r.ok); }).catch(function () { busy(codeForm, false); });
    });

    card.querySelector('[data-nl-close]').addEventListener('click', function () { close(days); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !card.hidden) { close(days); }
    });

    // The footer "Subscribe" link opens the card instead of leaving the page.
    document.querySelectorAll('[data-nl-open]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            show(true);
        });
    });

    // Offer it on its own after a pause, but never on top of the cookie notice.
    function offer() {
        if (blocked()) { return; }
        var banner = document.querySelector('[data-consent-banner]');
        var start = function () { setTimeout(function () { if (!blocked() && card.hidden) { show(false); } }, delay); };
        if (banner && !banner.hidden) {
            var watcher = new MutationObserver(function () {
                if (banner.hidden) { watcher.disconnect(); start(); }
            });
            watcher.observe(banner, { attributes: true, attributeFilter: ['hidden'] });
        } else {
            start();
        }
    }
    offer();
})();
