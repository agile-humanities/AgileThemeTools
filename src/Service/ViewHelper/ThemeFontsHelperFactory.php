<?php
namespace AgileThemeTools\Service\ViewHelper;

use AgileThemeTools\Styling\ThemeFonts;
use AgileThemeTools\View\Helper\ThemeFontsHelper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ThemeFontsHelperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $site = null;
        $routeMatch = $services->get('Application')->getMvcEvent()->getRouteMatch();
        $siteSlug = $routeMatch?->getParam('site-slug');
        if ($siteSlug) {
            try {
                $site = $services->get('Omeka\ApiManager')->read('sites', ['slug' => $siteSlug])->getContent();
            } catch (\Exception $e) {
                $site = null;
            }
        }
        return new ThemeFontsHelper($services->get(ThemeFonts::class), $site);
    }
}
