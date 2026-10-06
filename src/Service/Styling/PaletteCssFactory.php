<?php
namespace AgileThemeTools\Service\Styling;

use AgileThemeTools\Styling\PaletteCss;
use AgileThemeTools\ThemeBuild\BuildManager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PaletteCssFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new PaletteCss($services->get(BuildManager::class));
    }
}
