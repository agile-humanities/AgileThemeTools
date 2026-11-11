<?php declare(strict_types=1);
/**
 * Agile patch imageserver-remote-source-readable (ImageServer): Read originals on an external store (Amazon S3).
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH imageserver-remote-source-readable".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'imageserver-remote-source-readable',
    'module' => 'ImageServer',
    'level' => 'required',
    'title' => 'Read originals on an external store (Amazon S3)',
    'why' => 'ImageServer checks its source with is_readable(), which is always false for an http(s) url, so originals on an external store are never read.',
    'impact' => 'Without it, with media on S3, every IIIF image request that needs processing (sizes, regions, tiles) fails.',
    'applies_from' => null,
    'fixed_in' => null,
    'when_module' => null,
    'upstream' => 'To report.',
    'legacy' => ['$has_headers', 'PITTS PATCH (non-local files)'],
    'variants' => [
        [
            'versions' => ['3.6.25', '3.6.26', '3.6.27'],
            'edits' => [
                [
                    'file' => 'src/ImageServer/AbstractImager.php',
                    'search' => <<<'AGILEPATCH'
        // The image should be readable, whether local path or external url.
        // For external url, normally php just pings the source, so there is no
        // double download.
        if (!is_readable($source)) {
            return false;
        }
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
        // The image should be readable, whether local path or external url.
        // AGILE-PATCH imageserver-remote-source-readable: is_readable() is always false for an http(s) url (the
        // http stream wrapper has no url_stat()), so with an external store
        // (Amazon S3) every derivative and tile failed. A url is checked with
        // get_headers() instead (any 200 status line, HTTP/1.x or 2); a local path
        // keeps is_readable(). See AgileThemeTools docs/PATCHES.md.
        if (preg_match('~^https?://~i', (string) $source)) {
            $headers = @get_headers($source);
            if (!$headers || !preg_match('~^HTTP/\S+\s+200\b~', (string) $headers[0])) {
                return false;
            }
        } elseif (!is_readable($source)) {
            return false;
        }
AGILEPATCH,
                ],
                [
                    'file' => 'src/ImageServer/AbstractImager.php',
                    'search' => <<<'AGILEPATCH'
                default:
                    if (!is_readable($source)) {
                        return false;
                    }
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
                default:
                    // AGILE-PATCH imageserver-remote-source-readable: no is_readable() here: it is false for http(s)
                    // urls, which is exactly the external store case. The check above
                    // established that the source is fetchable, and the copy() below
                    // is checked for failure. See AgileThemeTools docs/PATCHES.md.
AGILEPATCH,
                ],
            ],
        ],
    ],
];
