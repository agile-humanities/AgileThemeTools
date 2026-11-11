<?php declare(strict_types=1);
/**
 * Agile patch amazons3-archiverepertory-interface (AmazonS3): Archive Repertory adapters for the current Archive Repertory.
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH amazons3-archiverepertory-interface".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'amazons3-archiverepertory-interface',
    'module' => 'AmazonS3',
    'level' => 'required',
    'title' => 'Archive Repertory adapters for the current Archive Repertory',
    'why' => 'The adapters Amazon S3 provides for the Archive Repertory module do not match the interface of Archive Repertory 3.15.17 and later.',
    'impact' => 'Without it, with Archive Repertory, media file management on S3 fails (the file manager cannot be created).',
    'applies_from' => null,
    'fixed_in' => null,
    'when_module' => 'ArchiveRepertory',
    'upstream' => 'Re-check with 3.4.5 (step 6 of the convergence).',
    'legacy' => ['//$this->getFileWriter();'],
    'variants' => [
        [
            'versions' => ['3.4.4'],
            'edits' => [
                [
                    'file' => 'src/File/ArchiveRepertory/FileManager.php',
                    'search' => <<<'AGILEPATCH'
        $fileWriter = $this->getFileWriter();
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
        // AGILE-PATCH amazons3-archiverepertory-interface: ArchiveRepertory 3.15.17+ has no getFileWriter(). See AgileThemeTools docs/PATCHES.md.
        $fileWriter = $this->fileWriter;
AGILEPATCH,
                ],
                [
                    'file' => 'src/Service/File/ArchiveRepertory/FileManagerFactory.php',
                    'search' => <<<'AGILEPATCH'
        return new FileManager(
            $thumbnailTypes,
            $basePath,
            $ingesters,
            $services
        );
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
        // AGILE-PATCH amazons3-archiverepertory-interface: the constructor of ArchiveRepertory 3.15.17+. See AgileThemeTools docs/PATCHES.md.
        return new FileManager(
            $services->get('ArchiveRepertory\FileWriter'),
            $services->get('ControllerPluginManager')->get('messenger'),
            $services->get('Omeka\Settings'),
            $services->get('MvcTranslator'),
            $basePath,
            $ingesters,
            $thumbnailTypes
        );
AGILEPATCH,
                ],
            ],
        ],
    ],
];
