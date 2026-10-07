# Styling a site: two pages

A site on an Agile theme has two styling pages, reached from its Theme page.

## Colours and fonts — no build

The nine palette colours and the font roles. What is saved applies to the site at once.

Offered only where the stylesheet the site is served reads its palette and fonts from CSS variables: the
Simplified theme always, the Advanced theme when it was built with `gulp build --variables` (the theme's
`tools/README.md`). The Advanced theme builds plain compiled CSS by default — its colours are compiled in — so by
default its sites have only "Configure theme styles". The module tells the two apart by a marker the tool leaves
in the stylesheet (`--agile-css-variables`).

It works because the theme's compiled stylesheet reads its palette and fonts from CSS variables (the theme's
`tools/css-variables.js` makes it so; see the theme's `tools/README.md`). On save, the module writes a small
stylesheet that sets those variables for the site — `files/theme-assets/custom/palette-<site>.css`, loaded
before the custom CSS layers — so no build is involved. Every colour derived from the palette (greys, tints,
hover states) follows, because the stylesheet derives them with CSS `color-mix()`.

Fonts: a Google font is loaded automatically (the module adds the `<link>`), a font bundled with the theme or a
system font is used as it is.

A site served from its own per-site build has its colours compiled in even when the theme's own stylesheet
carries variables; the page then says that its values take effect at the next build.

## Configure theme styles — needs a build

The full configuration: sizes, spacing, the modular scale, every colour alias, and the same palette and fonts.
Changes take effect when the site theme is built (see THEME-BUILD.md). Offered only where the theme declares a
build system and the site has "Use theme configuration" switched on.

## They edit the same values

Both pages save to the site's styling settings, so a colour chosen on the quick page is what a later build uses,
and a build does not undo it. Values written on the full page as theme-system expressions (`$clr2`,
`neutral(40)`) cannot be applied without a build; the quick page shows the theme's default in their place and
says so when such a value is saved.

Styling values belong to the base theme they were saved for (THEME-IDENTITY.md, "Styling values belong to a base
theme").

## Derived colour roles

The theme's configuration defines some colours by rule rather than by proportion: `on_impact`,
`brand_primary_on_light`, `on_brand` and the like are `on-colour()` and `ensure-contrast()` expressions that the
build evaluates (a brand colour as it is when it reads on its surface, otherwise darkened or lightened until it
does; the text colour for a surface). `color-mix()` cannot express them, so the theme's variables tool leaves
their `:root` definitions as built values and makes the rules that use them read `var(--colour--on-impact)` and
so on. When a site changes a palette colour on the Colours and fonts page, the palette stylesheet recomputes
every derived role from the new palette with the build's own maths (`Styling\ColourExpression`, a port of the
library's `_contrast.sass`) and redefines those variables. The Simplified theme does the same in its own layout,
with a standalone copy of the evaluator (`agile-colour.php`) and its colour configuration baked in by the
generator.

## The colour-roles panel

Both styling pages show, live as values change, how the colours work together (`asset/js/colour-value.js`,
which resolves the theme's expressions in the browser, the colour-role functions included): the continuum
`$black | $surface-dark | $surface-light | $white` with every guaranteed pair and its WCAG grade and a warning
naming any that fails; each brand and accent colour's permitted use on each step (body text, large text and UI
parts, accent only) with a sample; and the derived text variants in play, with where each came from. The page
hands the script the effective colour configuration (`Styling\ColourRoles::colourConfig`), so roles the form does
not show still resolve. On Colours and fonts, four fields take brand-approved tints in place of the automatic
variants (empty = automatic; the swatch shows the automatic value); saving warns on failing guaranteed pairs.

## For themers

The theme ships `asset/css/theme-variables.css`: every variable with notes — the palette and fonts to change,
the derived colours and how they are made, the sizing variables for reading in custom CSS. Copy the lines you
need into the site's custom CSS, inside the same `@supports` block; loading the file as it is changes nothing.

## Browsers

Browsers older than early 2023 cannot mix colours in CSS. They show the theme's built palette throughout: the
palette stylesheet and the derived-colour definitions both sit inside `@supports (color: color-mix(…))`.

## Basic styles for the module's templates

The module's own templates — its page blocks, the card, item browse, the item header and media blocks — carry no
styling of their own. **Settings > Agile Theme Tools > "Basic styles for the module's templates"** (global, default
off) loads four small, plain stylesheets on public pages so they are presentable under any theme, in the usual
Omeka manner: `asset/css/basic/agile-text-blocks.css`, `agile-listings.css`, `agile-slide-viewer.css`,
`agile-item.css` (`Styling\BasicStyles`). They read the palette variables `--colour--1/3/4` when the theme defines
them and fall back to neutral greys.

They are never loaded for an Agile theme (one that declares `agile_base`): the Advanced theme styles the
templates from its SASS and the Simplified theme carries that compiled stylesheet. When the setting is on, a site using such a theme sees a note on its Theme
page. The stylesheets are hand-maintained and deliberately minimal; a template change may need a matching edit.
