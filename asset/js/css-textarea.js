/**
 * CssTextarea form element: copy a variable name from the reference into the clipboard, and let Tab indent
 * inside the textarea instead of leaving it.
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        $(document).on('click', '.agile-css-variable-name', function () {
            const button = $(this);
            const text = button.data('copy');
            const done = function () {
                button.addClass('is-copied');
                window.setTimeout(function () { button.removeClass('is-copied'); }, 900);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, done);
            } else {
                const helper = $('<textarea>').val(text).appendTo('body').select();
                try { document.execCommand('copy'); } catch (e) { /* unsupported */ }
                helper.remove();
                done();
            }
        });

        $(document).on('keydown', '.agile-css-textarea', function (e) {
            if (e.key !== 'Tab' || e.ctrlKey || e.metaKey || e.altKey) {
                return;
            }
            e.preventDefault();
            const start = this.selectionStart;
            const end = this.selectionEnd;
            this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
            this.selectionStart = this.selectionEnd = start + 4;
        });
    });
})(jQuery);
