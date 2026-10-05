<?php
namespace AgileThemeTools\Service\ViewHelper;

use AgileThemeTools\View\Helper\PropertyTerms;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PropertyTermsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // Defaults the current theme declares for its settings: theme.ini [config] elements.<name>.attributes.value.
        // themeSetting() does not fall back to these, so a setting a site has never saved would otherwise be empty.
        $defaults = [];
        $theme = $services->get('Omeka\Site\ThemeManager')->getCurrentTheme();
        $configSpec = $theme ? $theme->getConfigSpec() : [];
        foreach ($configSpec['elements'] ?? [] as $key => $elementSpec) {
            if (isset($elementSpec['attributes']['value'])) {
                $defaults[$elementSpec['name'] ?? $key] = $elementSpec['attributes']['value'];
            }
        }

        return new PropertyTerms($defaults);
    }
}
