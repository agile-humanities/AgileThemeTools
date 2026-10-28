<?php
namespace AgileThemeTools\Setup;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * The standard item page: the Agile resource page blocks in the Advanced theme's regions, plus the Agile Mirador
 * preset (MiradorPreset). Written to the site's theme settings, so it holds whatever the theme declares as its
 * defaults, and can be rerun to restore the standard page after experiments.
 *
 *   Above main: Advanced media, Item heading, Downloads, Item summary.
 *   Main:       Item full metadata.
 *
 * Item set and media pages keep their current blocks. A theme without the "above" region (any theme other than the
 * Advanced theme) gets all five in its first region. Blocks whose module is not active (Advanced Media) are left out.
 */
class ItemPagesPreset
{
    const BLOCKS = [
        'above' => ['advancedMedia', 'itemHeading', 'advancedMediaDownloads', 'itemSummary'],
        'main' => ['itemFullMetadata'],
    ];

    /** @return string[] Lines for the job log. */
    public static function apply(ServiceLocatorInterface $services, SiteRepresentation $site): array
    {
        $lines = [];
        $theme = $services->get('Omeka\Site\ThemeManager')->getTheme($site->theme());
        if (!$theme) {
            return [sprintf('Site "%s": its theme "%s" is not installed; item page blocks not written.', $site->slug(), $site->theme())];
        }
        $blockManager = $services->get('Omeka\ResourcePageBlockLayoutManager');
        $registered = $blockManager->getRegisteredNames();
        $regions = array_keys($blockManager->getResourcePageRegions($theme)['items'] ?? []) ?: ['main'];

        $blocks = [];
        if (in_array('above', $regions, true) && in_array('main', $regions, true)) {
            $blocks = self::BLOCKS;
        } else {
            $blocks[reset($regions) ?: 'main'] = array_merge(...array_values(self::BLOCKS));
        }
        foreach ($blocks as $region => $names) {
            $missing = array_diff($names, $registered);
            if ($missing) {
                $lines[] = sprintf('Left out (module not active): %s.', implode(', ', $missing));
            }
            $blocks[$region] = array_values(array_intersect($names, $registered));
        }

        // Once a site stores its own blocks, core stops falling back to the theme's defaults for every resource
        // type, so start from what the site shows now (item sets and media keep their blocks).
        $siteSettings = $services->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($site->id());
        $blockManager->setSiteSettings($siteSettings);
        $key = $theme->getSettingsKey();
        $themeSettings = $siteSettings->get($key, []) ?: [];
        $themeSettings['resource_page_blocks'] = $blockManager->getResourcePageBlocks($theme);
        $themeSettings['resource_page_blocks']['items'] = $blocks;
        $siteSettings->set($key, $themeSettings);
        foreach ($blocks as $region => $names) {
            $lines[] = sprintf('Site "%s", item pages, region "%s": %s.', $site->slug(), $region, implode(', ', $names) ?: 'none');
        }

        return array_merge($lines, MiradorPreset::apply($services));
    }
}
