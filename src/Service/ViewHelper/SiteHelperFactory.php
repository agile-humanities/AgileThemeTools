<?php

namespace AgileThemeTools\Service\ViewHelper;

use AgileThemeTools\View\Helper\SiteHelper;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

/**
 * Service factory for the siteHelper view helper.
 */
class SiteHelperFactory implements FactoryInterface
{
  /**
   * Create and return the SiteAssetUrl view helper
   *
   * @return SiteHelper
   */
  public function __invoke(ContainerInterface|\Psr\Container\ContainerInterface $container, $requestedName, array $options = null)
  {
    $apiManager = $container->get('Omeka\ApiManager');
    $routeMatch = $container->get('Application')->getMvcEvent()->getRouteMatch();

    $siteSlug = $routeMatch?->getParam('site-slug');
    $site = $siteSlug ? $apiManager->read('sites', ['slug' => $siteSlug])->getContent() : null;
    return new SiteHelper($site,$siteSlug);

  }
}
