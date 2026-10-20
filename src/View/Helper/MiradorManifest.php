<?php
namespace AgileThemeTools\View\Helper;

/**
 * Mirador for a manifest URL. The Mirador module's helper only takes a resource (it finds the manifest itself);
 * this subclass exposes its renderer for a URL the admin typed — the IIIF presentation page block (design review D2).
 * Loaded only when the Mirador module is present (the block checks class_exists before asking for it).
 *
 * Renders through the "collection" path, which needs no resource and reads the site's mirador_config_collection
 * (the Agile Mirador preset writes the same configuration to both keys).
 */
class MiradorManifest extends \Mirador\View\Helper\Mirador
{
    public function manifest(string $urlManifest, array $options = []): string
    {
        $view = $this->getView();
        $this->isSite = $view->status()->isSiteRequest();
        $setting = $this->isSite ? $view->plugin('siteSetting') : $view->plugin('setting');
        $this->version = (string) $setting('mirador_version', '3');
        return $this->render($urlManifest, $options, 'multiple');
    }
}
