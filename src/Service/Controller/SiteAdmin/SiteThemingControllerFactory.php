<?php
namespace AgileThemeTools\Service\Controller\SiteAdmin;

use AgileThemeTools\Controller\SiteAdmin\SiteThemingController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SiteThemingControllerFactory implements FactoryInterface
{
  public function __invoke(ContainerInterface|\Psr\Container\ContainerInterface $container, $requestedName, array $options = null)
  {
    $apiManager = $container->get('Omeka\ApiManager');
    $routeMatch = $container->get('Application')->getMvcEvent()->getRouteMatch();
    $siteSlug = $routeMatch?->getParam('site-slug');
    $site = $siteSlug ? $apiManager->read('sites', ['slug' => $siteSlug])->getContent() : null;

    return new SiteThemingController(
      $container->get('Omeka\Site\ThemeManager'),
      $site,
      $container->get(\AgileThemeTools\ThemeBuild\BuildManager::class),
      $container->get(\AgileThemeTools\Styling\ThemeFonts::class),
      //$container->get('Omeka\Site\NavigationLinkManager'),
      //$container->get('Omeka\Site\NavigationTranslator'),
      //$container->get('Omeka\ResourcePageBlockLayoutManager'),
    );
  }
}
