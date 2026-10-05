<?php

namespace AgileThemeTools\Service\Settings;

use AgileThemeTools\Settings\SiteThemeSettings;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class SiteThemeSettingsFactory implements FactoryInterface
{
  /**
   * Create the site settings service.
   *
   * @return SiteThemeSettings
   */
  public function __invoke(ContainerInterface|\Psr\Container\ContainerInterface $container, $requestedName, array $options = null)
  {
    return new SiteThemeSettings(
      $container->get('Omeka\Connection'),
      $container->get('Omeka\Status')
    );
  }
}
