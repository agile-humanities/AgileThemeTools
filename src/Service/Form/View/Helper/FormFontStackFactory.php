<?php
namespace AgileThemeTools\Service\Form\View\Helper;

use AgileThemeTools\Form\View\Helper\FormFontStack;
use AgileThemeTools\Styling\ThemeFonts;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class FormFontStackFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $site = null;
        $siteSlug = $services->get('Application')->getMvcEvent()->getRouteMatch()?->getParam('site-slug');
        if ($siteSlug) {
            try {
                $site = $services->get('Omeka\ApiManager')->read('sites', ['slug' => $siteSlug])->getContent();
            } catch (\Exception $e) {
                $site = null;
            }
        }
        return new FormFontStack($services->get(ThemeFonts::class), $services->get('Omeka\Site\ThemeManager'), $site);
    }
}
