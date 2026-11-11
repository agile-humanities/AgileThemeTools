# Changelog

All notable changes to Agile Theme Tools are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## [2.1.0] — unreleased

### Added

- Agile patches (docs/PATCHES.md): 13 exact fixes to IIIF Server, Image Server, Search Solr, Advanced Search, Vips and
  Amazon S3, mostly for media on Amazon S3, shipped in `data/patches/` with variants for every supported module
  version (from the `minimum` of `config/dependencies.php`), each verified against the stock releases.
  `iiifserver-no-synthetic-tiles` covers the IIIF Image API 3 info.json too (Universal Viewer reads it; with a tile
  grid on an untiled S3 image each 1024px tile timed out after 30 s).
- The patch audit: the dashboard and Modules page panel lists the required patches not in place; the configuration
  page has a "Patches to third-party modules" table; the Easy Admin check logs every patch.
- Easy Admin > Check and fix > Agile modules > "Apply the Agile patches" (job `ApplyPatches`): permission preflight,
  lint, backup of the originals in `files/agile-patches/backups`, atomic writes, check after writing.
- `data/scripts/patches.php`: `status`, `apply [--dry-run] [--allow-unverified]`, `verify <stock-dir>`, with no
  Omeka bootstrap, for deployments and for maintaining the patches.

### Changed

- Search templates (`view/search/agile/search.phtml`): the facet position is read from `facet.position` only, as
  Advanced Search 3.4.64+ (the minimum) stores it; the fallback to the pre-3.4.64 `results.facets` is removed.
- Mirador preset: the full Pitts configuration (custom dark palette with "mode" for Mirador 4, osdConfig, thumbnail
  strip, workspace settings) and the download plugin (dl) with image-tools.
- IIIF Server and Image Server have a minimum version (3.6.32, 3.6.25): the oldest versions the patches were
  verified on. Advanced Media 1.1.0 is the tested version.

## [2.0.1] — 2026-11-10

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
