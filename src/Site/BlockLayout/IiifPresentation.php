<?php
namespace AgileThemeTools\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;

/**
 * Omeka's IIIF presentation page block (a manifest URL), rendered in the site's own viewer: Mirador when the
 * Mirador module is active, else Universal Viewer when that module is active, else Omeka's built-in viewer
 * (the core block's iframe). Design review D2. Registered under the core block's name, so it replaces it.
 *
 * The viewers take the site's Mirador / UV settings (the Agile Mirador preset: dark theme, image tools).
 */
class IiifPresentation extends \Omeka\Site\BlockLayout\IiifPresentation
{
    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = 'common/faceted-browse/block-layout/faceted-browse-preview')
    {
        $blockData = $this->getBlockData($block);
        $url = $blockData['manifest_url'];
        if (!$url) {
            return;
        }
        $plugins = $view->getHelperPluginManager();
        $title = (string) $blockData['title'];
        $heading = ($title !== '' && $blockData['show_title']) ? sprintf('<h3>%s</h3>', $view->escapeHtml($title)) : '';

        if (class_exists('Mirador\View\Helper\Mirador') && $plugins->has('mirador')) {
            $html = $plugins->get('miradorManifest')->manifest($url);
            if ($html !== '') {
                return $heading . '<div class="iiif-presentation iiif-presentation--mirador">' . $html . '</div>';
            }
        }
        if (class_exists('UniversalViewer\View\Helper\UniversalViewer') && $plugins->has('universalViewer')) {
            $html = $plugins->get('universalViewerManifest')->manifest($url);
            if ($html !== '') {
                return $heading . '<div class="iiif-presentation iiif-presentation--uv">' . $html . '</div>';
            }
        }
        return '<div class="iiif-presentation iiif-presentation--core">' . parent::render($view, $block, $templateViewScript) . '</div>';
    }
}
