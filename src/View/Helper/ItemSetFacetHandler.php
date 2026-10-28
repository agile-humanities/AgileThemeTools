<?php

namespace AgileThemeTools\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Laminas\View\View;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Response;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;

class ItemSetFacetHandler extends AbstractHelper
{

  protected $api;
  public $public_item_sets = [];

  public function __construct($api) {
    $this->api = $api;
    //$this->public_item_sets = $this->getPublicItemSets();
  }

  public function getPublicItemSets():void {
    $view = $this->view;
    $plugins = $view->getHelperPluginManager();

    /** @var Api $api */
    $api = $plugins->get('api');

    $publicItemSets = [];

    $itemSets = $api->search('item_sets')->getContent();

    /** @var ItemSetRepresentation $itemSet */
    foreach ($itemSets as $itemSet) {
      if ($itemSet && $itemSet instanceof ItemSetRepresentation && $itemSet->isPublic()) {
        $publicItemSets[$itemSet->id()] = $itemSet->title();
      }
    }

    $this->public_item_sets = $publicItemSets;
  }

  private function getItemSetResourceByValue($value):Response {
    $view = $this->view;
    $plugins = $view->getHelperPluginManager();

    /** @var Api $api */
    $api = $plugins->get('api');

    /** @var Response $response */
    $response = $api->searchOne('item_sets',[
      'filter' => [
        [
          'field' => 'dcterms:title',
          'val' => $value,
          'type' => 'eq',
        ],
      ],
    ]);

    return $response;
  }

  public function getItemSetLabelById($value) {
    // Ugly, but...
    if ((int) $value != $value) {
      return $value;
    }


    $view = $this->view;
    $plugins = $view->getHelperPluginManager();

    /** @var Api $api */
    $api = $plugins->get('api');

    /** @var Response $response */
    /*$response = $api->searchOne('item_sets',[
      'filter' => [
        [
          'field' => 'o:id',
          'val' => $value,
          'type' => 'eq',
        ],
      ],
    ]);*/

    $itemSets = $api->search('item_sets')->getContent();

    /** @var ItemSetRepresentation $itemSet */
    foreach($itemSets as $itemSet) {
      if ($itemSet->id() == $value) {
        return $itemSet->title();
      }
    }

    return $value;
  }

  public function getItemSetById($value):ItemSetRepresentation|null {
    // Ugly, but...
    if ((int) $value != $value) {
      return null;
    }


    $view = $this->view;
    $plugins = $view->getHelperPluginManager();

    /** @var Api $api */
    $api = $plugins->get('api');

    /** @var Response $response */
    /*$response = $api->searchOne('item_sets',[
      'filter' => [
        [
          'field' => 'o:id',
          'val' => $value,
          'type' => 'eq',
        ],
      ],
    ]);*/

    // A search, not a read: it honours visibility, and an unknown id (a stale facet link) gives null, not an error.
    return $api->search('item_sets', ['id' => (int) $value, 'limit' => 1])->getContent()[0] ?? null;
  }

  /**
   * @param $value
   * @return bool
   */

  public function isPublicItemSet($value):bool {
    if (is_string($value)) {
      $response = $this->getItemSetResourceByValue($value);

      /** @var ItemSetRepresentation $itemSet */

      $itemSet = $response->getContent();


      if ($response->getTotalResults() && $response->getContent()->resourceName() === 'item_sets' && $response->getContent()->isPublic()) {

        return true;
      }
    }
    return false;
  }
}
