<?php declare(strict_types=1);
/**
 * Agile patch iiifserver-keep-media-identifier (IiifServer): Never switch the IIIF media identifier automatically.
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH iiifserver-keep-media-identifier".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'iiifserver-keep-media-identifier',
    'module' => 'IiifServer',
    'level' => 'required',
    'title' => 'Never switch the IIIF media identifier automatically',
    'why' => 'IiifServer guesses that an external image server is in use from a self-request, and then switches the media identifier to file paths. The guess runs on install, upgrade and every visit of its config page; a proxy or CDN answer is enough to trigger it.',
    'impact' => 'Without it, IIIF image urls can silently become file paths with encoded slashes (%2F), which Apache refuses: every viewer shows broken images.',
    'applies_from' => null,
    'fixed_in' => null,
    'when_module' => null,
    'upstream' => 'To report.',
    'legacy' => ['PITTS PATCH: report, never reconfigure'],
    'variants' => [
        [
            'versions' => ['3.6.32', '3.6.33', '3.6.34', '3.6.35'],
            'edits' => [
                [
                    'file' => 'Module.php',
                    'search' => <<<'AGILEPATCH'
            if (in_array($identifier, ['media_id', 'default'])) {
                // Auto-configure: external server with filesystem source
                // needs the filename, not the numeric media id.
                $settings->set('iiifserver_media_api_identifier', 'filename_image');
                $message = new PsrMessage(
                    'An external IIIF image server was detected ({server_label}). The media identifier has been set to "filename with extension (image only)" for compatibility. The option can be changed in the config form.', // @translate
                    ['server_label' => $serverLabel]
                );
                $messenger->addSuccess($message);
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
            if (in_array($identifier, ['media_id', 'default'])) {
                // AGILE-PATCH iiifserver-keep-media-identifier: report, never reconfigure. Upstream SETS the media
                // identifier to "filename_image" here, on a guess: the detection is a
                // self-request whose answer only has to LOOK foreign (a proxy or CDN
                // block page is enough), and it runs on install, upgrade and every load
                // or save of the config page. On a production site that silently
                // swapped every IIIF image address from /iiif/2/<id>/ to
                // /iiif/2/<path>%2F<file>/, which Apache refuses (encoded slash), so
                // every viewer broke with nobody having changed a setting.
                // See AgileThemeTools docs/PATCHES.md.
                $message = new PsrMessage(
                    'An external IIIF image server may be in use ({server_label}). The media identifier has NOT been changed and is still "{identifier}". If images are served by an external server reading the original files, set it to "filename with extension (image only)" in the Image Server config form.', // @translate
                    ['server_label' => $serverLabel, 'identifier' => $identifier]
                );
                $messenger->addNotice($message);
AGILEPATCH,
                ],
            ],
        ],
    ],
];
