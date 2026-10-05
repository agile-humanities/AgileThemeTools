<?php
namespace AgileThemeTools\Service\Styling;

use AgileThemeTools\Styling\CustomCss;
use AgileThemeTools\ThemeBuild\BuildManager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CustomCssFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $local = $services->get('Config')['file_store']['local'] ?? [];
        return new CustomCss(
            $services->get('Omeka\Settings'),
            $services->get('Omeka\ApiManager'),
            $services->get(BuildManager::class),
            $services->get('Omeka\EntityManager'),
            $local['base_path'] ?: (OMEKA_PATH . '/files'),
            $local['base_uri'] ?: null
        );
    }
}
