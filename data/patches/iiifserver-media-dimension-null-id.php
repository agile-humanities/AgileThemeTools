<?php declare(strict_types=1);
/**
 * Agile patch iiifserver-media-dimension-null-id (IiifServer): No dimension cache write for a media that has no id yet.
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH iiifserver-media-dimension-null-id".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'iiifserver-media-dimension-null-id',
    'module' => 'IiifServer',
    'level' => 'required',
    'title' => 'No dimension cache write for a media that has no id yet',
    'why' => 'IiifServer sizes the media of an item while the item is being saved, before new media have an id, and its cache write requires an int: the TypeError aborts the item create.',
    'impact' => 'Without it, creating items with audio, video or image media from a url fails (CSV Import jobs error out, nothing is created).',
    'applies_from' => null,
    'fixed_in' => null,
    'when_module' => null,
    'upstream' => '3.6.34 guards the generic branch; the remote IIIF branch (3.6.33+) is still unguarded in 3.6.35. To report.',
    'legacy' => ['PITTS PATCH: a media being created', 'Patched (Pitts, 2026-11-09)'],
    'variants' => [
        [
            'versions' => ['3.6.32'],
            'edits' => [
                [
                    'file' => 'src/Mvc/Controller/Plugin/MediaDimension.php',
                    'search' => <<<'AGILEPATCH'
        if ($result['width'] || $result['height'] || $result['duration']) {
            $this->cacheMediaDimensions($media->id(), $type, $result);
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
        // AGILE-PATCH iiifserver-media-dimension-null-id: no id yet while the item is being created. See AgileThemeTools docs/PATCHES.md.
        if ($media->id() && ($result['width'] || $result['height'] || $result['duration'])) {
            $this->cacheMediaDimensions((int) $media->id(), $type, $result);
AGILEPATCH,
                ],
            ],
        ],
        [
            'versions' => ['3.6.33'],
            'edits' => [
                [
                    'file' => 'src/Mvc/Controller/Plugin/MediaDimension.php',
                    'search' => <<<'AGILEPATCH'
                $table = $media->resourceName() === 'digital_objects'
                    ? 'digital_object'
                    : 'media';
                $this->cacheMediaDimensions($media->id(), $type, $result, $table);
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
                $table = $media->resourceName() === 'digital_objects'
                    ? 'digital_object'
                    : 'media';
                // AGILE-PATCH iiifserver-media-dimension-null-id: a media being created (item hydration, e.g. CSV Import)
                // has no id yet, and cacheMediaDimensions() takes an int: skip, the
                // dimensions are saved with the entity by the sizing listener.
                // See AgileThemeTools docs/PATCHES.md.
                if ($media->id()) {
                    $this->cacheMediaDimensions((int) $media->id(), $type, $result, $table);
                }
AGILEPATCH,
                ],
                [
                    'file' => 'src/Mvc/Controller/Plugin/MediaDimension.php',
                    'search' => <<<'AGILEPATCH'
        if ($result['width'] || $result['height'] || $result['duration']) {
            $table = $media->resourceName() === 'digital_objects' ? 'digital_object' : 'media';
            $this->cacheMediaDimensions($media->id(), $type, $result, $table);
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
        // AGILE-PATCH iiifserver-media-dimension-null-id: no id yet while the item is being created. See AgileThemeTools docs/PATCHES.md.
        if ($media->id() && ($result['width'] || $result['height'] || $result['duration'])) {
            $table = $media->resourceName() === 'digital_objects' ? 'digital_object' : 'media';
            $this->cacheMediaDimensions((int) $media->id(), $type, $result, $table);
AGILEPATCH,
                ],
            ],
        ],
        [
            'versions' => ['3.6.34', '3.6.35'],
            'edits' => [
                [
                    'file' => 'src/Mvc/Controller/Plugin/MediaDimension.php',
                    'search' => <<<'AGILEPATCH'
                $table = $media->resourceName() === 'digital_objects'
                    ? 'digital_object'
                    : 'media';
                $this->cacheMediaDimensions($media->id(), $type, $result, $table);
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
                $table = $media->resourceName() === 'digital_objects'
                    ? 'digital_object'
                    : 'media';
                // AGILE-PATCH iiifserver-media-dimension-null-id: a media being created (item hydration, e.g. CSV Import)
                // has no id yet, and cacheMediaDimensions() takes an int: skip, the
                // dimensions are saved with the entity by the sizing listener.
                // See AgileThemeTools docs/PATCHES.md.
                if ($media->id()) {
                    $this->cacheMediaDimensions((int) $media->id(), $type, $result, $table);
                }
AGILEPATCH,
                ],
            ],
        ],
    ],
];
