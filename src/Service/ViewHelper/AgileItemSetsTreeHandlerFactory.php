<?php
namespace AgileThemeTools\Service\ViewHelper;

use AgileThemeTools\View\Helper\AgileItemSetsTreeHandler;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AgileItemSetsTreeHandlerFactory implements FactoryInterface
{
  public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
  {
    //$settings = $services->get('Omeka\Settings');
    //$siteSettings = $services->get('Omeka\Settings\Site');
    $api = $services->get('Omeka\ApiManager');

    return new AgileItemSetsTreeHandler($api);
  }
}
