<?php
namespace AgileThemeTools\Service\Delegator;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;

/**
 * Map this module's form elements to the view helpers that render them.
 */
class FormElementDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $container, $name, callable $callback, array $options = null)
    {
        /** @var \Laminas\Form\View\Helper\FormElement $formElement */
        $formElement = $callback();
        $formElement->addClass(\AgileThemeTools\Form\Element\PropertyList::class, 'formPropertyList');
        $formElement->addClass(\AgileThemeTools\Form\Element\CssTextarea::class, 'formCssTextarea');
        $formElement->addClass(\AgileThemeTools\Form\Element\ColourValue::class, 'formColourValue');
        $formElement->addClass(\AgileThemeTools\Form\Element\FontStack::class, 'formFontStack');
        return $formElement;
    }
}
