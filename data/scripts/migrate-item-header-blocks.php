<?php
/**
 * Moves sites from the legacy Item Header resource page block (itemHeader) to the
 * Item Header + Item Summary pair (itemHeading, itemSummary). A Downloads block in the
 * same region is placed between them: Item Header, Downloads, Item Summary.
 *
 * Dry run is the default and changes nothing. Run from the Omeka root:
 *
 *   php modules/AgileThemeTools/data/scripts/migrate-item-header-blocks.php
 *   php modules/AgileThemeTools/data/scripts/migrate-item-header-blocks.php --site=hrc
 *   php modules/AgileThemeTools/data/scripts/migrate-item-header-blocks.php --apply
 *
 * Skipped: sites with no configured item blocks (they use the theme.ini defaults),
 * regions without itemHeader, and regions that already contain itemSummary.
 */

use Omeka\Mvc\Application;

const LEGACY_BLOCK = 'itemHeader';
const HEADING_BLOCK = 'itemHeading';
const SUMMARY_BLOCK = 'itemSummary';
const DOWNLOADS_BLOCK = 'advancedMediaDownloads';

$options = getopt('', ['apply', 'site:']);
$apply = isset($options['apply']);
$onlySlug = $options['site'] ?? null;

$omekaPath = dirname(__DIR__, 4);
chdir($omekaPath);
require $omekaPath . '/bootstrap.php';

$application = Application::init(require $omekaPath . '/application/config/application.config.php');
$services = $application->getServiceManager();
$connection = $services->get('Omeka\Connection');
$siteSettings = $services->get('Omeka\Settings\Site');

/**
 * Replace itemHeader with itemHeading [+ advancedMediaDownloads] + itemSummary.
 */
function migrateBlocks(array $blocks): array
{
    $hasDownloads = in_array(DOWNLOADS_BLOCK, $blocks, true);
    $migrated = [];
    foreach ($blocks as $block) {
        if ($block === DOWNLOADS_BLOCK) {
            continue;
        }
        if ($block === LEGACY_BLOCK) {
            $migrated[] = HEADING_BLOCK;
            if ($hasDownloads) {
                $migrated[] = DOWNLOADS_BLOCK;
            }
            $migrated[] = SUMMARY_BLOCK;
            continue;
        }
        $migrated[] = $block;
    }
    return $migrated;
}

printf("%s\n\n", $apply ? 'APPLYING CHANGES' : 'DRY RUN (pass --apply to write changes)');

$sites = $connection->executeQuery('SELECT id, slug, theme FROM site ORDER BY id')->fetchAll();
$changedCount = 0;

foreach ($sites as $site) {
    if ($onlySlug !== null && $site['slug'] !== $onlySlug) {
        continue;
    }

    $settingsKey = 'theme_settings_' . $site['theme'];
    $siteSettings->setTargetId($site['id']);
    $themeSettings = $siteSettings->get($settingsKey);
    $regions = is_array($themeSettings) ? ($themeSettings['resource_page_blocks']['items'] ?? null) : null;

    if (!is_array($regions)) {
        printf("- %s (%s): skipped, no configured item blocks\n", $site['slug'], $site['theme']);
        continue;
    }

    $siteChanged = false;
    foreach ($regions as $region => $blocks) {
        if (!in_array(LEGACY_BLOCK, $blocks, true) || in_array(SUMMARY_BLOCK, $blocks, true)) {
            continue;
        }
        $migrated = migrateBlocks($blocks);
        printf("* %s (%s) items/%s\n    before: %s\n    after:  %s\n", $site['slug'], $site['theme'], $region, implode(', ', $blocks), implode(', ', $migrated));
        $themeSettings['resource_page_blocks']['items'][$region] = $migrated;
        $siteChanged = true;
    }

    if (!$siteChanged) {
        printf("- %s (%s): skipped, nothing to migrate\n", $site['slug'], $site['theme']);
        continue;
    }

    $changedCount++;
    if ($apply) {
        $siteSettings->set($settingsKey, $themeSettings);
    }
}

printf("\n%d site(s) %s.\n", $changedCount, $apply ? 'updated' : 'would be updated');
