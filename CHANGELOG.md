# Changelog

All notable changes to Agile Theme Tools are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## [2.0.1] — unreleased

Fixes found by aligning the Pitts installation with the 2.0.0 release (characterization tests, 2026-11-10).

### Fixed

- Item pages failed with a fatal error ("Class SearchSolr\Stdlib\SolrCore not found") on installations with
  Search Solr older than 3.5.70: Solr field maps are now read through the old `solr_cores` API when the new class
  is missing.
- The search pages preset skips the Solr page, with a message, when Search Solr is older than 3.5.70, instead of
  failing half-way.

### Changed

- Search Solr is listed as required whenever a search engine uses Solr (condition `solr-search`), instead of
  recommended.
- Advanced Media 1.0.1 is the tested version.

### Removed

- The controller override for BibLibre's Search module (`Search\Controller\IndexController`) and
  `AgileSearchIndexController`: search is Advanced Search's, and nothing routed to them.

## [2.0.0] — 2026-10-30

First public release of the 2.x line, alongside the Advanced Agile Base Theme 2.0.0, the Simplified Agile Base
Theme 2.0.0, Advanced Media 1.0.0 and Static Sitemaps 1.0.0. Licence: CeCILL v2.1.

### Added

- Theme identity: a theme declares itself with `agile_base`, `agile_base_version`, `agile_theme_tools` and
  `agile_build` keys in `theme.ini`; the module recognises Agile themes by them (docs/THEME-IDENTITY.md).
- Per-site theme builds from the admin: preflight checks (Node.js, npm, theme folder, permissions), "Install theme
  dependencies (npm install)" and "Build this site" as jobs, builds written under `files/theme-assets/`, served by
  the `siteAssetUrl` helper with a per-build version string; Easy Admin "Theme builds" tasks (docs/THEME-BUILD.md).
- Configure theme styles (palette, fonts, type scale, layout) for themes that build; Colours and fonts, applied
  without a build, for themes whose stylesheet reads its palette from CSS variables (the Simplified theme).
- Colour roles and expressions (`ensure-contrast`, `on-colour`, `neutral`, `mix`) with contrast checks of the
  guaranteed text/surface pairs; brand-approved tints for the derived brand-as-text roles.
- Custom CSS layers: for all sites (global setting) and per site, with a reference of the site's CSS variables.
- Agile search pages for Advanced Search (`view/search/agile/*`): facets, active filters, list/grid, mobile filter
  panel; the main search form adapter `agile`; Solr support through Search Solr.
- "Agile site setup" Easy Admin task with presets: item pages (resource page blocks and the Mirador preset),
  homepage, search pages (three pages with the same facets), block test pages.
- Dependency audit on the Modules page, with pinned and minimum versions (config/dependencies.php) and
  `data/scripts/dependency-smoke.php`.
- Card display mode and the `card()` helper for items, item sets and site pages; page summary block.
- Site-specific partials through `sitePartial` (`<template>-<site-slug>.phtml`).

### Changed

- Section listing block: "List - Simple" is a selectable template and the default; the item count returns the
  first N pages in navigation order; `getPagesBySection()` no longer loads every block of every child page.
- Homepage introduction: autoplay honours an unticked box.
- The module's block, item page and card templates have default versions for any theme; the Advanced theme
  overrides them and the Simplified theme carries the Advanced versions.

### Removed

- `cards.phtml` (unused). The Poster and Glossary blocks are withheld pending a redesign.
