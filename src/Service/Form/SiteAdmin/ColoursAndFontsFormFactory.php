<?php
namespace AgileThemeTools\Service\Form\SiteAdmin;

use AgileThemeTools\Form\SiteAdmin\ColoursAndFontsForm;
use AgileThemeTools\Styling\PaletteCss;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ColoursAndFontsFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // The inputs depend on the site's theme: the controller resolves them and passes them in.
        $form = new ColoursAndFontsForm(null, $options ?? []);
        $form->setInputs($options['inputs'] ?? []);
        return $form;
    }
}
