<?php
namespace AgileThemeTools\Service\Dependency;

use AgileThemeTools\Dependency\DependencyAudit;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DependencyAuditFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new DependencyAudit(
            $services->get('Omeka\ModuleManager'),
            $services->get('Omeka\Connection'),
            require DependencyAudit::manifestFile()
        );
    }
}
