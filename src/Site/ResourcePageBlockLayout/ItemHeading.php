<?php
namespace AgileThemeTools\Site\ResourcePageBlockLayout;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

/**
 * Item Header: wayfinding, content type, title, subtitle and creator.
 *
 * Pairs with the Item Summary block. Together they replace the legacy Item Header
 * (ItemHeader), which rendered the heading and the summary/metadata tabs as one block.
 */
class ItemHeading implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Item Header'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return ['items'];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        $site = $view->currentSite();
        $siteUrl = $site ? $site->url() : '/';

        return $view->partial('common/resource-page-block-layout/item-heading', ['resource' => $resource, 'siteUrl' => $siteUrl]);
    }
}
