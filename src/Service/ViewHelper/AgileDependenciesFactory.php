<?php
namespace AgileThemeTools\Service\ViewHelper;

use AgileThemeTools\Dependency\DependencyAudit;
use AgileThemeTools\Patch\PatchManager;
use AgileThemeTools\View\Helper\AgileDependencies;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AgileDependenciesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new AgileDependencies($services->get(DependencyAudit::class), $services->get(PatchManager::class));
    }
}
