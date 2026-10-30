<?php

namespace AgileThemeTools\Service\ViewHelper;

use AgileThemeTools\View\Helper\SiteAssetUrl;
use Omeka\Module\Manager as ModuleManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

/**
 * Service factory for the assetUrl view helper.
 */
class SiteAssetUrlFactory implements FactoryInterface
{
    /**
     * Create and return the SiteAssetUrl view helper
     *
     * @return SiteAssetUrl
     */
    public function __invoke(ContainerInterface|\Psr\Container\ContainerInterface $container, $requestedName, array $options = null)
    {
        $currentTheme = $container->get('Omeka\Site\ThemeManager')->getCurrentTheme();
        $activeModules = $container->get('Omeka\ModuleManager')
            ->getModulesByState(ModuleManager::STATE_ACTIVE);

        $apiManager = $container->get('Omeka\ApiManager');
        $routeMatch = $container->get('Application')->getMvcEvent()->getRouteMatch();
        $siteSlug = $routeMatch?->getParam('site-slug');
        $site = $siteSlug ? $apiManager->read('sites', ['slug' => $siteSlug])->getContent() : null;

        $config = $container->get('Config');

        $assetConfig = $config['assets'];
        if ($assetConfig['use_externals']) {
            $externals = $assetConfig['externals'];
        } else {
            $externals = [];
        }

        $siteSettings = $container->get('Omeka\Settings\Site');

        $themeSettings = $siteSettings->get($currentTheme->getSettingsKey(), []);

        // The site's build in the files directory, when per-site styling is on and a build exists there.
        $siteBuild = null;
        if ($site && $currentTheme && \AgileThemeTools\ThemeBuild\BuildManager::stylingEnabled($themeSettings)) {
            /** @var \AgileThemeTools\ThemeBuild\BuildManager $builder */
            $builder = $container->get(\AgileThemeTools\ThemeBuild\BuildManager::class);
            $dir = $builder->getBuildDir($currentTheme, $site);
            $manifest = $builder->readManifest($dir);
            if ($manifest) {
                $local = $config['file_store']['local'] ?? [];
                $siteBuild = [
                    'dir' => $builder->getAssetsDir($currentTheme, $site),
                    'uri' => $builder->getAssetsUri($currentTheme, $site, ''),
                    'absoluteUri' => !empty($local['base_uri']),
                    // Changes with every build, so browsers refetch the assets after a rebuild.
                    'version' => $currentTheme->getIni('version') . '-' . substr((string) ($manifest['configHash'] ?? ''), 0, 8) . '-' . substr(sha1((string) ($manifest['finishedAt'] ?? '')), 0, 8),
                ];
            }
        }

        $helper = new SiteAssetUrl($currentTheme, $themeSettings, $site, $activeModules, $externals, $siteBuild);
        return $helper;
    }
}
