<?php declare(strict_types=1);
/**
 * Agile patches from the command line (docs/PATCHES.md). Needs no Omeka bootstrap: it reads the module files.
 * Run it as the owner of the module files (often root in a container), from anywhere:
 *
 *   php modules/AgileThemeTools/data/scripts/patches.php [status]        every patch and its state (default)
 *   php modules/AgileThemeTools/data/scripts/patches.php apply [<id>…]   apply the missing patches (all, or the ids)
 *       --dry-run            show what would change, change nothing
 *       --allow-unverified   also apply a variant to a module version it was not verified on, when it fits cleanly
 *       --backup=<dir>       where the original files are copied (default: files/agile-patches/backups)
 *   php modules/AgileThemeTools/data/scripts/patches.php verify <stock-dir>
 *       developer check: every variant against the unzipped stock release of every version it lists
 *       (<stock-dir>/<Module>-<version>/<Module>/…)
 *
 * Exit code: 1 when a required patch is not in place (status), when an apply failed, or when verify found a problem.
 * Run "apply" after copying new module files and BEFORE running the module upgrade, so the upgrade runs patched code.
 */

require_once dirname(__DIR__, 2) . '/src/Patch/PatchManager.php';

use AgileThemeTools\Patch\PatchManager;

$omekaPath = dirname(__DIR__, 4);
$args = array_slice($argv, 1);
$options = [];
$words = [];
foreach ($args as $arg) {
    if (strpos($arg, '--') === 0) {
        [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
        $options[$key] = $value;
    } else {
        $words[] = $arg;
    }
}
$command = array_shift($words) ?: 'status';
$manager = new PatchManager($omekaPath);

$labels = [
    PatchManager::STATUS_APPLIED => 'applied',
    PatchManager::STATUS_MISSING => 'MISSING',
    PatchManager::STATUS_PARTIAL => 'PARTIAL',
    PatchManager::STATUS_CONFLICT => 'CONFLICT',
    PatchManager::STATUS_LEGACY => 'legacy',
    PatchManager::STATUS_UNVERIFIED => 'unverified',
    PatchManager::STATUS_UNSUPPORTED => 'UNSUPPORTED',
    PatchManager::STATUS_NOT_NEEDED => 'not needed',
    PatchManager::STATUS_NOT_INSTALLED => '-',
];

switch ($command) {
    case 'status':
        $failed = false;
        printf("%-44s %-15s %-12s %-12s %s\n", 'Patch', 'Module', 'Version', 'State', 'Detail');
        foreach ($manager->statuses() as $status) {
            $severity = $manager->severity($status);
            $failed = $failed || $severity === 'error';
            printf("%-44s %-15s %-12s %-12s %s\n", $status['id'], $status['module'], $status['version'] ?? '-',
                $labels[$status['status']] ?? $status['status'], $status['status'] === PatchManager::STATUS_APPLIED ? '' : $status['message']);
        }
        exit($failed ? 1 : 0);

    case 'apply':
        $ids = $words ?: array_keys($manager->definitions());
        $backup = is_string($options['backup'] ?? null)
            ? $options['backup']
            : (empty($options['dry-run']) ? $manager->defaultBackupDir() : $omekaPath . '/files/agile-patches/backups');
        $failed = false;
        foreach ($ids as $id) {
            if (!$manager->definition($id)) {
                fwrite(STDERR, "Unknown patch \"$id\".\n");
                $failed = true;
                continue;
            }
            $result = $manager->apply($id, $backup, !empty($options['dry-run']), !empty($options['allow-unverified']), PHP_BINARY);
            printf("%-44s %s %s\n", $id, $result['ok'] ? 'ok    ' : 'FAILED', $result['message']);
            if ($result['backup']) {
                printf("%-44s        originals saved in %s\n", '', $result['backup']);
            }
            $failed = $failed || !$result['ok'];
        }
        if (empty($options['dry-run'])) {
            echo "Clear the opcache of the web server (restart php or the container) if it does not revalidate files.\n";
        }
        exit($failed ? 1 : 0);

    case 'verify':
        $stock = $words[0] ?? '';
        if ($stock === '' || !is_dir($stock)) {
            fwrite(STDERR, "Usage: patches.php verify <stock-dir>\n");
            exit(2);
        }
        $problems = $manager->verify($stock, PHP_BINARY);
        $count = 0;
        foreach ($manager->definitions() as $definition) {
            foreach ($definition['variants'] as $variant) {
                $count += count($variant['versions']);
            }
        }
        echo $problems ? implode("\n", $problems) . "\n" : sprintf("All %d patches verified on %d module versions.\n", count($manager->definitions()), $count);
        exit($problems ? 1 : 0);

    default:
        fwrite(STDERR, "Usage: patches.php [status | apply [<id>…] [--dry-run] [--allow-unverified] [--backup=<dir>] | verify <stock-dir>]\n");
        exit(2);
}

