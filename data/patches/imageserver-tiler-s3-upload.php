<?php declare(strict_types=1);
/**
 * Agile patch imageserver-tiler-s3-upload (ImageServer): Upload tiles to an external store.
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH imageserver-tiler-s3-upload".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'imageserver-tiler-s3-upload',
    'module' => 'ImageServer',
    'level' => 'required',
    'title' => 'Upload tiles to an external store',
    'why' => 'The tiler reads the tile suffixes from an ArchiveRepertory setting that does not exist by default, so it never uploads the tiles it builds to Amazon S3.',
    'impact' => 'Without it, tiling jobs complete but the tiles stay on the local disk, and every deep zoom request on S3 is processed dynamically from the original.',
    'applies_from' => null,
    'fixed_in' => null,
    'when_module' => null,
    'upstream' => 'To report.',
    'legacy' => ['[\'.dzi\', \'_files\', \'_zdata\']'],
    'variants' => [
        [
            'versions' => ['3.6.25', '3.6.26', '3.6.27'],
            'edits' => [
                [
                    'file' => 'src/Job/Tiler.php',
                    'search' => <<<'AGILEPATCH'
        $suffixes = $config['archiverepertory']['ingesters']['tile']['extension'];
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
        // AGILE-PATCH imageserver-tiler-s3-upload: ArchiveRepertory defines no "ingesters.tile" key by default, so
        // this was null and the loop below warned: tiles were built locally but never
        // uploaded to the external store, and every request fell back to dynamic
        // extraction. Fall back to the suffixes ImageServer itself creates. See AgileThemeTools docs/PATCHES.md.
        $suffixes = $config['archiverepertory']['ingesters']['tile']['extension'] ?? null;
        if (!is_array($suffixes) || !$suffixes) {
            $suffixes = ['.dzi', '_files', '_zdata'];
        }
AGILEPATCH,
                ],
            ],
        ],
    ],
];
