<?php
namespace AgileThemeTools\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use AgileThemeTools\View\Helper\AgileSearchHelper;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AgileSearchHelperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new AgileSearchHelper($services->get('AgileSearchHandler'));
    }
}
