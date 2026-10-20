<?php
namespace AgileThemeTools\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Builds the manifest-URL viewer helpers (View\Helper\MiradorManifest, UniversalViewerManifest) the way the viewer
 * modules build their own: with the current theme. Ask for one only after checking the module's class exists.
 */
class ViewerManifestFactory implements FactoryInterface
{
    const HELPERS = [
        'miradorManifest' => \AgileThemeTools\View\Helper\MiradorManifest::class,
        'universalViewerManifest' => \AgileThemeTools\View\Helper\UniversalViewerManifest::class,
    ];

    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $class = self::HELPERS[$requestedName] ?? $requestedName;
        $currentTheme = $services->get('Omeka\Site\ThemeManager')->getCurrentTheme();
        return new $class($currentTheme);
    }
}
