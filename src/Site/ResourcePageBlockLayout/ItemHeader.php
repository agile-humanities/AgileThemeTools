<?php
namespace AgileThemeTools\Site\ResourcePageBlockLayout;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

class ItemHeader implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Item Header (legacy)'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return ['items'];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        /* @todo The proper way to get the site URL would be to build a factor and inject the
           Service Locator, e.g.
           $api = $serviceLocator->get('Omeka\ApiManager');
           $route = $serviceLocator->get('Application')->getMvcEvent()->getRouteMatch();
           $siteSlug = null;
           $site=null;

           if ($route) {
             $siteSlug = $route->getParam('site-slug');
             $site = $api->read('sites', ['slug' => $siteSlug])->getContent();
           }
        */

        $sitePath= $resource->siteUrl();

        $siteUrl = '/';

        if ($sitePath) {
          $siteArray = explode('/',$sitePath);
          if (count($siteArray) > 1) {
            $siteUrl = '/s/' . $siteArray[2];
          }
        }

        return $view->partial('common/resource-page-block-layout/item-header', ['resource' => $resource,'siteUrl'=>$siteUrl]);
    }
}
