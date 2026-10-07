<?php
namespace AgileThemeTools\Service\ViewHelper;

use AgileThemeTools\Styling\BasicStyles;
use AgileThemeTools\View\Helper\AgileBasicStyles;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AgileBasicStylesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new AgileBasicStyles($services->get(BasicStyles::class));
    }
}
