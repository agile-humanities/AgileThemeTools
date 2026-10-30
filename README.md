# Agile Theme Tools v2
The Agile Theme Tools is intended to support Agile Omeka-S build system, which includes a suite of third-party modules alongside the Agile Advanced Base Theme. It also supports the Agile Simplified Base Theme. 

It offers a number of customized blocks to expand content editing capabilities, including a Slide Viewer,  Sectional Nagigation, Callouts, and Introductory Sections. The module offers administrators a series of Easy Admin Tasks to perform basic site setup.

It should be noted that the Agile Advanced Theme and build system is targeted at experienced administrators and front-end developers. The Agile Simplified Theme provides a clean base theme with similar styling for all Omeka S administrators with no third-party module dependencies.


# Who is this module for?
| Audience | Gets |
|---|---|
| **Administrator** | Dependency audit (module config page, dashboard, Modules page, Easy Admin); global settings (custom CSS, basic styles, Node path, cards, item media, unlinked terms); Easy Admin tasks (dependency check, Mirador preset, theme builds, site setup presets); site Theme page additions (identity panel, build panel, logo check, Colours and fonts, Configure theme styles); `build-site-themes.php` CLI. |
| **Site editor** | 18 page block layouts (section 2) plus the card display used by listings; item-page composition from resource page blocks; per-site settings (custom CSS, card overrides, primary-media switch); item-page region choice. |
| **Themer** | Region system for blocks; template overrides for blocks, cards, search, item pages; `theme.ini` declaration keys; `siteAssetUrl`, `themeFonts()->inject()`, `customCss()->inject()`; palette via CSS variables; build system (gulp) contract; helpers and settings keys. |
| **Developer** | Display-mode registry; Advanced Search form adapter; form elements and `formLayout`; BuildManager, ThemeProfile, DependencyAudit; presets; scripts in `data/scripts`. |

## Dependencies
* Omeka 4.1+
* [Agile Base Theme]( https://github.com/agile-humanities/AgileBaseOmekaTheme), or a theme built on its codebase
* A standard module suite: AdvancedSearch ([Version 3.4.38](https://github.com/Daniel-KM/Omeka-S-module-AdvancedSearch/releases/download/3.4.38/AdvancedSearch-3.4.38.zip)), Common, IiifServer, ImageServer, and Mirador
* Optionally SearchSolr for Solr-based search implementations

## Note on Advanced Search
* The Advanced Search module has been in a rapid release cycle in 2024 and into 2025, which can cause some instability as features are added and critical templates are changed. The Agile Suite (Agile Theme Tools + Agile Base Theme) is currently tested for [Version 3.4.38](https://github.com/Daniel-KM/Omeka-S-module-AdvancedSearch/releases/download/3.4.38/AdvancedSearch-3.4.38.zip) released on January 20, 2025.
* Upgrading to a new version of Advanced Search may cause instability.
* We will periodically stabilize our Advanced Search integration with new releases.

## Features
* Provides a series of content blocks for site builders using the Agile Base Theme. This includes:
  * A section page listing block that allows you to present a configurable set of links to sub pages. This allows you to create subnavigation for sections of your site. 
  * A slideshow block based on the Slick javascript library.
  * A contributor list block to list all creators on the site.
  * A quotation block with attribution intended for an impactful design treatment.
  * A callout block to present an excerpt in the margin of the body text.
  * A series of editorial blocks to provide consistent class names for a variety of content elements (bylines, a title deck, etc.)
* Integrates with the Advanced Search Module to provide a series of helper functions for templating, including
  * A method for linking metadata back to a filtered search/browse page
  * Presentation support for both Solr and Internal Query searches
  * A date handler which uses fuzzy logic to provide consistent output for a variety of date styles (in progress)
* Other programmatic support for sites using the [Agile Base Theme]( https://github.com/agile-humanities/AgileBaseOmekaTheme)

## Site theme builds and custom CSS

Fonts are chosen per role (Google Fonts loaded automatically and checked on save, bundled, system or alias). Administrators can also add plain CSS for all sites (Settings → Theme styling and builds) or for one site (site
Edit → Theme styling), with a reference of the theme's CSS variables; no build is needed.

Per-site styling settings (Theme → Configure theme styles) are compiled into a site's own CSS/JS by the theme's gulp
build, run from Omeka as a job. Builds live under `files/theme-assets/<theme>/<site>/`, are verified and recorded in a
manifest, and are managed from the build panel on the site's Theme page. See [docs/THEME-BUILD.md](docs/THEME-BUILD.md)
for the layout, the command-line equivalent, deployment requirements (Node.js, `npm install` in the theme on the
server) and troubleshooting.

## Note on the Agile Base Theme Dependency
* The module is intended to provide programmatic support for the theme and its presentation features but can be used independently.
* Most of the styling for various content features is built in to the theme and not the module, however, so some presentation features will not work optimally.

## INSTALLATION
* Download the release version of “AgileThemeTools", or the codebase renamed as “AgileThemeTools”
* Place in Omeka ./modules folder.
* Run "npm install" inside AgileThemeTools folder to install third-party libraries in the non-release version.
* Ensure that all dependencies are installed and activated in Omeka.
