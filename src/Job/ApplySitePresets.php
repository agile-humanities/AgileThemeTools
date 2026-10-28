<?php
namespace AgileThemeTools\Job;

use AgileThemeTools\Setup\HomepagePreset;
use AgileThemeTools\Setup\ItemPagesPreset;
use AgileThemeTools\Setup\SamplePagesPreset;
use AgileThemeTools\Setup\SearchPagesPreset;
use Omeka\Job\AbstractJob;

/**
 * Applies the chosen site presets (Setup\*Preset) to one site. Run from Easy Admin > Check and fix > Agile site setup
 * (Setup\EasyAdminTasks). Arguments: site_id, presets (a list of keys of PRESETS), and for the search pages the
 * Solr connection (solr_url).
 */
class ApplySitePresets extends AbstractJob
{
    /** In the order they run: search pages first, so the test pages can use the site's main search. */
    const PRESETS = [
        'item_pages' => ItemPagesPreset::class,
        'homepage' => HomepagePreset::class,
        'search_pages' => SearchPagesPreset::class,
        'sample_pages' => SamplePagesPreset::class,
    ];

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $site = $services->get('Omeka\ApiManager')->read('sites', (int) $this->getArg('site_id'))->getContent();
        $chosen = (array) $this->getArg('presets', []);
        foreach (self::PRESETS as $key => $class) {
            if (!in_array($key, $chosen, true)) {
                continue;
            }
            try {
                $lines = $key === 'search_pages'
                    ? $class::apply($services, $site, ['solr_url' => (string) $this->getArg('solr_url', '')])
                    : $class::apply($services, $site);
                foreach ($lines as $line) {
                    $logger->notice($line);
                }
            } catch (\Throwable $e) {
                $logger->err(sprintf('Preset "%s" failed: %s', $key, $e->getMessage()));
            }
            // Reread the site: presets change its navigation and homepage.
            $site = $services->get('Omeka\ApiManager')->read('sites', $site->id())->getContent();
        }
    }
}
