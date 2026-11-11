<?php declare(strict_types=1);
/**
 * Agile patch imageserver-no-fast-tile-on-remote-store (ImageServer): No fast tile script on an external store.
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH imageserver-no-fast-tile-on-remote-store".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'imageserver-no-fast-tile-on-remote-store',
    'module' => 'ImageServer',
    'level' => 'required',
    'title' => 'No fast tile script on an external store',
    'why' => 'Opening the Image Server config page writes a rewrite rule into .htaccess that sends IIIF image requests to a script reading originals from the local disk.',
    'impact' => 'Without it, on a site with media on S3, opening the Image Server config page breaks every IIIF image (404 "Image file not found").',
    'applies_from' => null,
    'fixed_in' => null,
    'when_module' => null,
    'upstream' => '3.6.26+ skips it when an external IIIF server is set, or with imageserver_htaccess_skip; not for an external file store. To report.',
    'legacy' => ['PITTS PATCH: never install the fast tile script'],
    'variants' => [
        [
            'versions' => ['3.6.25', '3.6.26', '3.6.27'],
            'edits' => [
                [
                    'file' => 'Module.php',
                    'search' => <<<'AGILEPATCH'
        $scriptPath = __DIR__ . '/data/scripts/iiiftile.php';
        if (!file_exists($scriptPath)) {
            return;
        }

        $htaccessPath = OMEKA_PATH . '/.htaccess';
        $htaccess = @file_get_contents($htaccessPath);
        if ($htaccess === false) {
            return;
        }

        $marker = '# Module ImageServer: fast IIIF tile server.';
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
        $scriptPath = __DIR__ . '/data/scripts/iiiftile.php';
        if (!file_exists($scriptPath)) {
            return;
        }

        $htaccessPath = OMEKA_PATH . '/.htaccess';
        $htaccess = @file_get_contents($htaccessPath);
        if ($htaccess === false) {
            return;
        }

        $marker = '# Module ImageServer: fast IIIF tile server.';

        // AGILE-PATCH imageserver-no-fast-tile-on-remote-store: never install the fast tile script when originals are not on
        // local disk. iiiftile.php bypasses Omeka and reads files/original/ from the
        // filesystem, so with a remote store (S3) it answers EVERY IIIF image request
        // with a 404, and this method WRITES its rule into .htaccess merely because an
        // admin opened the config page. See AgileThemeTools docs/PATCHES.md.
        if (!($services->get('Omeka\File\Store') instanceof \Omeka\File\Store\Local)) {
            // An ACTIVE rule only: a commented-out one is exactly the fix.
            if (preg_match('/^[ \t]*RewriteRule\b[^\n]*iiiftile\.php/m', $htaccess)) {
                $messenger->addError(new PsrMessage(
                    'The fast IIIF tile script is enabled in .htaccess, but files are not stored locally, so it cannot find any image and every IIIF image request fails. Remove the rules starting with "{marker}" from .htaccess.', // @translate
                    ['marker' => $marker]
                ));
            }
            return;
        }
AGILEPATCH,
                ],
            ],
        ],
    ],
];
