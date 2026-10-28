<?php
namespace AgileThemeTools\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use AgileThemeTools\View\Helper\AgileSearchHelper;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AgileSearchHelperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
      $settings = $services->get('Omeka\Settings');
      $siteSettings = $services->get('Omeka\Settings\Site');
      $api = $services->get('Omeka\ApiManager');

      return new AgileSearchHelper($services->get('AgileSearchHandler'),$settings,$siteSettings,$api,null,$services);
    }
}
