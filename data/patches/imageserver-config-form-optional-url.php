<?php declare(strict_types=1);
/**
 * Agile patch imageserver-config-form-optional-url (ImageServer): Saveable Image Server config form.
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH imageserver-config-form-optional-url".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'imageserver-config-form-optional-url',
    'module' => 'ImageServer',
    'level' => 'required',
    'title' => 'Saveable Image Server config form',
    'why' => 'An optional url field of the Image Server config form is a required Laminas Url element.',
    'impact' => 'Without it, saving the Image Server config form silently does nothing while the rights url is empty (its default).',
    'applies_from' => null,
    'fixed_in' => '3.6.26',
    'when_module' => null,
    'upstream' => 'Fixed upstream in 3.6.26.',
    'legacy' => ['PITTS PATCH: OptionalUrl'],
    'variants' => [
        [
            'versions' => ['3.6.25'],
            'edits' => [
                [
                    'file' => 'src/Form/ConfigForm.php',
                    'search' => <<<'AGILEPATCH'
                'name' => 'imageserver_info_rights_url',
                'type' => Element\Url::class,
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
                'name' => 'imageserver_info_rights_url',
                // AGILE-PATCH imageserver-config-form-optional-url: OptionalUrl, not Laminas' Url, which is required by
                // default: leaving this optional field empty made the whole form invalid
                // and nothing was saved (silently: the messenger is cleared before the
                // form is rendered). See AgileThemeTools docs/PATCHES.md.
                'type' => CommonElement\OptionalUrl::class,
AGILEPATCH,
                ],
            ],
        ],
    ],
];
