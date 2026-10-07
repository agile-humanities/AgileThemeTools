/**
 * ColourValue form elements (theme styling): live swatches, a colour picker, resolution of theme-system
 * expressions against the other fields of the form, WCAG contrast for foreground/background pairs, a palette
 * strip, and a preview of the modular scale.
 *
 * The resolver mirrors the theme system's SASS: $name references another field (or, failing that, the theme's
 * configuration handed to the page as JSON); neutral(n[, dark, light]) is mix(dark, light, n%);
 * set-color(n, intensity[, mixer]) / set-colour is mix(clrN, mixer, intensity%); mix(a, b, w%) is the SASS
 * per-channel weighted average; ensure-contrast(c, surface[, ratio]) and on-colour(surface[, light, dark[, ratio]])
 * are the colour-role functions of the library's _contrast.sass. Anything else that CSS can parse (hex, names,
 * rgb(), hsl()) is read through the browser. Expressions the resolver does not know leave no swatch.
 *
 * The colour-roles panel (renderRolesPanel) shows, live, the continuum $black | $surface-dark | $surface-light |
 * $white with every guaranteed pair and its WCAG grade, each brand colour's permitted use on each step, and the
 * derived text variants — the feedback a themer gets while choosing colours (generic requirements E4, 7.3).
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

    function isLight(rgb) { return luminance(rgb) > 0.18; }

    /** The library's ensure-contrast(): the colour, or it mixed toward the opposite end until it reads. */
    function ensureContrast(colour, surface, ratio, toward) {
        if (contrastRatio(colour, surface) >= ratio) { return colour; }
        for (var step = 1; step <= 50; step++) {
            var candidate = mix(toward, colour, step * 2);
            if (contrastRatio(candidate, surface) >= ratio) { return candidate; }
        }
        return toward;
    }

    /** The library's on-colour(): the side's text colour, else the other side's, else black or white. */
    function onColour(surface, light, dark, black, white, ratio) {
        var candidates = isLight(surface) ? [dark, light, black, white] : [light, dark, white, black];
        for (var i = 0; i < candidates.length; i++) {
            if (candidates[i] && contrastRatio(candidates[i], surface) >= ratio) { return candidates[i]; }
        }
        return contrastRatio(black, surface) >= contrastRatio(white, surface) ? black : white;
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
            if (f === 'ensure-contrast' && args.length >= 2) {
                var ec = col(args[0]), es = col(args[1]);
                if (!ec || !es) { return null; }
                var er = args[2] !== undefined && args[2] !== '' ? num(args[2], 4.5) : 4.5;
                var toward = args[3] && args[3] !== 'null' ? col(args[3]) : (isLight(es) ? col('$black') : col('$white'));
                return toward ? ensureContrast(ec, es, er, toward) : null;
            }
            if (f === 'on-colour' || f === 'on-color') {
                var os = col(args[0]);
                if (!os) { return null; }
                var ol = args[1] ? col(args[1]) : col('$on_dark'), od = args[2] ? col(args[2]) : col('$on_light');
                var oblack = col('$black') || [0, 0, 0], owhite = col('$white') || [255, 255, 255];
                return onColour(os, ol || owhite, od || oblack, oblack, owhite, args[3] !== undefined ? num(args[3], 4.5) : 4.5);
            }
            if (f === 'rgba' && args.length === 2) {
                var rc = col(args[0]);
                if (!rc) { return null; }
                var ra = num(args[1], 1);
                return [rc[0] * ra + 255 * (1 - ra), rc[1] * ra + 255 * (1 - ra), rc[2] * ra + 255 * (1 - ra)];
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

        // The theme's colour configuration as the page received it (data-agile-colour-config on the form): the
        // values of keys that are not fields here — the roles, the aliases — so every expression can be resolved.
        var pageConfig = null;
        function configOf() {
            if (pageConfig === null) {
                pageConfig = {};
                var holder = $('[data-agile-colour-config]').first();
                if (holder.length) {
                    try { pageConfig = JSON.parse(holder.attr('data-agile-colour-config')) || {}; } catch (e) { pageConfig = {}; }
                }
            }
            return pageConfig;
        }

        function lookupFactory(map) {
            var config = configOf();
            return function (name) {
                var f = map[name];
                if (f) {
                    var v = f.find('input.agile-colour-value').val();
                    // An empty override field means "automatic": the configuration's expression.
                    if (String(v).trim() !== '' || !Object.prototype.hasOwnProperty.call(config, name)) { return v; }
                }
                return Object.prototype.hasOwnProperty.call(config, name) ? config[name] : null;
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
                var automatic = false;
                if (!rgb && String(raw).trim() === '' && Object.prototype.hasOwnProperty.call(configOf(), name)) {
                    rgb = resolve('$' + name, lookup, parseCss); // an empty override: the configuration's own value
                    automatic = !!rgb;
                }
                resolved[name] = rgb;
                var swatch = wrap.find('.agile-colour-swatch');
                var picker = wrap.find('.agile-colour-picker');
                var caption = wrap.find('.agile-colour-resolved');
                if (rgb) {
                    var hex = rgbToHex(rgb);
                    swatch.css('background-color', hex).removeClass('is-unknown');
                    picker.val(hex);
                    caption.text(automatic ? 'automatic: ' + hex : (hexToRgb(raw) ? '' : hex));
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
            renderRolesPanel(map, lookup);
        }

        // ---------------------------------------------------------------- the colour-roles panel

        var GRADE = function (ratio) {
            if (ratio >= 7) { return { label: 'AAA', use: 'body text', ok: true }; }
            if (ratio >= 4.5) { return { label: 'AA', use: 'body text', ok: true }; }
            if (ratio >= 3) { return { label: 'AA large', use: 'large text and UI parts', ok: false }; }
            return { label: 'fails', use: 'accent only', ok: false };
        };
        var STEPS = [['black', 'black'], ['surface_dark', 'dark surface'], ['surface_light', 'light surface'], ['white', 'white']];
        // The guaranteed pairs, as the library checks them (colour_roles component).
        var PAIRS = [
            ['black', 'white', 'blackpoint on whitepoint'], ['black', 'surface_light', 'black on the light surface'],
            ['on_light', 'white', 'text on white'], ['on_light', 'surface_light', 'text on the light surface'],
            ['white', 'black', 'whitepoint on blackpoint'], ['white', 'surface_dark', 'white on the dark surface'],
            ['on_dark', 'black', 'text on black'], ['on_dark', 'surface_dark', 'text on the dark surface'],
            ['on_impact', 'visual_impact', 'text on the impact colour'],
            ['on_brand', 'surface_brand', 'text on the primary brand surface'], ['on_brand_secondary', 'surface_brand_secondary', 'text on the secondary brand surface']
        ];
        var BRAND = [['brand_primary', 'Primary brand colour'], ['brand_secondary', 'Secondary brand colour'], ['clr6', 'Colour 6'], ['clr7', 'Colour 7'], ['clr8', 'Colour 8'], ['clr9', 'Colour 9']];
        var DERIVED = [
            ['brand_primary_on_light', 'brand_primary', 'Primary brand colour as text on the light side'],
            ['brand_primary_on_dark', 'brand_primary', 'Primary brand colour as text on the dark side'],
            ['brand_secondary_on_light', 'brand_secondary', 'Secondary brand colour as text on the light side'],
            ['brand_secondary_on_dark', 'brand_secondary', 'Secondary brand colour as text on the dark side'],
            ['on_impact', 'visual_impact', 'Text on the impact colour'],
            ['on_brand', 'surface_brand', 'Text on the primary brand surface'],
            ['on_brand_secondary', 'surface_brand_secondary', 'Text on the secondary brand surface']
        ];

        function renderRolesPanel(map, lookup) {
            var anchor = map.clr1 ? map.clr1.closest('fieldset') : null;
            if (!anchor || !anchor.length) { return; }
            var config = configOf();
            if (!Object.prototype.hasOwnProperty.call(config, 'surface_dark') && !map.surface_dark) { return; } // a theme without roles
            var panel = $('#agile-colour-roles');
            if (!panel.length) {
                panel = $('<div id="agile-colour-roles" class="agile-colour-roles" aria-live="polite"></div>');
                var host = $('#agile-colour-roles-host');
                if (host.length) { host.append(panel); } else { anchor.after(panel); }
            }
            var get = function (name) { return resolve('$' + name, lookup, parseCss); };
            var hex = function (rgb) { return rgb ? rgbToHex(rgb) : '—'; };
            var raw = function (name) { return map[name] ? String(map[name].find('input.agile-colour-value').val() || '').trim() : ''; };
            panel.empty();

            // 1. The continuum and its guaranteed pairs
            var failures = [];
            var strip = $('<div class="agile-roles-continuum"></div>');
            STEPS.forEach(function (step) {
                var rgb = get(step[0]);
                var cell = $('<div class="agile-roles-step"></div>').css('background-color', hex(rgb));
                cell.append($('<div class="agile-roles-step-name"></div>').text(step[1] + ' · ' + hex(rgb)).css('color', rgb && isLight(rgb) ? '#000' : '#fff'));
                PAIRS.forEach(function (pair) {
                    if (pair[1] !== step[0]) { return; }
                    var fg = get(pair[0]);
                    if (!fg || !rgb) { return; }
                    var r = contrastRatio(fg, rgb), g = GRADE(r);
                    if (r < 4.5) { failures.push(pair[2] + ' (' + pair[0] + ' ' + hex(fg) + ' on ' + hex(rgb) + ') is ' + (Math.round(r * 100) / 100) + ':1'); }
                    cell.append($('<div class="agile-roles-sample"></div>').css('color', hex(fg)).text(pair[0].replace(/_/g, '-') + ' ' + (Math.round(r * 100) / 100) + ':1 ' + g.label).toggleClass('is-bad', r < 4.5));
                });
                strip.append(cell);
            });
            var impact = get('visual_impact'), onImpact = get('on_impact');
            var extra = $('<div class="agile-roles-continuum agile-roles-extra"></div>');
            [['visual_impact', 'on_impact', 'impact'], ['surface_brand', 'on_brand', 'primary brand surface'], ['surface_brand_secondary', 'on_brand_secondary', 'secondary brand surface']].forEach(function (s) {
                var bg = get(s[0]), fg = get(s[1]);
                if (!bg || !fg) { return; }
                var r = contrastRatio(fg, bg), g = GRADE(r);
                if (r < 4.5) { failures.push('text on the ' + s[2] + ' (' + hex(fg) + ' on ' + hex(bg) + ') is ' + (Math.round(r * 100) / 100) + ':1'); }
                var cell = $('<div class="agile-roles-step"></div>').css('background-color', hex(bg));
                cell.append($('<div class="agile-roles-step-name"></div>').text(s[2] + ' · ' + hex(bg)).css('color', isLight(bg) ? '#000' : '#fff'));
                cell.append($('<div class="agile-roles-sample"></div>').css('color', hex(fg)).text(s[1].replace(/_/g, '-') + ' ' + (Math.round(r * 100) / 100) + ':1 ' + g.label).toggleClass('is-bad', r < 4.5));
                extra.append(cell);
            });
            panel.append($('<h4></h4>').text('How the colours work together'));
            panel.append($('<p class="agile-roles-lead"></p>').text('The page is white and the blackpoint its inverse; the two surfaces are a step in from each end, for panels that stand out without contrasting. Each text colour must read (4.5:1) on both steps of its side. These pairs are guaranteed: a failure here is a configuration to fix.'));
            if (failures.length) {
                var warn = $('<div class="agile-roles-warning" role="alert"></div>').append($('<strong></strong>').text(failures.length === 1 ? 'One guaranteed pair fails: ' : failures.length + ' guaranteed pairs fail: '));
                warn.append($('<span></span>').text(failures.join('; ') + '.'));
                panel.append(warn);
            } else {
                panel.append($('<div class="agile-roles-ok"></div>').text('Every guaranteed pair reads at AA or better.'));
            }
            panel.append(strip).append(extra);

            // 2. Brand colours: permitted use on each step
            var table = $('<table class="agile-roles-brand"></table>');
            var head = $('<tr><th>Brand and accent colours</th></tr>');
            STEPS.forEach(function (step) { head.append($('<th></th>').text('on ' + step[1])); });
            table.append($('<thead></thead>').append(head));
            var body = $('<tbody></tbody>');
            BRAND.forEach(function (b) {
                var rgb = get(b[0]);
                if (!rgb) { return; }
                var row = $('<tr></tr>');
                row.append($('<th scope="row"></th>').append($('<span class="agile-roles-chip"></span>').css('background-color', hex(rgb))).append(document.createTextNode(' ' + b[1] + ' ' + hex(rgb))));
                STEPS.forEach(function (step) {
                    var bg = get(step[0]);
                    if (!bg) { row.append('<td></td>'); return; }
                    var r = contrastRatio(rgb, bg), g = GRADE(r);
                    var td = $('<td></td>').addClass('agile-roles-use-' + g.use.split(' ')[0]);
                    td.append($('<span class="agile-roles-sample-text"></span>').css({ color: hex(rgb), backgroundColor: hex(bg) }).text('Aa'));
                    td.append($('<span class="agile-roles-use"></span>').text(g.use)).append($('<span class="agile-roles-ratio"></span>').text(Math.round(r * 100) / 100 + ':1'));
                    row.append(td);
                });
                body.append(row);
            });
            table.append(body);
            panel.append($('<p class="agile-roles-lead"></p>').text('Brand colours are what the brand says they are: they are never changed, only tested. Here is where each may be used as it is — as body text, as large text and interface parts, or only as an accent that carries no text.'));
            panel.append(table);

            // 3. Derived text variants
            var list = $('<ul class="agile-roles-derived"></ul>');
            DERIVED.forEach(function (d) {
                var value = get(d[0]), source = get(d[1]);
                if (!value) { return; }
                var li = $('<li></li>');
                li.append($('<span class="agile-roles-chip"></span>').css('background-color', hex(value)));
                var same = source && hex(source) === hex(value);
                var overridden = map[d[0]] && raw(d[0]) !== '';
                var text = d[2] + ': ' + hex(value) + (overridden ? ' (set by you)' : same ? ' (the colour as it is)' : (d[0].indexOf('on_') === 0 && d[0] !== 'on_impact' || /^on_/.test(d[0]) ? ' (picked for legibility)' : ' (derived from ' + hex(source) + ' for legibility)'));
                li.append(document.createTextNode(' ' + text));
                list.append(li);
            });
            panel.append($('<p class="agile-roles-lead"></p>').text('Where a brand colour is used as text, the theme uses it as it is when it reads, and otherwise a variant darkened or lightened until it does. These are the variants in play; a brand-approved tint can replace a derived one below.'));
            panel.append(list);
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

    return { resolve: resolve, mix: mix, hexToRgb: hexToRgb, rgbToHex: rgbToHex, contrastRatio: contrastRatio, describeContrast: describeContrast, ensureContrast: ensureContrast, onColour: onColour, isLight: isLight };
}));
