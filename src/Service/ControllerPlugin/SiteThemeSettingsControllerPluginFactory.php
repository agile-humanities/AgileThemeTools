<?php
namespace AgileThemeTools\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Omeka\Mvc\Controller\Plugin\Settings;
use Laminas\ServiceManager\Factory\FactoryInterface;

/*
 * Extends controllers to supply SiteThemeSettings and their parents methods. Instantiated in module.config.php.
 */

class SiteThemeSettingsControllerPluginFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface|\Psr\Container\ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Settings($container->get('AgileThemeTools\Settings\SiteThemeSettings'));
    }
}
