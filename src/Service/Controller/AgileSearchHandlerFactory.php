<?php

namespace AgileThemeTools\Service\Controller;

use AgileThemeTools\Controller\AgileSearchHandler;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AgileSearchHandlerFactory implements FactoryInterface
{
    /**
     * Create the Html block layout service.
     *
     * @param ContainerInterface $serviceLocator
     * @return AgileSearchHandler
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $htmlPurifier = $serviceLocator->get('Omeka\HtmlPurifier');
        $api = $serviceLocator->get('Omeka\ApiManager');
        $siteSlug = $serviceLocator->get('Application')->getMvcEvent()->getRouteMatch()->getParam('site-slug');
        $site = $api->read('sites', ['slug' => $siteSlug])->getContent();
        $conn = $serviceLocator->get('Omeka\Connection');
        return new AgileSearchHandler($api, $htmlPurifier,$siteSlug,$site,$conn);
    }
}
