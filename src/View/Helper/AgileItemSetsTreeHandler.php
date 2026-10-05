<?php

namespace AgileThemeTools\View\Helper;

use ItemSetsTree\ViewHelper\ItemSetsTree;
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\View;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Response;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;

class AgileItemSetsTreeHandler extends AbstractHelper
{

  protected $api;
  protected $view;
  public $public_item_sets = [];

  public function __construct($api) {
    $this->api = $api;
  }

  public function itemSetsTree():ItemSetsTree {
    $plugins = $this->view->getHelperPluginManager();
    return $plugins->get('itemSetsTree');
  }

  public function getPrimaryCollection(ItemSetRepresentation|ItemRepresentation $item):ItemSetRepresentation|null {

    if (!$item instanceof ItemRepresentation && !$item instanceof ItemSetRepresentation) {
      return null;
    }

    $itemSets = $item instanceof ItemRepresentation ? $item->itemSets() : [$item];
    /** @var \Omeka\Api\Representation\ItemSetRepresentation $primaryItemSet */
    $primaryItemSet = is_array($itemSets) && count($itemSets) > 0 ? array_shift($itemSets) : null;
    $primaryCollection = null;

    if ($primaryItemSet) {
      $ancestors = $primaryItemSet ? $this->itemSetsTree()->getAncestors($primaryItemSet) : [];
      if (is_array($ancestors) && count($ancestors) > 0) {
        $primaryCollection = array_pop($ancestors);
      }
    }


    return $primaryCollection;
  }

  public function getPrimaryCollectionLabel($item,$compact=false):string {
    $primaryCollection = $this->getPrimaryCollection($item);

    if (!$primaryCollection) {
      return !empty($item->value('dcterms:type')) ? $item->value('dcterms:type') : $item->resourceClass()->label() ?? 'Item';
    }

    $label_class = $this->getHtmlClass($primaryCollection->title());
    $itemSets = $item instanceof ItemRepresentation ? $item->itemSets() : [$item];
    $label = "<span class='primary-collection-label {$label_class}'>". $primaryCollection->title() .'</span>';

    if ($compact) {
      return $label;
    }

    if ($itemSets && count($itemSets) > 0) {
      $immediateItemSet = array_shift($itemSets);
      if ($immediateItemSet->title() !== $primaryCollection->title()) {
        $label .= " | ". $immediateItemSet->title();
      }
    } else {
      $label .= " | ".  $item->resourceClass()->label();
    }

    return $label ;

  }

  public function getHtmlClass($string) {
    return filter_var(strtolower(str_replace(' ','-',$string)),FILTER_SANITIZE_FULL_SPECIAL_CHARS);
  }

  public function getPrimaryCollectionClass($item):string {
    $primaryCollection = $this->getPrimaryCollection($item);
    return $primaryCollection ? $this->getHtmlClass($primaryCollection->title()) : 'no-primary-collection';
  }

}
