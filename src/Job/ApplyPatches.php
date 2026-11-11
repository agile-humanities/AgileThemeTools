<?php
namespace AgileThemeTools\Job;

use AgileThemeTools\Patch\PatchManager;
use Omeka\Job\AbstractJob;

/**
 * Applies the Agile patches that are missing (docs/PATCHES.md), from Easy Admin > Check and fix > Agile modules.
 *
 * Only patches with a variant verified on the installed module version are applied, and only when every file to
 * change and its directory are writable by the user running jobs (the web server user, as a rule). Otherwise the
 * job changes nothing for that patch and logs what to do: the command to run as the owner of the files, or which
 * files to make writable. The originals are copied to files/agile-patches/backups first.
 *
 * Run it after copying new module files and before running their upgrade, so that the upgrade runs patched code.
 */
class ApplyPatches extends AbstractJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        /** @var PatchManager $patches */
        $patches = $services->get(PatchManager::class);

        // Lint each patched file before writing it, with the php running this job (jobs run with the php cli).
        $php = PHP_BINARY && stripos(PHP_SAPI, 'cli') !== false ? PHP_BINARY : null;
        $backup = $patches->defaultBackupDir();

        $applied = $failed = $skipped = 0;
        foreach ($patches->statuses() as $id => $status) {
            if ($this->shouldStop()) {
                $logger->warn('The job was stopped.'); // @translate
                return;
            }
            if (!in_array($status['status'], [PatchManager::STATUS_MISSING, PatchManager::STATUS_PARTIAL], true)) {
                if (in_array($status['status'], [PatchManager::STATUS_CONFLICT, PatchManager::STATUS_LEGACY, PatchManager::STATUS_UNVERIFIED, PatchManager::STATUS_UNSUPPORTED], true)) {
                    $logger->warn(sprintf('[%s] %s %s: not applied. %s', $id, $status['module'], $status['version'], $status['message']));
                    ++$skipped;
                }
                continue;
            }
            $result = $patches->apply($id, $backup, false, false, $php);
            if ($result['ok']) {
                $logger->notice(sprintf('[%s] %s%s', $id, $result['message'], $result['backup'] ? ' Originals saved in ' . $result['backup'] . '.' : ''));
                ++$applied;
            } else {
                $logger->err(sprintf('[%s] %s %s: %s', $id, $status['module'], $status['version'], $result['message']));
                ++$failed;
            }
        }

        $logger->notice(sprintf(
            'Agile patches: %d applied, %d failed, %d need a manual step (see above). %s',
            $applied, $failed, $skipped,
            $applied ? 'If the site still behaves as before, restart php (or the container) to clear its opcache.' : ''
        ));
    }
}
