<?php declare(strict_types=1);
/**
 * Agile patch imageserver-portable-base-url (ImageServer): Tile urls of a local store use the current host.
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH imageserver-portable-base-url".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'imageserver-portable-base-url',
    'module' => 'ImageServer',
    'level' => 'required',
    'title' => 'Tile urls of a local store use the current host',
    'why' => 'Image Server stores absolute tile urls: a base url taken from the request that installed, upgraded or configured it, and the url of each tiled image in its media data, taken from the request (or job) that tiled it. For a local file store, both are reused for every request.',
    'impact' => 'Without it, on a site with a local file store, deep zoom of tiled images fails when Image Server was installed or upgraded from the command line (the stored url has no host), or after a database copy from another environment (tiles are requested from that host).',
    'applies_from' => null,
    'fixed_in' => null,
    'when_module' => null,
    'upstream' => 'To report.',
    'legacy' => [],
    'variants' => [
        [
            'versions' => ['3.6.25', '3.6.26', '3.6.27'],
            'edits' => [
                [
                    'file' => 'src/Service/ControllerPlugin/TileInfoFactory.php',
                    'search' => <<<'AGILEPATCH'
                $baseUrl = $settings->get('imageserver_base_url', '');
                if ($baseUrl) {
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
                // AGILE-PATCH imageserver-portable-base-url: the stored base url ("imageserver_base_url") is the host of
                // whichever request saved it: empty ("http:/") when Image Server was installed or
                // upgraded from the command line, another environment's host in a copied database.
                // Tile urls of a local store were then broken or pointed elsewhere. Use the host of
                // the current request when there is one; the stored url only without, and only when
                // it has a host. See AgileThemeTools docs/PATCHES.md.
                $baseUrl = (string) $settings->get('imageserver_base_url', '');
                if (preg_match('~^https?://[^/]+~i', (string) $viewHelpers->get('ServerUrl')->__invoke())
                    || !preg_match('~^https?://[^/]+/~i', $baseUrl)
                ) {
                    $baseUrl = '';
                }
                if ($baseUrl) {
AGILEPATCH,
                ],
                [
                    'file' => 'src/Mvc/Controller/Plugin/TileMediaInfo.php',
                    'search' => <<<'AGILEPATCH'
        $tileData = $media->mediaData();
        if (empty($tileData['tile'])) {
            return null;
        }
        return $format
            ? $tileData['tile'][$format] ?? null
            : reset($tileData['tile']);
    }
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
        $tileData = $media->mediaData();
        if (empty($tileData['tile'])) {
            return null;
        }
        $tileInfo = $format
            ? $tileData['tile'][$format] ?? null
            : reset($tileData['tile']);
        // AGILE-PATCH imageserver-portable-base-url: the tiling data cached in the media data keeps the tile url of the
        // request that tiled the image (none for a job, another host after a database copy).
        // For a local store (path_base is a directory), compute it for the current request.
        // See AgileThemeTools docs/PATCHES.md.
        if (is_array($tileInfo) && strpos((string) ($tileInfo['path_base'] ?? ''), '/') === 0) {
            $tileInfo['url_base'] = $this->agileLocalTileBaseUrl($media, (string) ($tileInfo['url_base'] ?? ''));
        }
        return $tileInfo;
    }

    /** AGILE-PATCH imageserver-portable-base-url: the tile base url of a local store for the current request. */
    protected function agileLocalTileBaseUrl(AbstractResourceEntityRepresentation $media, string $stored): string
    {
        try {
            $services = $media->getServiceLocator();
            $tileDir = (string) $services->get('Omeka\Settings')->get('imageserver_image_tile_dir', 'tile');
            $baseUri = $services->get('Config')['file_store']['local']['base_uri'] ?? null;
            if ($baseUri) {
                return rtrim((string) $baseUri, '/') . '/' . $tileDir;
            }
            $viewHelpers = $services->get('ViewHelperManager');
            $serverUrl = (string) $viewHelpers->get('ServerUrl')->__invoke();
            if (preg_match('~^https?://[^/]+~i', $serverUrl)) {
                return $serverUrl . $viewHelpers->get('BasePath')->__invoke('files/' . $tileDir);
            }
        } catch (\Throwable $e) {
            // Keep the stored url.
        }
        return $stored;
    }
AGILEPATCH,
                ],
            ],
        ],
    ],
];
