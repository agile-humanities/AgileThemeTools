<?php declare(strict_types=1);
/**
 * Agile patch vips-pdf-no-dpi (Vips): PDF thumbnails with the vips command line tool.
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH vips-pdf-no-dpi".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'vips-pdf-no-dpi',
    'module' => 'Vips',
    'level' => 'recommended',
    'title' => 'PDF thumbnails with the vips command line tool',
    'why' => 'The vips command line tool of the Agile PHP container rejects the dpi and background load options the Vips module passes for PDF files.',
    'impact' => 'Without it, PDF thumbnails fail with the Agile container. The cost: PDFs are rendered at 72 dpi, so a letter page gives a 504x648 "large" thumbnail.',
    'applies_from' => null,
    'fixed_in' => null,
    'when_module' => null,
    'upstream' => 'Environment-specific: re-check with each vips version of the container.',
    'legacy' => ['VIPS Cli is rejecting this parameter'],
    'variants' => [
        [
            'versions' => ['3.4.6', '3.4.7'],
            'edits' => [
                [
                    'file' => 'src/File/Thumbnailer/VipsCli.php',
                    'search' => <<<'AGILEPATCH'
        $supportDpi = [
            'application/pdf',
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
        $supportDpi = [
            // AGILE-PATCH vips-pdf-no-dpi: no dpi option for pdf: the vips cli of the Agile container
            // rejects it, so pdf thumbnails failed. See AgileThemeTools docs/PATCHES.md.
            // 'application/pdf',
AGILEPATCH,
                ],
                [
                    'file' => 'src/File/Thumbnailer/VipsCli.php',
                    'search' => <<<'AGILEPATCH'
        $supportBackground = [
            'application/pdf',
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
        $supportBackground = [
            // AGILE-PATCH vips-pdf-no-dpi: no background option for pdf (rejected by the vips cli).
            // 'application/pdf',
AGILEPATCH,
                ],
            ],
        ],
    ],
];
