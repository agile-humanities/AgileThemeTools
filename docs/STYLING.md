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

## For themers

The theme ships `asset/css/theme-variables.css`: every variable with notes — the palette and fonts to change,
the derived colours and how they are made, the sizing variables for reading in custom CSS. Copy the lines you
need into the site's custom CSS, inside the same `@supports` block; loading the file as it is changes nothing.

## Browsers

Browsers older than early 2023 cannot mix colours in CSS. They show the theme's built palette throughout: the
palette stylesheet and the derived-colour definitions both sit inside `@supports (color: color-mix(…))`.
