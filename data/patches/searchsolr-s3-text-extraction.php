<?php declare(strict_types=1);
/**
 * Agile patch searchsolr-s3-text-extraction (SearchSolr): Index text files stored on an external store.
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH searchsolr-s3-text-extraction".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'searchsolr-s3-text-extraction',
    'module' => 'SearchSolr',
    'level' => 'required',
    'title' => 'Index text files stored on an external store',
    'why' => 'SearchSolr reads text media (transcripts, VTT, plain text) from the local files directory only.',
    'impact' => 'Without it, with media on S3, the text of transcripts and other text files is not indexed, so it cannot be searched (and each indexed item logs a warning).',
    'applies_from' => null,
    'fixed_in' => null,
    'when_module' => null,
    'upstream' => 'To report.',
    'legacy' => ['remotely stored media'],
    'variants' => [
        [
            'versions' => ['3.5.69', '3.5.70', '3.5.71', '3.5.72', '3.5.73', '3.5.74', '3.5.75', '3.5.76'],
            'edits' => [
                [
                    'file' => 'src/ValueExtractor/AbstractResourceEntityValueExtractor.php',
                    'search' => <<<'AGILEPATCH'
            $filePath = $this->baseFilepath . '/original/' . $media->filename();
            return [file_get_contents($filePath)];
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
            $filePath = $this->baseFilepath . '/original/' . $media->filename();
            // AGILE-PATCH searchsolr-s3-text-extraction: media on an external store (Amazon S3) have no local
            // original: read the file from its url. See AgileThemeTools docs/PATCHES.md.
            if (!file_exists($filePath)) {
                $filePath = $media->originalUrl();
            }
            return [file_get_contents($filePath)];
AGILEPATCH,
                ],
            ],
        ],
    ],
];
