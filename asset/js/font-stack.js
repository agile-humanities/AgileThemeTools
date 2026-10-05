/**
 * FontStack form elements (theme styling): compose the CSS font-family declaration from the source, family and
 * fallback controls, keep the companion <role>_source field in step, offer suggestions per source, and preview
 * the font — loading it from Google Fonts in the admin when that is the source — reporting whether it loaded.
 * Each control is a collapsed <details>; its summary carries the font, the source and the load status, and it
 * opens itself when a font fails to load.
 */
(function ($) {
    'use strict';

    function quoteFamily(family) {
        family = String(family || '').trim();
        if (family === '') { return ''; }
        return /^[A-Za-z][A-Za-z0-9_-]*$/.test(family) ? family : '"' + family.replace(/"/g, '') + '"';
    }

    function composeDeclaration(source, family, generic, alias) {
        if (source === 'alias') { return '$' + alias; }
        family = String(family || '').trim();
        if (source === 'system' && family.indexOf(',') !== -1) { return family; } // a full stack typed or picked
        var quoted = quoteFamily(family);
        if (quoted === '') { return generic || ''; }
        return generic && family.toLowerCase() !== generic ? quoted + ', ' + generic : quoted;
    }

    function googleStylesheet(family, axes) {
        return 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(family).replace(/%20/g, '+') + ':' + axes + '&display=swap';
    }

    var loadedGoogle = {};

    function ensureGoogleLoaded(family, axes) {
        if (!family || loadedGoogle[family]) { return; }
        loadedGoogle[family] = true;
        $('<link rel="stylesheet">').attr('href', googleStylesheet(family, axes)).appendTo('head');
    }

    function initOne(wrap) {
        var value = wrap.find('input.agile-font-stack-value');
        var sourceInput = $('input[name="' + wrap.data('sourceInput') + '"]');
        var source = wrap.find('.agile-font-stack-source');
        var family = wrap.find('.agile-font-stack-family');
        var alias = wrap.find('.agile-font-stack-alias');
        var generic = wrap.find('.agile-font-stack-generic');
        var list = wrap.find('datalist');
        var sample = wrap.find('.agile-font-stack-sample');
        var status = wrap.find('.agile-font-stack-status');
        var details = wrap.find('.agile-font-stack-details');
        var summarySample = wrap.find('.agile-font-stack-summary-sample');
        var summarySource = wrap.find('.agile-font-stack-summary-source');
        var code = wrap.find('.agile-font-stack-code');
        var axes = wrap.data('googleAxes');
        var bundled = wrap.data('bundled') || [];
        var googleCategories = wrap.data('googleCategories') || {};
        var systemStacks = wrap.data('systemStacks') || {};

        var initial = String(sourceInput.val() || '').trim() || wrap.data('inferredSource') || 'system';
        source.val(initial);
        if (!source.val()) { source.val('system'); }
        // A system font may be a whole stack ("Georgia, Times, serif"); keep it intact rather than its first name.
        if (source.val() === 'system' && String(value.val() || '').indexOf(',') !== -1) {
            family.val(value.val());
        }

        function fillSuggestions() {
            list.empty();
            var s = source.val();
            if (s === 'google') {
                $.each(googleCategories, function (name, category) { list.append($('<option>').attr('value', name).text(category)); });
            } else if (s === 'bundled') {
                $.each(bundled, function (i, name) { list.append($('<option>').attr('value', name)); });
            } else if (s === 'system') {
                $.each(systemStacks, function (label, stack) { list.append($('<option>').attr('value', stack).text(label)); });
            }
        }

        function checkLoaded(fam) {
            if (!document.fonts || !fam) { status.text(''); return; }
            var probe = '16px ' + quoteFamily(fam);
            var report = function () {
                var ok = document.fonts.check(probe);
                status.text(ok ? 'Loaded' : 'Not loaded here — check the family name').toggleClass('is-ok', ok).toggleClass('is-bad', !ok);
                if (!ok) { details.prop('open', true); } // a problem should not hide inside a collapsed row
            };
            document.fonts.load(probe).then(report, report);
            window.setTimeout(report, 2500);
        }

        function update() {
            var s = source.val();
            wrap.attr('data-source', s);
            wrap.find('.agile-font-stack-family-field').toggle(s !== 'alias');
            wrap.find('.agile-font-stack-generic-field').toggle(s !== 'alias');
            wrap.find('.agile-font-stack-alias-field').toggle(s === 'alias');

            var declaration = composeDeclaration(s, family.val(), generic.val(), alias.val());
            value.val(declaration);
            sourceInput.val(s);
            code.text(declaration);
            summarySource.text(source.find('option:selected').text());

            if (s === 'alias') {
                var other = $('.agile-font-stack[data-role="' + alias.val() + '"] input.agile-font-stack-value').val() || '';
                sample.css('font-family', other);
                summarySample.text(alias.find('option:selected').text()).css('font-family', other);
                status.text('').removeClass('is-ok is-bad');
                return;
            }
            if (s === 'google') { ensureGoogleLoaded(family.val().trim(), axes); }
            sample.css('font-family', declaration || 'inherit');
            summarySample.text(declaration || '—').css('font-family', declaration || 'inherit');
            if (s === 'google' || s === 'bundled') {
                checkLoaded(family.val().trim());
            } else {
                status.text('').removeClass('is-ok is-bad');
            }
        }

        source.on('change', function () { fillSuggestions(); update(); });
        family.on('input change', update);
        generic.on('change', update);
        alias.on('change', update);
        fillSuggestions();
        update();
        return update;
    }

    $(document).ready(function () {
        var updaters = [];
        $('.agile-font-stack').each(function () { updaters.push(initOne($(this))); });
        // An alias previews the other role's font; refresh aliases when any role changes.
        $(document).on('input change', '.agile-font-stack input, .agile-font-stack select', function () {
            $.each(updaters, function (i, u) { u(); });
        });
    });
})(jQuery);
