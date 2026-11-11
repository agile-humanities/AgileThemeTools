<?php
namespace AgileThemeTools\Service\Patch;

use AgileThemeTools\Patch\PatchManager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PatchManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new PatchManager(OMEKA_PATH);
    }
}
