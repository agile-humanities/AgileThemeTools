/**
 * Site theme build panel: while a build runs, poll the status endpoint and swap in the re-rendered panel, so the
 * result (and the live build output) appears without visiting the jobs page.
 */
(function ($) {
    'use strict';

    const POLL_MS = 3000;

    function watch(panel) {
        if (panel.data('running') !== 1 && panel.data('running') !== '1') {
            return;
        }
        const url = panel.data('statusUrl');
        const log = panel.find('.theme-build-log pre')[0];
        if (log) {
            log.scrollTop = log.scrollHeight;
        }
        window.setTimeout(function () {
            $.getJSON(url).done(function (data) {
                if (!data || typeof data.html !== 'string') {
                    watch(panel);
                    return;
                }
                const fresh = $(data.html);
                panel.replaceWith(fresh);
                if (data.running) {
                    watch(fresh);
                } else {
                    fresh.addClass('theme-build-just-finished');
                }
            }).fail(function () {
                watch(panel); // Keep trying; the server may be busy with the build.
            });
        }, POLL_MS);
    }

    $(document).ready(function () {
        $('.theme-build-panel').each(function () {
            watch($(this));
        });
    });
})(jQuery);
