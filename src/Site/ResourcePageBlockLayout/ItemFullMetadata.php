<?php
namespace AgileThemeTools\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

/**
 * Item Full Metadata: the item's whole record as a block of its own (the Southern Foodways "Full Metadata" panel),
 * for the item page's main region — the Summary block above then carries the summary alone (design review G6).
 *
 * The properties and their order come from the theme setting "Full Metadata Terms" (full_metadata_terms), shared
 * with the Summary block's optional Full Metadata tab; none selected = every property, alphabetically by label.
 * Template: common/resource-page-block-layout/item-full-metadata (the module's; a theme may override it).
 */
class ItemFullMetadata implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Agile: Item Full Metadata'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return ['items'];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        return $view->partial('common/resource-page-block-layout/item-full-metadata', ['resource' => $resource]);
    }
}
