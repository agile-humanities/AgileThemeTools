<?php
namespace AgileThemeTools\View\Helper;

/**
 * Universal Viewer for a manifest URL — the UniversalViewer module's helper exposed for a typed URL (the IIIF
 * presentation page block's fallback when Mirador is absent, design review D2). Loaded only when that module is
 * present (the block checks class_exists before asking for it).
 */
class UniversalViewerManifest extends \UniversalViewer\View\Helper\UniversalViewer
{
    public function manifest(string $urlManifest, array $options = []): string
    {
        $view = $this->getView();
        $this->isSite = $view->status()->isSiteRequest();
        $this->version = $this->isSite
            ? (string) $view->siteSetting('universalviewer_version', '4')
            : (string) $view->setting('universalviewer_version', '4');
        return $this->render($urlManifest, $options, 'items');
    }
}
