<?php
namespace AgileThemeTools\Site\ResourcePageBlockLayout;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

/**
 * Item Summary: the Summary / Full Metadata / Transcription tabs.
 *
 * Pairs with the Item Header block (ItemHeading). Kept separate so other blocks,
 * e.g. Downloads, can sit between the heading and the tabs.
 */
class ItemSummary implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Item Summary'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return ['items'];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        return $view->partial('common/resource-page-block-layout/item-summary', ['resource' => $resource]);
    }
}
