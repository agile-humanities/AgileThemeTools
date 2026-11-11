<?php declare(strict_types=1);
/**
 * Agile patch advancedsearch-property-value-array (AdvancedSearch): Property search with a single value.
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH advancedsearch-property-value-array".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'advancedsearch-property-value-array',
    'module' => 'AdvancedSearch',
    'level' => 'required',
    'title' => 'Property search with a single value',
    'why' => 'The property search of the item search page submits a single value as a string, where the query builder expected a list.',
    'impact' => 'Without it, a property search from the item search page fails with an error.',
    'applies_from' => null,
    'fixed_in' => '3.4.65',
    'when_module' => null,
    'upstream' => 'Not needed from 3.4.65: every property query type with a string value works on 3.4.67, whose SearchResources.php is identical to 3.4.65\'s.',
    'legacy' => ['Hack solution to properties search'],
    'variants' => [
        [
            'versions' => ['3.4.62', '3.4.64'],
            'edits' => [
                [
                    'file' => 'src/Stdlib/SearchResources.php',
                    'search' => <<<'AGILEPATCH'
        switch ($queryType) {
            case 'eq':
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
        // AGILE-PATCH advancedsearch-property-value-array: the property search of the item search page submits the
        // value as a string, which caused a fatal error: use a list. See AgileThemeTools docs/PATCHES.md.
        if (!is_array($value)) {
            $value = (array) $value;
        }

        switch ($queryType) {
            case 'eq':
AGILEPATCH,
                ],
            ],
        ],
    ],
];
