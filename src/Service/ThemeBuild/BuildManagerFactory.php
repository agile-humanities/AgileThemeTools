<?php
namespace AgileThemeTools\Service\ThemeBuild;

use AgileThemeTools\ThemeBuild\BuildManager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class BuildManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        $local = $config['file_store']['local'] ?? [];

        return new BuildManager(
            $services->get('Omeka\Site\ThemeManager'),
            $services->get('Omeka\ApiManager'),
            $services->get('AgileThemeTools\Settings\SiteThemeSettings'),
            $services->get('Omeka\Settings\Site'),
            $services->get('Omeka\Settings'),
            $services->get('Omeka\Cli'),
            $services->get('Omeka\EntityManager'),
            $local['base_path'] ?: (OMEKA_PATH . '/files'),
            $local['base_uri'] ?: null
        );
    }
}
