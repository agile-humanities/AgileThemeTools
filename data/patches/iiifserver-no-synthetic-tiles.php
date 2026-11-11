<?php declare(strict_types=1);
/**
 * Agile patch iiifserver-no-synthetic-tiles (IiifServer): No synthesised tile grid for untiled images (IIIF Image API 2).
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH iiifserver-no-synthetic-tiles".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'iiifserver-no-synthetic-tiles',
    'module' => 'IiifServer',
    'level' => 'recommended',
    'title' => 'No synthesised tile grid for untiled images (IIIF Image API 2)',
    'why' => 'For an image without a tile pyramid, IiifServer advertises a 512px tile grid in info.json. With media on an external store, each tile request makes ImageServer download and process the whole original.',
    'impact' => 'Without it, deep zoom of untiled images on S3 is very slow and costly (dozens of full-original downloads per view), and edge tiles fail with 400.',
    'applies_from' => null,
    'fixed_in' => null,
    'when_module' => null,
    'upstream' => 'Design choice upstream: keep as an Agile patch for external stores.',
    'legacy' => ['// Only advertise tiles when the image is ACTUALLY pre-tiled.', 'PITTS PATCH: only advertise tiles'],
    'variants' => [
        [
            'versions' => ['3.6.32'],
            'edits' => [
                [
                    'file' => 'src/View/Helper/IiifInfo2.php',
                    'search' => <<<'AGILEPATCH'
            if ($tiles) {
                $info['tiles'] = $tiles;
            } else {
                // When no pre-tiled data, provide default tiles info so viewers
                // like Diva/OSD can compute tile requests. The server is
                // level2, so arbitrary region requests are supported.
                $info['tiles'] = [[
                    'width' => 512,
                    'scaleFactors' => $this->defaultScaleFactors($width, $height, 512),
                ]];
            }
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
            // AGILE-PATCH iiifserver-no-synthetic-tiles: only advertise tiles when the image is ACTUALLY pre-tiled.
            // Upstream also synthesises a 512px tile grid for untiled images, because a
            // level2 server can serve any region. With an external store (Amazon S3) and
            // no tile pyramid, every tile makes ImageServer download the whole original,
            // so viewers that fetch dozens of tiles instead of two or three pre-made
            // sizes are far slower, and edge tiles get 400. Without "tiles", viewers use
            // "sizes". See AgileThemeTools docs/PATCHES.md.
            if ($tiles) {
                $info['tiles'] = $tiles;
            }
AGILEPATCH,
                ],
            ],
        ],
        [
            'versions' => ['3.6.33', '3.6.34', '3.6.35'],
            'edits' => [
                [
                    'file' => 'src/View/Helper/IiifInfo2.php',
                    'search' => <<<'AGILEPATCH'
            if ($tiles) {
                $info['tiles'] = $tiles;
            } elseif ($width && $height) {
                // When no pre-tiled data, provide default tiles info so viewers
                // like Diva/OSD can compute tile requests. The server is
                // level2, so arbitrary region requests are supported. Skip the
                // block when dimensions are unknown (mediaDimension could not
                // resolve them): exposing a tile descriptor without
                // width/height confuses the viewer.
                $info['tiles'] = [[
                    'width' => 512,
                    'scaleFactors' => $this->defaultScaleFactors($width, $height, 512),
                ]];
            }
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
            // AGILE-PATCH iiifserver-no-synthetic-tiles: only advertise tiles when the image is ACTUALLY pre-tiled.
            // Upstream also synthesises a 512px tile grid for untiled images, because a
            // level2 server can serve any region. With an external store (Amazon S3) and
            // no tile pyramid, every tile makes ImageServer download the whole original,
            // so viewers that fetch dozens of tiles instead of two or three pre-made
            // sizes are far slower, and edge tiles get 400. Without "tiles", viewers use
            // "sizes". See AgileThemeTools docs/PATCHES.md.
            if ($tiles) {
                $info['tiles'] = $tiles;
            }
AGILEPATCH,
                ],
            ],
        ],
    ],
];
