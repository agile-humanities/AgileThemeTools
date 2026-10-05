<?php declare(strict_types=1);

namespace AgileThemeTools\Job;

use AgileThemeTools\ThemeBuild\BuildManager;
use Omeka\Job\AbstractJob;

/**
 * Builds the theme assets of one or more sites from their theme styling settings.
 *
 * Arguments: site_ids (int[]); preflight_only (bool, default false) runs the preflight checks for each site and
 * reports them without building. Sites are built one after another under the theme's build lock. The full build
 * transcript is written to this job's log (visible on the job page) as well as to each site's build.log.
 */
class BuildSiteTheme extends AbstractJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $api = $services->get('Omeka\ApiManager');
        /** @var BuildManager $builder */
        $builder = $services->get(BuildManager::class);

        $siteIds = array_values(array_unique(array_map('intval', (array) $this->getArg('site_ids', []))));
        $preflightOnly = (bool) $this->getArg('preflight_only', false);
        if (!$siteIds) {
            $this->log('No sites given. Nothing to build.');
            $logger->warn('Theme build job started without sites.'); // @translate
            return;
        }

        $failures = 0;
        foreach ($siteIds as $index => $siteId) {
            if ($this->shouldStop()) {
                $this->log('Stopped before building the remaining sites.');
                return;
            }
            if ($index > 0) {
                $this->log('');
            }

            try {
                $site = $api->read('sites', $siteId)->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $this->log(sprintf('Site #%d does not exist; skipped.', $siteId));
                $failures++;
                continue;
            }

            $theme = $builder->getSiteTheme($site);
            if (!$theme) {
                $this->log(sprintf('Site "%s" has no active theme; skipped.', $site->slug()));
                $failures++;
                continue;
            }

            if ($preflightOnly) {
                $preflight = $builder->preflight($theme, $site);
                $this->log(sprintf('%s "%s" (%s), theme "%s", as %s:', $preflight['ok'] ? 'OK  ' : 'FAIL', $site->title(), $site->slug(), $theme->getId(), $preflight['user']));
                foreach ($preflight['checks'] as $check) {
                    $this->log(sprintf('  [%s] %s: %s%s', $check['ok'] === true ? 'ok' : ($check['ok'] === false ? 'FAIL' : 'warn'), $check['label'], $check['detail'], $check['ok'] === false && $check['fix'] ? ' Fix: ' . $check['fix'] : ''));
                }
                $status = $builder->getStatus($theme, $site);
                $this->log('  Build: ' . ($status['built'] ? ($status['stale'] ? 'rebuild needed (' . implode(' ', $status['reasons']) . ')' : 'up to date') : ($status['legacy'] ? 'old build in the theme folder' : 'never built')));
                if (!$preflight['ok']) {
                    $failures++;
                }
                continue;
            }

            $result = $builder->build($theme, $site, function (string $line) {
                $this->log($line);
            }, $this->job->getId());

            if ($result['status'] === 'success') {
                $logger->info('Theme assets built for site "{slug}" ({theme}).', ['slug' => $site->slug(), 'theme' => $theme->getId()]); // @translate
            } else {
                $failures++;
                $logger->err('Theme build failed for site "{slug}" ({theme}): {message}', ['slug' => $site->slug(), 'theme' => $theme->getId(), 'message' => $result['message']]); // @translate
            }
        }

        if ($failures) {
            // Mark the job as errored so the jobs list and the build panel do not report success.
            throw new \RuntimeException($preflightOnly
                ? sprintf('%d of %d site(s) cannot be built. See the log above.', $failures, count($siteIds))
                : sprintf('%d of %d site build(s) failed. See the log above.', $failures, count($siteIds)));
        }
    }

    /** Appends a line to the job's own log, which the jobs page shows. */
    protected function log(string $line): void
    {
        $this->job->addLog($line);
    }
}
