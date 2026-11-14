/* Expand all / Collapse all for the drop-down parts of an article. Without this script the parts still open and close one by one. */
(function () {
    'use strict';
    var tools = document.querySelector('[data-acc-tools]');
    var button = document.querySelector('[data-acc-toggle]');
    var parts = Array.prototype.slice.call(document.querySelectorAll('details.acc'));
    if (!tools || !button || parts.length < 2) { return; }

    function allOpen() { return parts.every(function (d) { return d.open; }); }
    function label() { button.textContent = allOpen() ? button.getAttribute('data-label-close') : button.getAttribute('data-label-open'); button.setAttribute('aria-pressed', allOpen() ? 'true' : 'false'); }

    button.addEventListener('click', function () {
        var open = !allOpen();
        parts.forEach(function (d) { d.open = open; });
        label();
    });
    parts.forEach(function (d) { d.addEventListener('toggle', label); });

    tools.hidden = false;
    label();
})();