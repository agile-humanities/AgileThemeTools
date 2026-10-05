<?php
namespace AgileThemeTools\Service\ViewHelper;

use AgileThemeTools\Styling\CustomCss;
use AgileThemeTools\View\Helper\CustomCssHelper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CustomCssHelperFactory implements FactoryInterface
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
        return new CustomCssHelper($services->get(CustomCss::class), $site);
    }
}
