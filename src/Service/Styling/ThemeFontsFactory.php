<?php
namespace AgileThemeTools\Service\Styling;

use AgileThemeTools\Styling\ThemeFonts;
use AgileThemeTools\ThemeBuild\BuildManager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ThemeFontsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ThemeFonts(
            $services->get('Omeka\Settings'),
            $services->get(BuildManager::class),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\HttpClient')
        );
    }
}
