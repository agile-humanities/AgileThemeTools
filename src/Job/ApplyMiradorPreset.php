<?php
namespace AgileThemeTools\Job;

use AgileThemeTools\Setup\MiradorPreset;
use Omeka\Job\AbstractJob;

/**
 * Applies the Agile Mirador preset (Setup\MiradorPreset) to the installation and every site. Run from Easy Admin >
 * Check and fix > Agile modules.
 */
class ApplyMiradorPreset extends AbstractJob
{
    public function perform(): void
    {
        $logger = $this->getServiceLocator()->get('Omeka\Logger');
        foreach (MiradorPreset::apply($this->getServiceLocator()) as $line) {
            $logger->notice($line);
        }
    }
}
