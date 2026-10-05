<?php
namespace AgileThemeTools\Job;

use AgileThemeTools\Dependency\DependencyAudit;
use Omeka\Job\AbstractJob;

/**
 * Writes the dependency audit to the job log: every module the theme system depends on, the version installed,
 * the version tested, and what is wrong if anything. Run from Easy Admin > Check and fix. Changes nothing.
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
    }
}
