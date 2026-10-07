<?php
namespace AgileThemeTools\Service\Styling;

use AgileThemeTools\Styling\BasicStyles;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class BasicStylesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new BasicStyles($services->get('Omeka\Settings'));
    }
}
