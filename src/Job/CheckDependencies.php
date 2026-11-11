<?php
namespace AgileThemeTools\Job;

use AgileThemeTools\Dependency\DependencyAudit;
use Omeka\Job\AbstractJob;

/**
 * Writes the dependency audit to the job log: every module the theme system depends on, the version installed,
 * the version tested, and what is wrong if anything; then the state of each Agile patch (Patch\\PatchManager).
 * Run from Easy Admin > Check and fix. Changes nothing.
 */
class CheckDependencies extends AbstractJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $audit = $services->get(DependencyAudit::class);

        $labels = ['error' => 'PROBLEM', 'warning' => 'WARNING', 'note' => 'note', 'ok' => 'ok'];
        foreach ($audit->rows() as $row) {
            $line = sprintf('[%s] %s — installed %s, tested with %s. %s%s',
                $labels[$row['severity']],
                $row['label'],
                $row['installed'] ?? 'none',
                $row['pinned'] ?? 'no specific version',
                $row['message'],
                $row['fix'] !== '' && $row['severity'] !== 'ok' ? ' ' . $row['fix'] : ''
            );
            if ($row['severity'] === 'error') {
                $logger->err($line);
            } elseif ($row['severity'] === 'warning') {
                $logger->warn($line);
            } else {
                $logger->info($line);
            }
        }
        $summary = $audit->summary();
        $logger->notice(sprintf('Agile Theme Tools dependency check: %d problem(s), %d warning(s), %d note(s), %d ok.', $summary['error'], $summary['warning'], $summary['note'], $summary['ok']));

        // The Agile patches to third-party modules (docs/PATCHES.md).
        $patches = $services->get(\AgileThemeTools\Patch\PatchManager::class);
        $counts = ['error' => 0, 'warning' => 0, 'note' => 0, 'ok' => 0];
        foreach ($patches->statuses() as $id => $status) {
            if ($status['status'] === \AgileThemeTools\Patch\PatchManager::STATUS_NOT_INSTALLED) {
                continue;
            }
            $severity = $patches->severity($status);
            ++$counts[$severity];
            $line = sprintf('[%s] patch %s (%s %s): %s', $labels[$severity], $id, $status['module'], $status['version'], $status['message']);
            if (in_array($severity, ['error', 'warning'], true) && !empty($status['definition']['impact'])) {
                $line .= ' ' . $status['definition']['impact'];
            }
            if ($severity === 'error') {
                $logger->err($line);
            } elseif ($severity === 'warning') {
                $logger->warn($line);
            } else {
                $logger->info($line);
            }
        }
        $logger->notice(sprintf('Agile patches: %d problem(s), %d warning(s), %d note(s), %d ok.', $counts['error'], $counts['warning'], $counts['note'], $counts['ok']));

        // Advanced Media's live checks of what the patches protect, through the site's public address.
        if ($services->has(\AdvancedMedia\File\IntegrationChecks::class)) {
            try {
                $serverUrl = $services->get('ViewHelperManager')->get('ServerUrl');
                $basePath = $services->get('ViewHelperManager')->get('BasePath');
                $result = $services->get(\AdvancedMedia\File\IntegrationChecks::class)->results(rtrim($serverUrl($basePath()), '/'), true);
                foreach ($result['checks'] as $check) {
                    $line = sprintf('[%s] check %s: %s', strtoupper($check['state']), $check['label'], $check['message']);
                    if ($check['state'] === 'error') {
                        $logger->err($line);
                    } elseif ($check['state'] === 'warning') {
                        $logger->warn($line);
                    } else {
                        $logger->info($line);
                    }
                }
            } catch (\Throwable $e) {
                $logger->warn('Advanced Media integration checks could not run: ' . $e->getMessage());
            }
        }
    }
}
