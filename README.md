# What is this module for?
The Agile Theme Tools is intended to support Agile Omeka-S build system, which includes a suite of third-party modules alongside the Agile Advanced Base Theme. It also supports the Agile Simplified Base Theme. 

It offers a number of customized blocks to expand content editing capabilities, including a Slide Viewer,  Sectional Nagigation, Callouts, and Introductory Sections. The module offers administrators a series of Easy Admin Tasks to perform basic site setup.

It should be noted that the Agile Advanced Theme and build system is targeted at experienced administrators and front-end developers. The Agile Simplified Theme provides a clean base theme with similar styling for all Omeka S administrators with no third-party module dependencies.


# Who is this module for and what does it do?
| Audience | Gets |
|---|---|
| **Administrator** | Dependency audit (module config page, dashboard, Modules page, Easy Admin); global settings (custom CSS, basic styles, Node path, cards, item media, unlinked terms); Easy Admin tasks (dependency check, Mirador preset, theme builds, site setup presets); site Theme page additions (identity panel, build panel, logo check, Colours and fonts, Configure theme styles); `build-site-themes.php` CLI. |
| **Site editor** | 18 page block layouts (section 2) plus the card display used by listings; item-page composition from resource page blocks; per-site settings (custom CSS, card overrides, primary-media switch); item-page region choice. |
| **Themer** | Region system for blocks; template overrides for blocks, cards, search, item pages; `theme.ini` declaration keys; `siteAssetUrl`, `themeFonts()->inject()`, `customCss()->inject()`; palette via CSS variables; build system (gulp) contract; helpers and settings keys. |
| **Developer** | Display-mode registry; Advanced Search form adapter; form elements and `formLayout`; BuildManager, ThemeProfile, DependencyAudit; presets; scripts in `data/scripts`. |

## Who made this module?
This module was made by Bill Kennedy at Agile Humanities Agency. Some of the functionality was supported by the [Pitts Theological Library](https://digital.pitts.emory.edu) at Emory University and the [Creativity and COVID-19](https://creativityandcovid19.org) project at Michigan State University.

## Dependencies
* Omeka 4.1+
* NPM 19+ for command line theme building support
* [Agile Base Theme]( https://github.com/agile-humanities/AgileBaseOmekaTheme), or a theme built on its codebase
* A standard module suite: AdvancedSearch Common, IiifServer, ImageServer, and Mirador
* Optionally SearchSolr for Solr-based search implementations

## Installation
* Download the release version of “AgileThemeTools", or the codebase renamed as “AgileThemeTools”
* Place in Omeka ./modules folder.
* Run "npm install" inside AgileThemeTools folder to install third-party libraries in the non-release version.
* Ensure that all dependencies are installed and activated in Omeka.

## Note on Advanced Search
* The Advanced Search module has been in a fairly rapid release cycle, which can cause some instability as features are added and critical templates are changed. The Agile Suite (Agile Theme Tools + Agile Base Theme) is currently tested for Version 3.4.66.

# Additional Details

## Regions
The Agile build system introduces “regions” to Omeka-S content page, following a similar convention in the Drupal CMS. Regions are specific areas of a page supported by templates. They include a Splash/Hero area, as well as alternative content sections for better content organization. Supported blocks can be assigned to regions.

## Content Blocks
The Agile Theme Tools introduces the following content blocks.

| Key | Label | Options / data | Template | Notes |
|---|---|---|---|---|
| `pageTitle` | Page title | none | `page-title.phtml` | Renders the page title. Template reads an unset `$blockId`. |
| `iiifPresentation` | (core's) | core options | core fallback | **Replaces Omeka's block**: Mirador if its helper exists, else Universal Viewer, else core. Wrapper class `iiif-presentation--mirador/--uv/--core`. |
| `html` | HTML | `html`, `divclass` | built inline in `Html::render()` | Overrides core. `html.phtml` exists but is never used. |
| `regionalHtml` | Regional HTML | `html`, `region` | `regional-html.phtml` | Body text placed in a region. |
| `regionalTitle` | Regional Title | `title`, `html` (the "Deck"), `region` | `regional-title.phtml` | h1 plus deck. Bug: `title` is not purified, `html` is. |
| `deck` | Deck | `html`, `region` | `deck.phtml` | Standfirst paragraph under the page title. |
| `byline` | Byline | `html`, `region` | `byline.phtml` | Attribution line. |
| `excerpt` | Excerpt | `html`, `region` | `excerpt.phtml` | Admin text says it is not shown on the page (it feeds cards); the template still renders it, so hiding depends on theme CSS. |
| `callout` | Callout | `html`, `callout`, `position` (left/right/centre, `PositionSelect`), `region` | `callout.phtml` | Pull-quote beside body text. Loads `regional_html_handler.js` from a non-existent module `BlockOptions` (broken asset URL). |
| `quotation` | Quotation | `quotation`, `attribution` | `quotation.phtml` | No region. Attribution is not purified. |
| `contributorList` | Contributor List | `html`, `region` | `contributor-list.phtml` | The editor writes the list as HTML. Its code also queries creators with `site_id => 1` hard-coded (the result is not relied on by the form). Uses the Byline admin partial. |
| `pageSummary` | Page summary | `summary`, `byline`, `label`, `show` (0/1); 1 attachment | `page-summary.phtml` | The 2.0 way to describe a page for listings/cards: representative image (attach item or media), summary, byline, label. Renders on the page only if "Show on the page" is ticked. |
| `representativeImage` | Representative Image | `region`; 1 attachment | `representative-image.phtml` | Older; read by cards when no Page summary exists. Admin text says it is hidden, but it renders the thumbnail (`splash` size in the splash region, else `large`). |
| `slideshow` | Slide Viewer | `title`, `hastitle`, `subtitle`, `labelText`, `introduction`, `buttonText`, `slidesperrow`, `autoplay`, `fade`, `region`, `show_title_option`; attachments | `slideshow.phtml` (+ partial `agile-item/slide-item`) | Slick-based custom elements `slide-viewer`/`slide-item` (`asset/js/slick.js`, `00_agileComponent.js`, `slideViewerComponent.js`). Audio attachments become audio controls, others slides. Optional title slide uses the first image. Cross-fade default on *(uncommitted)*. |
| `homepageSplash` | Homepage Introduction | as Slide Viewer plus `composition` (`SlideViewerCompositionSelect`: `slide-viewer-with-title-default`, `-overlay`, `-grid`) and `slidestoshow` | `homepage-introduction.phtml` | Site title over a slide viewer. The module's template hard-codes `/s/pathways/browse` and "Browse our collections"; the Advanced theme overrides it with a site setting. `homepage-introduction-orig.phtml` is an unused older copy. |
| `sectionIntroSplash` | Section Introduction Splash | `title`, `introduction`, `alignment` (left/right), `region` (new blocks default to splash), `show_title_option`; attachments | `section-intro-splash.phtml` | Splash image + intro for a section page; slider scripts come from the theme. |
| `itemListing` | Item Listing with Introduction | `title`, `introduction`, `buttonText`, `buttonPath`, `classes`, `thumbnail_type`, `region`; attachments (the items) | `item-listing.phtml` | Cards for attached items. Template reads `$regionClass`, which render never passes. `onHydrate` purifies an `itemCount` the form lacks. |
| `linkCards` | Site Link Cards | `title`, `introduction`, `buttonText`, `buttonPath`, `classes`, `thumbnail_type`, `region`, and up to **3** cards stored as `itemLabel_N`, `itemTitle_N`, `itemSubTitle_N`, `itemThumbnailUrl_N`, `itemDescription_N`, `itemUrl_N` | `link-cards.phtml` | A card linking to a page of this site with no image borrows that page's Page summary / Representative image *(uncommitted)*. "View Exhibit" CTA text is hard-coded; description textarea name has a stray `]]`. |
| `sectionPageListing` | Section Listing | `section` (page slug, `SectionsMenuSelect` from the public nav tree), `template`, `includeCurrentPage`, `title`, `introduction`, `cardLabel`, `itemCount`, `classes`, `region` | the chosen variant | Lists the child pages of a section (via `Controller\SectionManager`).


### Section Listing
Agile Theme Tools supports the idea of site “sections” based on the Omeka-S navigation system. The Section list allows you to choose a page in the navigation system, which designates all child pages a part of a section. The block then allows you to create various types of navigation interfaces, which dynamically update as child pages get added.

#### Section Listing template variants

| Label | Path | Renders |
|---|---|---|
| Capsules | `common/block-layout/section-list-capsules` | hand-built capsule markup from `card()->prepare($page)` |
| Cards | `…/section-list-cards` | `$this->card($page, …)` per page |
| Feature | `…/section-list-feature` | prepared card, "more" link |
| List - Tabs | `…/section-list-tabs` | tab-style sub-menu |
| List - Vertical | `…/section-list-vertical` | vertical list |

## Resource page blocks and item pages

`resource_page_block_layouts` (all items only; classes in `src/Site/ResourcePageBlockLayout/`, templates in `view/common/resource-page-block-layout/`):

| Key | Label | What it renders | Template |
|---|---|---|---|
| `itemHeading` | Item Header | wayfinding, content type, title, subtitle, creator | `item-heading` — **not shipped by the module**; the Advanced theme provides it |
| `itemSummary` | Item Summary | summary, key terms, optional Full Metadata and Transcription tabs | `item-summary` — **theme only** (Advanced) |
| `itemFullMetadata` | Agile: Item Full Metadata | dark panel of values in the order of theme setting `full_metadata_terms` (empty = all, alphabetical) plus a public Collections list | `item-full-metadata.phtml` (module) |
| `itemMedia` | Agile: Item Media | Mirador for image primaries, Universal Viewer for PDFs, otherwise each media through its renderer (VTT/OHMS aware). For sites **without** Advanced Media | `item-media.phtml` (module) |
| `itemHeader` | Item Header (legacy) | pre-2.0 combined header; superseded by Heading + Summary | `item-header.phtml`; calls partial `agile-item/download-item-block` that only the Advanced theme has; `@todo` on site-URL derivation |

## Cards, Representational Image, and Excerpt
Cards are a common design structure that typically represent content with a thumbnail, title, excerpt, and link to the page. This module provides Card styling and supporting View Helpers. It also provides specialized blocks – Representative Image and Excerpt – that allow admins to assign thumbnails and descriptions to pages. These blocks are only displayed on Cards rather than on the pages proper.

## Dependency Support
The Agile Build system requires several third-party modules, including Advanced Media, Advanced Search, Mirador, IIIF Server, Image Server, and others. Agile Theme Tools often “pins” support to various module releases to ensure compatibility and support. The module offers an audit tool that will automatically inpect the currently installed modules for compatibility. Running a newer version of a module may not have any unexpected effects, but the dependency support shows admins which key module versions have been tested. 

## Item Browser / Advanced Search Support
The Agile Build system uses the Advanced Search module as its search interface. It supports its own Advanced Search form that provides recommended filters. It also supplies a pagintated faceted browsing interface for Advanced Search.

## Quick setup
The Agile build system uses a number of set configurations – including a homepage splash/hero area, our Advanced Search Item Browser, and an Item Page with specific Resource Blocks.

The module offers quick configuration support based on Easy Admin.

## Site-specific Template and Style Support
In some instances sites may want alternative templates and styles. The Agile Theme Tools module offers a sitePartial() View Helper as an alternative to the Partial() Helper to support this. 

To use it, place a copy named [template]-[site-slug].phtml beside the original in the theme (or in the module’s view folder) and that site renders it; every other site keeps the original. The Advanced theme routes its resource page blocks, homepage and listing blocks, cards and search results through this helper.”



