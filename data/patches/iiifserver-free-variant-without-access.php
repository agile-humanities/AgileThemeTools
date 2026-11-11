<?php declare(strict_types=1);
/**
 * Agile patch iiifserver-free-variant-without-access (IiifServer): Shareable manifests on sites without the Access module.
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH iiifserver-free-variant-without-access".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'iiifserver-free-variant-without-access',
    'module' => 'IiifServer',
    'level' => 'required',
    'title' => 'Shareable manifests on sites without the Access module',
    'why' => 'Since 3.6.34, IiifServer serves the manifest variant "all" to everybody when the Access module is absent, but only the variant "free" is sent as public and warmed by the cache job.',
    'impact' => 'Without it, no manifest can be cached by a CDN or a browser (Cache-Control: private, max-age=0), and the manifest cache job deletes the cache the site actually reads, so large manifests are rebuilt on every visit after an item save.',
    'applies_from' => '3.6.34',
    'fixed_in' => null,
    'when_module' => null,
    'upstream' => 'To report.',
    'legacy' => ['PITTS PATCH: without the module Access'],
    'variants' => [
        [
            'versions' => ['3.6.34', '3.6.35'],
            'edits' => [
                [
                    'file' => 'src/Controller/PresentationController.php',
                    'search' => <<<'AGILEPATCH'
        $accessFilter = $hasAccessModule
            ? $this->iiifAccessCache()->accessFilterForVisitor((int) $resource->id())
            : AbstractResourceType::ACCESS_FILTER_ALL;
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
        // AGILE-PATCH iiifserver-free-variant-without-access: without the module Access, use the variant "free", as the
        // job CacheManifests does. Upstream uses "all" here; without Access both
        // variants are the same manifest (nothing can be locked), but "all" is never
        // sent as public, so every anonymous manifest goes out "private, max-age=0"
        // and no CDN or browser caches any; and it is cached as
        // "<id>.allowed.manifest.json", which CacheManifests never writes and deletes
        // as an orphan on each run (one is dispatched by every item save). See AgileThemeTools docs/PATCHES.md.
        $accessFilter = $hasAccessModule
            ? $this->iiifAccessCache()->accessFilterForVisitor((int) $resource->id())
            : AbstractResourceType::ACCESS_FILTER_FREE;
AGILEPATCH,
                ],
            ],
        ],
    ],
];
