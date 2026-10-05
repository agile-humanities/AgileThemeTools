<?php
namespace AgileThemeTools\Service\Form\View\Helper;

use AgileThemeTools\Form\View\Helper\FormPropertyList;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class FormPropertyListFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new FormPropertyList($services->get('Omeka\ApiManager'));
    }
}
