# Site theme builds

AgileThemeTools builds a site's theme assets (CSS, JavaScript, images, fonts) from the styling settings an
administrator saves under **Theme → Configure theme styles**, using the theme's own gulp build. This document
describes how that works, where things live, and how to run and debug builds.

## Where builds live

Everything the web server writes goes under Omeka's `files/` directory, the one place the PHP user is guaranteed to
be able to write. The theme directory itself is only ever read.

```
files/theme-assets/
  .home/                                 HOME for the build tools (npm caches etc.)
  <theme>/
    .build.lock                          one build of a theme at a time
    <site slug>/                         the live build for a site
      config.yml                         the settings the build was made from, as YAML
      build.log                          full build output
      build-manifest.json                what was built, from what, when, by whom
      assets/                            gulp's output: css/ js/ img/ fonts/
    <site slug>.building/                a build in progress (deleted or renamed when it ends)
    <site slug>.previous/                the build before the current one
    <site slug>.failed/                  the last failed build, with its log and manifest
```

Builds are staged in `<slug>.building` and only swapped into place after verification, so a failed build never
leaves a site without assets. The `siteAssetUrl` view helper serves a file from `assets/` when it exists there,
otherwise from an old `asset-<slug>` folder inside the theme (builds made before this system), otherwise from the
theme's own `asset/` folder. Site asset URLs carry a version string derived from the manifest, so browsers refetch
them after every rebuild.

## What "up to date" means

`build-manifest.json` records a hash of the settings and build options the build used (`configHash`) and a
fingerprint of the theme sources (`sourceFingerprint`: newest modification time and file count under `source/`,
plus the build files and theme version). The panel compares those with the current values. **Timestamps of built
files are never used**: gulp copies source-file timestamps onto its output, so a fresh build can carry old dates.

## The build panel

The panel appears on a site's **Theme** page and at the top of **Configure theme styles**. It shows the last build,
whether a rebuild is needed and why, any problem that would stop a build (with the command to fix it), the build
buttons, and the build output. While a build runs it polls `theme-styling/status` and updates itself.

- **Build this site** queues a `BuildSiteTheme` job for the site.
- **Rebuild all N sites using this theme** queues one job that builds every site using this theme that has
  *Use theme configuration* turned on in its theme settings, one after another.
- Saving the styling form no longer builds. It saves the settings and the panel reports *Rebuild needed*.

## Easy Admin

When the Easy Admin module is active, **Easy Admin → Check and fix** gets a **Theme builds** group with three tasks:

- **Check that site themes can be built**: runs the preflight for every site with per-site styling and reports each
  check, plus whether the site's build is up to date, without building anything.
- **Build the theme of one site**: pick a site (only sites with *Use theme configuration* on are listed).
- **Build the themes of all sites with per-site styling**: one job, sites built one after another.

All three run the same `BuildSiteTheme` job as the build panel; results are in the job log.

## The styling form

Colour settings on **Configure theme styles** are edited with a colour control: the value stays a text field (a hex
or named colour, or a theme-system expression such as `$clr2`, `neutral(40)`, `set-color(2, 50)`,
`mix($white, $clr2, 10%)`), with a live swatch, a native colour picker that writes a hex value into the field, the
resolved colour when the value is an expression, and, for foreground/background pairs (menu text on menu background,
button text on button background, links on the site background, and so on), a WCAG contrast readout: AAA (≥ 7:1),
AA (≥ 4.5:1), AA large text only (≥ 3:1) or fails. A strip under the Palette legend shows all eleven palette
colours as they are typed. Under the core values, a preview shows the type sizes the modular scale produces from
the base size, and the spacing steps.

Resolution mirrors the theme system: `$name` reads the other field, `neutral(n)` mixes blackpoint and whitepoint,
`set-color(n, i, mixer)` mixes colour n with the mixer, `mix()` is SASS's per-channel average. Anything the
resolver does not know (for example `clamped()`) shows no swatch; the build still handles it.

## Fonts

The four font roles on **Configure theme styles** — main text, headings, alternative, labels and interface — are
each set with a font control: a **source** (Google Fonts; bundled with the theme; a system font or one loaded some
other way; the same as another role), a **family** with suggestions for the chosen source (popular Google
families with their category; the theme's bundled families, read from `source/sass/00_configuration/
_04_typography_fonts.sass`; common system stacks), a generic **fallback**, and a **preview** that loads the font in
the admin and reports whether it loaded. What is saved is still the CSS declaration the build expects
(`"Open Sans", sans-serif`, or `$headingfont` for an alias), plus a companion `<role>_source` setting.

For Google families the module emits the `preconnect` and stylesheet `<link>` tags on every page of the site
(`$this->themeFonts()->inject();` in the layout), with regular, bold and their italics requested, so nothing has to
be pasted. On save, each Google family is checked against Google's stylesheet endpoint: an unknown family is an
error (Google answers 400) and weights the family lacks are reported (Google silently drops them and the browser
would synthesise bold or italic). Results are cached for a day.

Sites configured before this existed keep working: their roles are inferred (`$other` → alias, a bundled family →
bundled, anything else → system) and a pasted Google embed in **Other font embed code** still loads them. Once a
role's source is switched to Google Fonts, remove the pasted code to avoid loading the font twice. Adding a font
to the theme itself (bundled) remains a developer task: font files under `source/fonts/<name>-<weight>/` and a
`+load_font(…)` line in `_04_typography_fonts.sass`. Changing a role's font needs a theme build.

Each control is collapsed by default: its summary line shows the role's font set in that font, its source and
whether the font loaded, and a control opens itself when its font fails to load. So bundled fonts preview (and
report) truthfully, the form adds `@font-face` rules for the theme's bundled families pointing at the site's built
fonts (woff2).

`+load_font` expects `<name>-<style>-<weight>.woff2` files in `source/fonts/<name>-<weight>/` and emits woff2 only
(`$formats: (woff2, woff)` adds sources for a project that must support IE11; the old all-formats loader is
`+load_font_legacy`). Make woff2 files by compressing the foundry's TTF (`woff2_compress font.ttf`), which keeps
hinting and OpenType features byte for byte; do not regenerate fonts through FontForge. A theme pinned to a library
release without this mixin carries it as `00_configuration/_03_load_font.sass`, to be deleted once the library
dependency includes it.

## Custom CSS layers

Two layers of plain CSS can be written in Omeka without touching the theme or running a build, loaded after the
theme's own stylesheets (including the theme's `custom.css`) in this order:

1. **Settings → Theme styling and builds → Custom CSS for all sites**: every site of the installation.
2. **Site settings (site → Edit) → Theme styling → Custom CSS for this site**: that site only, loaded last.

Each layer is written to `files/theme-assets/custom/` as `global-<hash>.css` or `site-<slug>-<hash>.css` the first
time a page needs it; the hash is the content's, so a change produces a new URL and browsers never keep a stale
copy. Older versions of a layer are removed. If the directory cannot be written, the CSS is inlined in the page
instead. A closing `</style>` in the CSS is neutralised; otherwise the CSS is used as written (`@import` of a font
service is fine).

Under each box, **CSS variables available** lists the custom properties the site's compiled stylesheet declares on
`:root` — colours (`--colour--1…9` with `--shade/--primary/--tint/--fade/--watermark`, `--colour--black/white/
impact/cta`, neutrals), spacing and type (`--space-N`, `--type-N`; negative steps are `n1…n4`), fonts, layout and
breakpoints — with colour swatches; clicking a name copies `var(--name)`. The list comes from the site's current
build, so it reflects the site's own palette once the site has been built. Spacing, type, font and layout
variables are exported by the theme's `source/sass/20_layout/_root_sizing_variables.sass`; colours and breakpoints
by the theme system itself.

A theme layout enables the layers with `$this->customCss()->inject();` after adding its own stylesheets.

## Preflight

Before running gulp, the build checks: the theme has `gulpfile.js`, `package.json`, `source/` and a valid
`_00_main_configuration.yml`; `node_modules/gulp/bin/gulp.js` exists; a `node` executable can be found and run;
the image-optimisation binaries in `node_modules` run on this platform; `files/theme-assets/<theme>` is writable by
the PHP user and no existing build contains files it cannot replace; a writable home directory; free disk space;
`proc_open` is available. Every failing check comes with the fix.

`node` is looked for at the path in **Settings → Theme build → Node.js executable** (a file or the directory that
holds it), else on the PHP user's `PATH`, else in `/usr/local/bin`, `/usr/bin` and `/opt/homebrew/bin`.

## Configuration format

The site's settings are written as YAML (`config.yml`) and passed to gulp with `--config`. The gulpfile converts
it, and the theme's `_00_main_configuration.yml`, to SASS variables in a temporary directory that is put first on
the SASS load path. Nothing is generated inside the theme's `source/` tree.

Conversion rules (the same as the theme system's original parser, minus its quirks): a YAML `null` becomes SASS
`null` (the theme system uses it to mean "off"); an empty string means "use the default" and is skipped; other values
are emitted as written, so `"$white"` stays a SASS reference and `"'../img'"` becomes `'../img'`. Hyphenated keys
such as `default-min-bp` are honoured. A file that is not valid YAML is read leniently, line by line, with a warning
in the build output; fix or regenerate such files.

## Command line

A developer can run the same build from the theme directory. A bare `--config` name is looked up in `alt-config/`
and built to `asset-<name>` inside the theme; a path is used as is:

```
./node_modules/.bin/gulp build
./node_modules/.bin/gulp build --config digital-collections
./node_modules/.bin/gulp build --config /path/to/config.yml --dest /path/to/output --sourceimgs images-pittsdc
```

Missing config files or source directories are errors (non-zero exit), never silent fallbacks, and the destination is
guarded against unsafe paths. **Run builds as the web server user** (for example `sudo -u www-data …`) when building
into `files/`; files created by another user cannot later be replaced by the web server, and the preflight will say so.

The settings saved in Omeka are the source of truth for a site: the next build regenerates `config.yml` from them.
A hand-edited `alt-config/<slug>.yml` is a developer's file and is not touched by builds.

## Deployment notes

- Node.js must be installed where the web server can run it, and `npm install` must have been run in the theme
  directory **on the server** (native binaries such as `optipng` are platform-specific).
- `files/theme-assets/` is created on first build; the whole `files/` directory must be writable by the PHP user.
- Sites built before this system have their assets in `themes/<theme>/asset-<slug>`. They keep working until the
  site is built here; after that the old folder can be deleted (the panel says so).
- Old builds are not deleted automatically; `<slug>.previous` and `<slug>.failed` are kept for inspection.

## Troubleshooting

- **The panel says a build failed.** Open *Build output*; the error line is quoted in the headline. The same output
  is in the job's log (Admin → Jobs) and in `<slug>.failed/build.log`.
- **The panel says "Building…" but nothing happens.** Check Admin → Jobs for the job. A job that never ended (for
  instance PHP was killed) stops counting as running after about 25 minutes.
- **Permission errors.** The preflight names the directory and the user, and prints the `chown`/`chmod` to run.
  Builds started from the command line as root are the usual cause.
- **Spurious "Rebuild needed" after a deploy.** Expected: a checkout changes source timestamps, so the source
  fingerprint changes. Rebuild.

## Logo check

Themes built on this system place logos in short headers and banners, over backgrounds the site can recolour, so a
logo needs to be wide and to have a transparent background. The site's **Theme** page and the theme settings form
show a **Logo check** box when a chosen logo does not fit (`Styling\LogoAudit`, `themeLogoAudit()` view helper):

| Finding | Level | Meaning |
| --- | --- | --- |
| tall | warning | Taller than wide. The theme script gives it the `img-portrait` class, but it will be small or crowd the navigation. |
| squarish | note | Wider than tall but under 1.5:1. |
| opaque | warning | A raster image with no transparent pixel along its edge (every JPEG; a PNG, GIF or WebP with a solid box). The background colour is reported when it is uniform. |
| heavy | note | Over 300 KB or wider than 2400 pixels. |

SVG logos are checked for proportions only (from `viewBox`, else `width`/`height`). The thumbnail in the box sits on
a checkerboard so a solid background is visible at a glance. Nothing is shown when every logo passes, and nothing is
blocked: these are advisories.

A theme setting is audited when it is an `Omeka\Form\Element\Asset` element in `config/theme.ini` whose key or name
contains "logo"; set `elements.<key>.options.logo_audit = true` (or `false`) to include another image or exclude
one. Files are read from the local store, or over HTTP when assets are stored elsewhere (e.g. S3).
