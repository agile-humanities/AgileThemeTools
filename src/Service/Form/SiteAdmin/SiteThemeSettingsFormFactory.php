<?php declare(strict_types=1);

namespace AgileThemeTools\Service\Form\SiteAdmin;

use AgileThemeTools\Form\SiteAdmin\SiteThemeSettingsForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SiteThemeSettingsFormFactory implements FactoryInterface
{
  public function __invoke(ContainerInterface|\Psr\Container\ContainerInterface $container, $requestedName, array $options = null): SiteThemeSettingsForm
  {

    /** @var \Laminas\Router\Http\RouteMatch $routeMatch */
    $apiManager = $container->get('Omeka\ApiManager');
    $routeMatch = $container->get('Application')->getMvcEvent()->getRouteMatch();
    $themeManager = $container->get('Omeka\Site\ThemeManager');

    if ($options && array_key_exists('site_id',$options)) {
      $site = $apiManager->read('sites', ['id' => $options['site_id']])->getContent();
    } else {
      $siteSlug = $routeMatch?->getParam('site-slug');
      $site = $siteSlug ? $apiManager->read('sites', ['slug' => $siteSlug])->getContent() : null;
    }

    return (new SiteThemeSettingsForm(null, $options ?? []))
      ->setApiManager($apiManager)
      ->setCurrentSite($site)
      ->setThemeManager($themeManager)
      ->setFormAdapterManager($container->get('AdvancedSearch\FormAdapterManager'))
      ->setSiteThemeSettings($container->get('AgileThemeTools\Settings\SiteThemeSettings'))
      ->setLogger($container->get('Omeka\Logger'));
  }
}
