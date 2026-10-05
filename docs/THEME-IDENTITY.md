# Theme identity

Agile Theme Tools recognises an Agile theme by what the theme declares in its `config/theme.ini`. It never uses
the theme's folder name or display name. A theme can therefore be copied, renamed and edited — a *fork* — and
keep working, as long as it keeps its declaration.

## The keys

They go in the `[info]` section of `config/theme.ini`, beside `name` and `version`.

| Key | Required | Meaning |
|---|---|---|
| `agile_base` | yes | The Agile base theme this theme is, or was forked from. `agile-base` for the Advanced Agile Base Theme and its forks; `agile-base-simplified` for the Simplified theme and its forks. A fork keeps the value of the theme it came from. |
| `agile_base_version` | recommended | The version of that base theme this theme corresponds to. In the base theme it is the theme's own version. In a fork it is the base version the fork was made from, or last brought up to date with. |
| `agile_theme_tools` | recommended | The Agile Theme Tools versions the theme works with, as a composer-style constraint, e.g. `^2.0`. |
| `agile_build` | when it applies | `gulp` if the theme can be rebuilt per site from its SASS sources. Leave the key out for a theme without a build. |
| `agile_build_config` | no | The theme's default styling configuration. Default: `_00_main_configuration.yml`. |
| `agile_build_source` | no | The source directory (SASS, scripts, fonts, images). Default: `source`. |
| `agile_build_fonts` | no | The SASS file that loads bundled fonts with `+load_font`. Default: `<source>/sass/00_configuration/_04_typography_fonts.sass`. |

Paths are relative to the theme folder. A path that is absolute or contains `..` is refused, the default is used
instead, and the problem is reported.

Example, as shipped in the Advanced Agile Base Theme:

```ini
[info]
name = "Advanced Agile Base Theme"
version = "2.0.0"
agile_base = "agile-base"
agile_base_version = "2.0.0"
agile_theme_tools = "^2.0"
agile_build = "gulp"
```

A fork that renamed its configuration file:

```ini
[info]
name = "My Museum Theme"
version = "1.0.0"
agile_base = "agile-base"
agile_base_version = "2.0.0"
agile_theme_tools = "^2.0"
agile_build = "gulp"
agile_build_config = "my-styles.yml"
```

## What the module does with them

- **Recognition.** Per-site styling, the styling form, font handling and the build all apply to any theme with
  `agile_base`, whatever it is called.
- **Per-site builds** are offered only when the theme declares `agile_build = "gulp"` *and* the site has "Use
  theme configuration" switched on in its theme settings.
- **Compatibility.** If the installed module does not satisfy `agile_theme_tools`, the site's Theme page says so
  and builds are refused until the module or the theme is changed.
- **Where it shows.** The site's Theme page has an "Agile theme" panel with the declaration and any problem.
  The same check is the first line of the build preflight, so it also appears in the build panel and in Easy
  Admin's "Check theme builds" task.

## Building from the command line

The gulpfile reads the default configuration from `_00_main_configuration.yml`. A theme that declares another
file passes it with `--mainconfig`; the module does this automatically for its own builds.

```bash
./node_modules/.bin/gulp build --mainconfig my-styles.yml
```

## Themes without the keys

A theme made before the keys existed is still recognised if it has the "Use theme configuration" setting and a
`_00_main_configuration.yml`. It keeps working, and the Theme page asks for the keys to be added. This fallback
exists so that existing sites survive a module update; new themes and forks should always declare themselves.

## Styling values belong to a base theme

A site's styling values (palette, fonts, sizes, and the alternative image and source folders) are stored per site.
The module records which base theme — the `agile_base` value — they were saved for, and uses them only with themes
of that base.

- A site that moves between a base theme and a fork of it, or between two forks, keeps its styling.
- With a theme of another base the values are neither used nor changed. The styling page says so and shows the
  current theme's defaults. Saving that form replaces the saved values with ones for the current base.
- Values saved before this was recorded are taken to belong to `agile-base`.

## What a rename still affects

The declaration makes the module's behaviour independent of the folder name, but two stores are keyed by the
folder name and are not carried over by a rename on their own:

- Omeka keeps a site's theme settings (logo, footer, card terms) under the theme's folder name.
- Per-site builds are stored under `files/theme-assets/<folder>/<site>`, so a renamed theme has to be rebuilt.

In the code: `AgileThemeTools\Theme\ThemeProfile`.
