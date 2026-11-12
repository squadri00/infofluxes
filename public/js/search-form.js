// The search page: choices left on "Any" are not sent, so the web address of a search holds only what was chosen
// (technical document 13.5: "the chosen filters appear in the web address, so a researcher can share a search").
(function () {
    'use strict';

    var form = document.querySelector('[data-search-form]');
    if (!form) return;

    form.addEventListener('submit', function () {
        var fields = form.querySelectorAll('select, input[type="date"], input[type="search"]');
        Array.prototype.forEach.call(fields, function (field) {
            var isSort = field.name === 'sort' && field.value === 'best';
            if (field.value === '' || isSort) {
                field.disabled = true; // a disabled field is not sent
            }
        });

        // The browser may come "back" to this page with the fields still disabled.
        setTimeout(function () {
            Array.prototype.forEach.call(form.querySelectorAll(':disabled'), function (field) { field.disabled = false; });
        }, 0);
    });
})();
