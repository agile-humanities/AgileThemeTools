/**
 * ColourValue form elements (theme styling): live swatches, a colour picker, resolution of theme-system
 * expressions against the other fields of the form, WCAG contrast for foreground/background pairs, a palette
 * strip, and a preview of the modular scale.
 *
 * The resolver mirrors the theme system's SASS: $name references another field; neutral(n[, dark, light]) is
 * mix(dark, light, n%); set-color(n, intensity[, mixer]) / set-colour is mix(clrN, mixer, intensity%);
 * mix(a, b, w%) is the SASS per-channel weighted average. Anything else that CSS can parse (hex, names, rgb(),
 * hsl()) is read through the browser. Expressions the resolver does not know leave no swatch.
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory(); // node: tests
    } else {
        root.AgileColourValue = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    // ---------------------------------------------------------------- pure colour maths

    function clamp(n) { return Math.max(0, Math.min(255, Math.round(n))); }

    function hexToRgb(hex) {
        var m = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(String(hex).trim());
        if (!m) { return null; }
        var h = m[1];
        if (h.length === 3) { h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2]; }
        return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)];
    }

    function rgbToHex(rgb) {
        return '#' + rgb.map(function (c) { var s = clamp(c).toString(16); return s.length === 1 ? '0' + s : s; }).join('');
    }

    /** SASS mix(color1, color2, weight%): weight is the share of color1. */
    function mix(a, b, weight) {
        var w = Math.max(0, Math.min(100, weight)) / 100;
        return [a[0] * w + b[0] * (1 - w), a[1] * w + b[1] * (1 - w), a[2] * w + b[2] * (1 - w)];
    }

    function luminance(rgb) {
        var t = rgb.map(function (c) { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); });
        return 0.2126 * t[0] + 0.7152 * t[1] + 0.0722 * t[2];
    }

    function contrastRatio(a, b) {
        var l1 = luminance(a), l2 = luminance(b);
        return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    }

    // ---------------------------------------------------------------- expression resolver

    /** Splits "a, b, 10%" on top-level commas. */
    function splitArgs(s) {
        var out = [], depth = 0, cur = '';
        for (var i = 0; i < s.length; i++) {
            var ch = s[i];
            if (ch === '(') { depth++; }
            if (ch === ')') { depth--; }
            if (ch === ',' && depth === 0) { out.push(cur.trim()); cur = ''; } else { cur += ch; }
        }
        if (cur.trim() !== '') { out.push(cur.trim()); }
        return out;
    }

    /**
     * Resolves an expression to [r, g, b] or null.
     * @param {string} value
     * @param {function(string): string|null} lookup  Returns another field's raw value by variable name (without $).
     * @param {function(string): number[]|null} parseCss  Browser CSS colour parser; may be null in node.
     * @param {string[]} [stack] cycle guard
     */
    function resolve(value, lookup, parseCss, stack) {
        stack = stack || [];
        var v = String(value == null ? '' : value).trim();
        if (v === '' || stack.length > 12) { return null; }

        var hex = hexToRgb(v);
        if (hex) { return hex; }

        var ref = /^\$([A-Za-z0-9_-]+)$/.exec(v);
        if (ref) {
            var name = ref[1];
            if (stack.indexOf(name) !== -1) { return null; }
            var other = lookup(name);
            if (other == null) {
                // The theme system's own aliases.
                if (name === 'true_black') { return [0, 0, 0]; }
                return null;
            }
            return resolve(other, lookup, parseCss, stack.concat([name]));
        }

        var fn = /^([a-z_-]+)\s*\((.*)\)$/i.exec(v);
        if (fn) {
            var f = fn[1].toLowerCase().replace(/_/g, '-');
            var args = splitArgs(fn[2]);
            var num = function (s, dflt) { var n = parseFloat(String(s).replace('%', '')); return isNaN(n) ? dflt : n; };
            var col = function (s) { return resolve(s, lookup, parseCss, stack); };

            if (f === 'neutral' || f === 'neutral-mix') {
                var intensity = args.length ? num(args[0], 50) : 50;
                var dark = args[1] ? col(args[1]) : col('$black');
                var light = args[2] ? col(args[2]) : col('$white');
                return dark && light ? mix(dark, light, intensity) : null;
            }
            if (f === 'set-color' || f === 'set-colour') {
                var base = col('$clr' + num(args[0], 1));
                var mixer = args[2] ? col(args[2]) : [255, 255, 255];
                return base && mixer ? mix(base, mixer, args[1] !== undefined ? num(args[1], 100) : 100) : null;
            }
            if (f === 'set-color-tint' || f === 'set-colour-tint') {
                var b2 = col('$clr' + num(args[0], 1));
                var m2 = args[2] ? col(args[2]) : [255, 255, 255];
                return b2 && m2 ? mix(b2, m2, args[1] !== undefined ? num(args[1], 100) : 100) : null;
            }
            if (f === 'mix' && args.length >= 2) {
                var c1 = col(args[0]), c2 = col(args[1]);
                return c1 && c2 ? mix(c1, c2, args[2] !== undefined ? num(args[2], 50) : 50) : null;
            }
            if ((f === 'rgb' || f === 'rgba' || f === 'hsl' || f === 'hsla') && parseCss) {
                return parseCss(v);
            }
            return null;
        }

        return parseCss ? parseCss(v) : null;
    }

    function describeContrast(ratio) {
        var r = Math.round(ratio * 100) / 100;
        if (ratio >= 7) { return { ratio: r, level: 'AAA', ok: true }; }
        if (ratio >= 4.5) { return { ratio: r, level: 'AA', ok: true }; }
        if (ratio >= 3) { return { ratio: r, level: 'AA large text only', ok: false }; }
        return { ratio: r, level: 'fails', ok: false };
    }

    // ---------------------------------------------------------------- browser wiring

    function initBrowser($) {
        var canvas = document.createElement('canvas').getContext && document.createElement('canvas').getContext('2d');

        // Reads any CSS colour the browser understands; alpha is composited on white.
        function parseCss(str) {
            if (!canvas) { return null; }
            canvas.fillStyle = '#000';
            canvas.fillStyle = str;
            var out = canvas.fillStyle; // normalised: "#rrggbb" or "rgba(r, g, b, a)"
            if (out === '#000000' && !/^(#0{3}|#0{6}|black|rgba?\(\s*0\s*,\s*0\s*,\s*0)/i.test(String(str).trim())) {
                return null; // invalid colours fall back to black; treat as unknown unless it really is black
            }
            var h = hexToRgb(out);
            if (h) { return h; }
            var m = /^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/.exec(out);
            if (!m) { return null; }
            var a = m[4] === undefined ? 1 : parseFloat(m[4]);
            return [+m[1] * a + 255 * (1 - a), +m[2] * a + 255 * (1 - a), +m[3] * a + 255 * (1 - a)];
        }

        function fields() {
            var map = {};
            $('.agile-colour').each(function () { map[$(this).data('name')] = $(this); });
            return map;
        }

        function lookupFactory(map) {
            return function (name) {
                var f = map[name];
                return f ? f.find('input.agile-colour-value').val() : null;
            };
        }

        function update() {
            var map = fields();
            var lookup = lookupFactory(map);
            var resolved = {};
            $.each(map, function (name, wrap) {
                var input = wrap.find('input.agile-colour-value');
                var raw = input.val();
                var rgb = resolve(raw, lookup, parseCss);
                resolved[name] = rgb;
                var swatch = wrap.find('.agile-colour-swatch');
                var picker = wrap.find('.agile-colour-picker');
                var caption = wrap.find('.agile-colour-resolved');
                if (rgb) {
                    var hex = rgbToHex(rgb);
                    swatch.css('background-color', hex).removeClass('is-unknown');
                    picker.val(hex);
                    caption.text(hexToRgb(raw) ? '' : hex);
                } else {
                    swatch.css('background-color', '').addClass('is-unknown');
                    caption.text(String(raw).trim() === '' ? '' : '?');
                }
            });
            $.each(map, function (name, wrap) {
                var badge = wrap.find('.agile-colour-contrast');
                var withName = wrap.data('contrastWith');
                if (!withName) { badge.text('').removeClass('is-ok is-bad'); return; }
                var mine = resolved[name], theirs = resolved[withName];
                if (!mine || !theirs) { badge.text('').removeClass('is-ok is-bad'); return; }
                var d = describeContrast(contrastRatio(mine, theirs));
                var role = wrap.data('contrastRole') === 'bg' ? 'under' : 'on';
                badge.text(d.ratio + ':1 ' + d.level + ' (' + role + ' ' + withName + ')')
                    .toggleClass('is-ok', d.ok).toggleClass('is-bad', !d.ok);
            });
            updatePaletteStrip(map, resolved);
        }

        function updatePaletteStrip(map, resolved) {
            var first = map.clr1;
            if (!first) { return; }
            var fieldset = first.closest('fieldset');
            var strip = fieldset.find('.agile-palette-strip');
            if (!strip.length) {
                strip = $('<div class="agile-palette-strip" aria-hidden="true"></div>');
                fieldset.children('legend').after(strip);
            }
            strip.empty();
            ['clr1', 'clr2', 'clr3', 'clr4', 'clr5', 'clr6', 'clr7', 'clr8', 'clr9', 'black', 'white'].forEach(function (name) {
                if (!map[name]) { return; }
                var rgb = resolved[name];
                var chip = $('<span class="agile-palette-chip"></span>').attr('title', name + (rgb ? ' ' + rgbToHex(rgb) : ''));
                chip.css('background-color', rgb ? rgbToHex(rgb) : 'transparent').toggleClass('is-unknown', !rgb);
                chip.append($('<span class="agile-palette-chip-label"></span>').text(name.replace('clr', '')));
                strip.append(chip);
            });
        }

        function updateScalePreview() {
            var scale = parseFloat($('input[name="scale"]').val());
            var base = String($('input[name="basetypesize"]').val() || '').trim();
            var lh = parseFloat($('input[name="base_line_height"]').val());
            var anchor = $('input[name="scale"]').closest('fieldset');
            if (!anchor.length || isNaN(scale) || scale <= 1 || scale > 3) { return; }
            var baseRem = parseFloat(base) || 1;
            var unit = /px$/i.test(base) ? 'px' : 'rem';
            var preview = anchor.find('.agile-scale-preview');
            if (!preview.length) {
                preview = $('<div class="agile-scale-preview"></div>');
                anchor.append(preview);
            }
            preview.empty().append($('<div class="agile-scale-preview-title"></div>').text('Preview of the scale: each step is the previous size × ' + scale));
            [-1, 0, 1, 2, 3, 4].forEach(function (step) {
                var size = Math.round(baseRem * Math.pow(scale, step) * 100) / 100;
                var row = $('<div class="agile-scale-preview-row"></div>');
                row.append($('<span class="agile-scale-preview-label"></span>').text((step === 0 ? 'base' : 'step ' + step) + ' · ' + size + unit));
                row.append($('<span class="agile-scale-preview-sample"></span>').text('The quick brown fox').css({fontSize: size + unit, lineHeight: isNaN(lh) ? '' : String(lh)}));
                preview.append(row);
            });
            var spacing = [-2, -1, 0, 1, 2, 3].map(function (n) { return Math.round(1.5 * Math.pow(scale, n) * 100) / 100 + 'rem'; });
            preview.append($('<div class="agile-scale-preview-spacing"></div>').text('Spacing steps (rv −2 … 3 at line height 1.5): ' + spacing.join(', ')));
        }

        $(document).ready(function () {
            if (!$('.agile-colour').length) { return; }
            $(document).on('input change', 'input.agile-colour-value', update);
            $(document).on('input change', '.agile-colour-picker', function () {
                $(this).siblings('input.agile-colour-value').val($(this).val()).trigger('input');
            });
            $(document).on('input change', 'input[name="scale"], input[name="basetypesize"], input[name="base_line_height"]', updateScalePreview);
            update();
            updateScalePreview();
        });
    }

    if (typeof jQuery !== 'undefined') {
        initBrowser(jQuery);
    }

    return { resolve: resolve, mix: mix, hexToRgb: hexToRgb, rgbToHex: rgbToHex, contrastRatio: contrastRatio, describeContrast: describeContrast };
}));
