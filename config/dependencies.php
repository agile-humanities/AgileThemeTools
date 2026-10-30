<?php
/**
 * The modules the Agile theme stack depends on, and the versions it is tested with.
 *
 * THE ONE LIST. Everything else is derived from it or checked against it: the "dependencies" line of
 * config/module.ini (data/scripts/dependencies.php check), the audit shown in the admin
 * (Dependency\DependencyAudit), the refusal to install without the required modules (Module::install), and the
 * table in docs/DEPENDENCIES.md (data/scripts/dependencies.php markdown).
 *
 * Per module (the array key is the module's folder name, which is its id in Omeka):
 *
 *   label       Name shown to administrators.
 *   level       required     the stack does not work without it; Agile Theme Tools refuses to install without it
 *               recommended  installed by the setup, used when present
 *               optional     an integration that is used when the module happens to be installed
 *   when        Makes "required" conditional. "internal-search": required only while a site's main search page
 *               uses Advanced Search's internal (SQL) engine. Otherwise the module is treated as optional.
 *   pinned      The version the stack is tested with, and the one the setup installs.
 *   minimum     Older versions are known not to work. Leave out when no such floor is known: a version between
 *               "minimum" and "pinned", or below "pinned" with no minimum, is reported as older than tested.
 *   volatile    true when new releases of the module often change templates, markup or stored settings. A
 *               version newer than "pinned" is then a warning rather than a note.
 *   known_bad   [version constraint => reason] for releases known to break the stack.
 *   for         What the stack uses it for (one line, shown in the audit and the docs).
 *   source      Project page.
 *   download    Release zip, with {version} where the version goes. Null when there is no public release yet.
 *
 * "omeka" is Omeka S itself.
 */

$danielKm = fn (string $name) => [
    'source' => "https://github.com/Daniel-KM/Omeka-S-module-$name",
    'download' => "https://github.com/Daniel-KM/Omeka-S-module-$name/releases/download/{version}/$name-{version}.zip",
];

return [
    'omeka' => [
        'label' => 'Omeka S',
        'level' => 'required',
        'pinned' => '4.2.1',
        'minimum' => '4.1.0',
        'for' => 'Resource page blocks and block templates.',
        'source' => 'https://omeka.org/s/',
        'download' => 'https://github.com/omeka/omeka-s/releases/download/v{version}/omeka-s-{version}.zip',
    ],

    // ------------------------------------------------------------------ required

    'Common' => [
        'label' => 'Common',
        'level' => 'required',
        'pinned' => '3.4.94',
        'minimum' => '3.4.91',
        'volatile' => true,
        'for' => 'Shared code required by Advanced Search and the other modules by the same author.',
    ] + $danielKm('Common'),

    'AdvancedSearch' => [
        'label' => 'Advanced Search',
        'level' => 'required',
        'pinned' => '3.4.66',
        'minimum' => '3.4.64',
        'volatile' => true,
        'for' => 'Search pages, filters and facets. Versions before 3.4.64 fail on the internal search engine and store search settings differently.',
    ] + $danielKm('AdvancedSearch'),

    'Reference' => [
        'label' => 'Reference',
        'level' => 'required',
        'when' => 'internal-search',
        'pinned' => '3.4.64',
        'minimum' => '3.4.58',
        'volatile' => true,
        'for' => 'Facets on Advanced Search’s internal engine. Not needed when every search page uses Solr.',
    ] + $danielKm('Reference'),

    'IiifServer' => [
        'label' => 'IIIF Server',
        'level' => 'required',
        'pinned' => '3.6.34',
        'volatile' => true,
        'for' => 'IIIF manifests for the image and document viewers.',
    ] + $danielKm('IiifServer'),

    'ImageServer' => [
        'label' => 'Image Server',
        'level' => 'required',
        'pinned' => '3.6.26',
        'volatile' => true,
        'for' => 'IIIF image delivery (deep zoom).',
    ] + $danielKm('ImageServer'),

    'Mirador' => [
        'label' => 'Mirador',
        'level' => 'required',
        'pinned' => '3.4.17',
        'minimum' => '3.4.9',
        'volatile' => true,
        // 3.4.17 made Mirador 4 the default and moved Mirador 3's settings to "_3" keys; its Mirador 3 bundle does
        // not draw images, so the Agile Mirador preset uses Mirador 4 from 3.4.17 (Setup\MiradorPreset).
        'for' => 'The default image and document viewer (Mirador 4 from 3.4.17, Mirador 3 before).',
    ] + $danielKm('Mirador'),

    'Log' => [
        'label' => 'Log',
        'level' => 'required',
        'pinned' => '3.4.42',
        'for' => 'Job and build logs readable in the admin.',
    ] + $danielKm('Log'),

    'EasyAdmin' => [
        'label' => 'Easy Admin',
        'level' => 'required',
        'pinned' => '3.4.50',
        'for' => 'Installing the stack, and the Check and fix page where builds, checks and setup tasks are run.',
    ] + $danielKm('EasyAdmin'),

    'AdvancedMedia' => [
        'label' => 'Advanced Media',
        'level' => 'required',
        'pinned' => '1.0.0',
        'for' => 'Media viewers, compound objects and downloads on item pages.',
        'source' => 'https://github.com/agile-humanities/AdvancedMedia',
        'download' => null,
    ],

    // ------------------------------------------------------------------ recommended

    'StaticSitemaps' => [
        'label' => 'Static Sitemaps',
        'level' => 'recommended',
        'pinned' => '1.0.0',
        'for' => 'Sitemap files for search engines.',
        'source' => 'https://github.com/agile-humanities/StaticSitemaps',
        'download' => null,
    ],

    'SearchSolr' => [
        'label' => 'Search Solr',
        'level' => 'recommended',
        'pinned' => '3.5.75',
        'minimum' => '3.5.70',
        'volatile' => true,
        'for' => 'Solr as the search engine, for larger collections. Advanced Search 3.4.64 needs 3.5.70 or later.',
    ] + $danielKm('SearchSolr'),

    // ------------------------------------------------------------------ optional integrations

    'NumericDataTypes' => [
        'label' => 'Numeric Data Types',
        'level' => 'optional',
        'pinned' => '1.14.3',
        'for' => 'Dates stored as timestamps or intervals are formatted for display.',
        'source' => 'https://github.com/omeka-s-modules/NumericDataTypes',
        'download' => 'https://github.com/omeka-s-modules/NumericDataTypes/releases/download/v{version}/NumericDataTypes-{version}.zip',
    ],

    'ItemSetsTree' => [
        'label' => 'Item Sets Tree',
        'level' => 'optional',
        'pinned' => '0.7.5',
        'for' => 'Collections shown as a tree in facets.',
        'source' => 'https://github.com/biblibre/omeka-s-module-ItemSetsTree',
        'download' => null,
    ],

    'Mapping' => [
        'label' => 'Mapping',
        'level' => 'optional',
        'pinned' => null,
        'for' => 'A themed map browse page. Not tested with a specific version yet.',
        'source' => 'https://github.com/omeka-s-modules/Mapping',
        'download' => null,
    ],

    'UniversalViewer' => [
        'label' => 'Universal Viewer',
        'level' => 'optional',
        'pinned' => '3.6.15-4.2.1',
        'for' => 'Paged documents and ePub in Advanced Media.',
    ] + $danielKm('UniversalViewer'),

    'AmazonS3' => [
        'label' => 'Amazon S3',
        'level' => 'optional',
        'pinned' => '3.4.4',
        'for' => 'Files stored in S3; Advanced Media can then serve them from the site’s own address.',
        'source' => 'https://github.com/Daniel-KM/Omeka-S-module-AmazonS3',
        'download' => null,
    ],
];
