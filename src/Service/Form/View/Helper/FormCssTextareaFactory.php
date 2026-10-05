<?php
namespace AgileThemeTools\Service\Form\View\Helper;

use AgileThemeTools\Form\View\Helper\FormCssTextarea;
use AgileThemeTools\Styling\CustomCss;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class FormCssTextareaFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new FormCssTextarea($services->get(CustomCss::class), $services->get('Omeka\ApiManager'));
    }
}
