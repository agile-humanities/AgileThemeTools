<?php
/**
 * Developer tool for config/dependencies.php, the one list of modules the Agile theme stack depends on.
 * Runs on its own (no Omeka needed):
 *
 *   php data/scripts/dependencies.php check      Fails (exit 1) if the "dependencies" line of config/module.ini does
 *                                                not match the required modules of the manifest. Run before a release.
 *   php data/scripts/dependencies.php ini        Prints the "dependencies" line module.ini should have.
 *   php data/scripts/dependencies.php markdown   Prints the table for docs/DEPENDENCIES.md.
 *   php data/scripts/dependencies.php docs       Rewrites the table inside docs/DEPENDENCIES.md.
 */
$root = dirname(__DIR__, 2);
$manifest = require $root . '/config/dependencies.php';
$command = $argv[1] ?? 'check';

$required = [];
foreach ($manifest as $id => $entry) {
    if ($id !== 'omeka' && ($entry['level'] ?? '') === 'required' && empty($entry['when'])) {
        $required[] = $id;
    }
}
sort($required);

$markdown = function () use ($manifest): string {
    $needs = function (array $entry): string {
        if ($entry['level'] === 'required') {
            return ($entry['when'] ?? null) === 'internal-search' ? 'Required with the internal search engine' : 'Required';
        }
        return ucfirst($entry['level']);
    };
    $lines = ['| Module | Needed | Tested with | Oldest that works | Used for |', '|---|---|---|---|---|'];
    foreach ($manifest as $id => $entry) {
        $name = !empty($entry['source']) ? sprintf('[%s](%s)', $entry['label'], $entry['source']) : $entry['label'];
        $notes = $entry['for'] ?? '';
        foreach ($entry['known_bad'] ?? [] as $constraint => $reason) {
            $notes .= sprintf(' **Avoid %s:** %s', $constraint, $reason);
        }
        $lines[] = sprintf('| %s | %s | %s | %s | %s |', $name, $needs($entry), $entry['pinned'] ?? '—', $entry['minimum'] ?? '—', trim($notes));
    }
    return implode("\n", $lines);
};

switch ($command) {
    case 'ini':
        echo 'dependencies = "' . implode(' ', $required) . "\"\n";
        exit(0);
    case 'markdown':
        echo $markdown() . "\n";
        exit(0);
    case 'docs':
        $file = $root . '/docs/DEPENDENCIES.md';
        $doc = (string) file_get_contents($file);
        $start = '<!-- BEGIN GENERATED TABLE: php data/scripts/dependencies.php docs -->';
        $end = '<!-- END GENERATED TABLE -->';
        $a = strpos($doc, $start);
        $b = strpos($doc, $end);
        if ($a === false || $b === false) {
            fwrite(STDERR, "The markers are missing from docs/DEPENDENCIES.md.\n");
            exit(1);
        }
        file_put_contents($file, substr($doc, 0, $a) . $start . "\n" . $markdown() . "\n" . substr($doc, $b));
        echo "docs/DEPENDENCIES.md updated.\n";
        exit(0);
    case 'check':
        $ini = parse_ini_file($root . '/config/module.ini', true, INI_SCANNER_RAW);
        $declared = preg_split('/[\s,]+/', trim((string) ($ini['info']['dependencies'] ?? ''), " \"'"), -1, PREG_SPLIT_NO_EMPTY);
        sort($declared);
        if ($declared === $required) {
            echo "OK: config/module.ini matches config/dependencies.php (" . implode(' ', $required) . ").\n";
            exit(0);
        }
        fwrite(STDERR, "MISMATCH\n  module.ini:       " . implode(' ', $declared) . "\n  dependencies.php: " . implode(' ', $required) . "\n  Fix module.ini with: php data/scripts/dependencies.php ini\n");
        exit(1);
    default:
        fwrite(STDERR, "Usage: php data/scripts/dependencies.php check|ini|markdown|docs\n");
        exit(2);
}
