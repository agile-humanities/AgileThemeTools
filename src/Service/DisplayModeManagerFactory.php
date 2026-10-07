<?php
namespace AgileThemeTools\Service;

use AgileThemeTools\DisplayMode\Manager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DisplayModeManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        return new Manager($services, $config['display_modes'] ?? []);
    }
}
