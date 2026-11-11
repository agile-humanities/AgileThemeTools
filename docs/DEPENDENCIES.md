# Modules the Agile theme system depends on

One file holds the list: `config/dependencies.php`. It says which modules are needed, how strongly, and which
version each was tested with. Everything else is derived from it or checked against it.

## Why the module checks this itself

Omeka S does not enforce dependencies between modules. The `dependencies` line in a module's `module.ini` is read
by Easy Admin's installer and by the module catalogues, but Omeka will happily activate a module whose
dependencies are missing. So Agile Theme Tools does the checking:

- **Install.** It refuses to install while a required module is missing, inactive or older than the oldest
  version that works, and says which.
- **Configuration page** (Modules → Agile Theme Tools → Configure). The full list: what is installed, what was
  tested, and the status of each.
- **Dashboard and Modules page.** A panel appears only when something is wrong.
- **Easy Admin** (Check and fix → Agile modules). The same audit written to the job log.

## What the statuses mean

| Status | Meaning | Shown as |
|---|---|---|
| The tested version | Installed version equals "tested with". | OK |
| Newer than tested | A later release than the one tested. For modules that change often this is a warning, otherwise a note. | Warning or note |
| Older than tested | Below "tested with" but not known to fail. | Warning |
| Older than the oldest that works | Known not to work. | Problem if the module is required |
| A release known to be broken | Listed under "Avoid" below. | Problem if the module is required |
| Missing, not installed or inactive | | Problem if required; note if recommended |
| Files newer than the database | The module was updated on disk but its upgrade has not been run. | Warning |

## The list

"Tested with" is also the version the setup installs.

<!-- BEGIN GENERATED TABLE: php data/scripts/dependencies.php docs -->
| Module | Needed | Tested with | Oldest that works | Used for |
|---|---|---|---|---|
| [Omeka S](https://omeka.org/s/) | Required | 4.2.1 | 4.1.0 | Resource page blocks and block templates. |
| [Common](https://github.com/Daniel-KM/Omeka-S-module-Common) | Required | 3.4.94 | 3.4.91 | Shared code required by Advanced Search and the other modules by the same author. |
| [Advanced Search](https://github.com/Daniel-KM/Omeka-S-module-AdvancedSearch) | Required | 3.4.66 | 3.4.64 | Search pages, filters and facets. Versions before 3.4.64 fail on the internal search engine and store search settings differently. |
| [Reference](https://github.com/Daniel-KM/Omeka-S-module-Reference) | Required with the internal search engine | 3.4.64 | 3.4.58 | Facets on Advanced Search’s internal engine. Not needed when every search page uses Solr. |
| [IIIF Server](https://github.com/Daniel-KM/Omeka-S-module-IiifServer) | Required | 3.6.34 | 3.6.32 | IIIF manifests for the image and document viewers. The oldest version the Agile patches were verified on is 3.6.32. |
| [Image Server](https://github.com/Daniel-KM/Omeka-S-module-ImageServer) | Required | 3.6.26 | 3.6.25 | IIIF image delivery (deep zoom). The oldest version the Agile patches were verified on is 3.6.25. |
| [Mirador](https://github.com/Daniel-KM/Omeka-S-module-Mirador) | Required | 3.4.17 | 3.4.9 | The default image and document viewer (Mirador 4 from 3.4.17, Mirador 3 before). |
| [Log](https://github.com/Daniel-KM/Omeka-S-module-Log) | Required | 3.4.42 | — | Job and build logs readable in the admin. |
| [Easy Admin](https://github.com/Daniel-KM/Omeka-S-module-EasyAdmin) | Required | 3.4.50 | — | Installing the stack, and the Check and fix page where builds, checks and setup tasks are run. |
| [Advanced Media](https://github.com/agile-humanities/AdvancedMedia) | Required | 1.1.0 | — | Media viewers, compound objects and downloads on item pages. |
| [Static Sitemaps](https://github.com/agile-humanities/StaticSitemaps) | Recommended | 1.0.0 | — | Sitemap files for search engines. |
| [Search Solr](https://github.com/Daniel-KM/Omeka-S-module-SearchSolr) | Required | 3.5.75 | 3.5.70 | Solr as the search engine, for larger collections. Required once a search engine uses Solr: item pages read its field maps. Advanced Search 3.4.64 needs 3.5.70 or later. |
| [Numeric Data Types](https://github.com/omeka-s-modules/NumericDataTypes) | Optional | 1.14.3 | — | Dates stored as timestamps or intervals are formatted for display. |
| [Item Sets Tree](https://github.com/biblibre/omeka-s-module-ItemSetsTree) | Optional | 0.7.5 | — | Collections shown as a tree in facets. |
| [Mapping](https://github.com/omeka-s-modules/Mapping) | Optional | — | — | A themed map browse page. Not tested with a specific version yet. |
| [Universal Viewer](https://github.com/Daniel-KM/Omeka-S-module-UniversalViewer) | Optional | 3.6.15-4.2.1 | — | Paged documents and ePub in Advanced Media. |
| [Amazon S3](https://github.com/Daniel-KM/Omeka-S-module-AmazonS3) | Optional | 3.4.4 | — | Files stored in S3; Advanced Media can then serve them from the site’s own address. |
<!-- END GENERATED TABLE -->

Reference is required only while a main search page uses Advanced Search's internal (SQL) engine, which is the
default. A site whose search pages all use Solr does not need it.

## Changing a pin

1. Update the module on a test installation and check the pages that use it.
2. Change `pinned` (and `minimum`, if older versions stop working) in `config/dependencies.php`.
3. Run `php data/scripts/dependencies.php check`, then `php data/scripts/dependencies.php docs`.

Advanced Search, Common and Search Solr move together: Advanced Search 3.4.64 needs Common 3.4.91 or later, and
will not upgrade unless Search Solr, when present, is 3.5.70 or later.

In the code: `AgileThemeTools\Dependency\DependencyAudit`.
