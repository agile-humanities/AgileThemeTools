<?php
namespace AgileThemeTools\Setup;

use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * The Agile Mirador preset: the viewer configuration the Agile themes are designed around (the Pitts setup), applied
 * to the installation's Mirador settings and to every site's — the Mirador helper reads the SITE settings on site
 * pages, with no fallback to the global ones, so both must carry it.
 *
 *   - Mirador 4 with Mirador module 3.4.17 and later (its Mirador 3 bundle does not draw images), Mirador 3 before;
 *     dark theme.
 *   - The image-tools plugin on (brightness/contrast/rotate controls, collapsed until the visitor opens them).
 *     The plugin must be in `mirador_plugins`, or the module loads the vanilla bundle that has no such code.
 *   - The workspace control panel (add window, workspace type) off: the viewer is embedded in an item page.
 *
 * Applied from Easy Admin > Check and fix > Agile modules, or programmatically. Overwrites the Mirador config keys
 * (version, plugins, item config, collection config); the other Mirador settings are left alone.
 *
 * @todo Reconcile with the live Pitts configuration (osdConfig, window flags) when that stack is at hand.
 */
class MiradorPreset
{
    const PLUGINS = ['image-tools'];

    /** The viewer configuration, emitted verbatim into the page as JavaScript by the Mirador helper. */
    public static function config(): string
    {
        return <<<'JSON'
{
    "selectedTheme": "dark",
    "window": {
        "imageToolsEnabled": true,
        "imageToolsOpen": false,
        "allowClose": false,
        "allowMaximize": false,
        "allowFullscreen": true,
        "sideBarOpenByDefault": false
    },
    "workspaceControlPanel": {
        "enabled": false
    }
}
JSON;
    }

    /** The Mirador major version the preset uses: 4 when the installed module offers it (3.4.17 and later). */
    public static function miradorVersion(): string
    {
        return method_exists(\Mirador\View\Helper\Mirador::class, 'renderMirador4') ? '4' : '3';
    }

    /**
     * Writes the preset. Returns one line per settings scope touched, for the job log.
     *
     * @return string[]
     */
    public static function apply(ServiceLocatorInterface $services): array
    {
        $module = $services->get('Omeka\ModuleManager')->getModule('Mirador');
        if (!$module || $module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
            return ['Mirador is not active: nothing written.'];
        }
        // From 3.4.17 the module defaults to Mirador 4 and keeps Mirador 3's settings under "_3" keys; the
        // unsuffixed keys are Mirador 4's. Before, the unsuffixed keys are Mirador 3's.
        $values = [
            'mirador_version' => self::miradorVersion(),
            'mirador_plugins' => self::PLUGINS,
            'mirador_config_item' => self::config(),
            'mirador_config_collection' => self::config(),
        ];
        $lines = [];

        $settings = $services->get('Omeka\Settings');
        foreach ($values as $key => $value) {
            $settings->set($key, $value);
        }
        $lines[] = sprintf('Installation settings: Mirador preset written (Mirador %s, dark theme, image tools, no workspace panel).', $values['mirador_version']);

        $siteSettings = $services->get('Omeka\Settings\Site');
        $api = $services->get('Omeka\ApiManager');
        foreach ($api->search('sites')->getContent() as $site) {
            $siteSettings->setTargetId($site->id());
            foreach ($values as $key => $value) {
                $siteSettings->set($key, $value);
            }
            $lines[] = sprintf('Site "%s" (%s): Mirador preset written.', $site->title(), $site->slug());
        }
        return $lines;
    }
}
