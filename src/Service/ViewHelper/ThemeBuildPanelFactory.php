<?php
namespace AgileThemeTools\Service\ViewHelper;

use AgileThemeTools\ThemeBuild\BuildManager;
use AgileThemeTools\View\Helper\ThemeBuildPanel;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ThemeBuildPanelFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ThemeBuildPanel($services->get(BuildManager::class), $services->get('FormElementManager'));
    }
}
