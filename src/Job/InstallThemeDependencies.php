<?php declare(strict_types=1);
namespace AgileThemeTools\Job;

use AgileThemeTools\ThemeBuild\BuildManager;
use Omeka\Job\AbstractJob;

/**
 * Installs a theme's build dependencies (node_modules) with npm, so the theme can be built on this server. Run from
 * the site Theme page's build panel or Easy Admin > Check and fix > Theme builds.
 *
 * Arguments: theme (the theme id, i.e. its folder name); site_ids (int[], the site the job was started from, so the
 * site's build panel shows it as running). The npm transcript is written to this job's log.
 */
class InstallThemeDependencies extends AbstractJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        /** @var BuildManager $builder */
        $builder = $services->get(BuildManager::class);
        $themeId = (string) $this->getArg('theme', '');
        $theme = $themeId ? $services->get('Omeka\Site\ThemeManager')->getTheme($themeId) : null;
        if (!$theme) {
            $logger->err(sprintf('Theme "%s" is not installed; nothing to do.', $themeId)); // @translate
            return;
        }
        $logger->notice(sprintf('Installing the build dependencies of theme "%s" (%s).', $theme->getName(), $theme->getPath())); // @translate
        $buffer = '';
        $result = $builder->installDependencies($theme, function (string $chunk) use (&$buffer, $logger) {
            $buffer .= $chunk;
            // One log entry per line, so the job page reads like a terminal.
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line !== '') {
                    $logger->info($line);
                }
            }
        });
        if (trim($buffer) !== '') {
            $logger->info(trim($buffer));
        }
        if ($result['timedOut']) {
            $logger->err('npm did not finish in time; the install was stopped.'); // @translate
        } elseif ($result['exitCode'] !== 0) {
            $logger->err(sprintf('npm exited with code %d.', $result['exitCode'])); // @translate
        } else {
            $logger->notice('Dependencies installed. The theme can be built now.'); // @translate
        }
    }
}
