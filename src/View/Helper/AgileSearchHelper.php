<?php
namespace AgileThemeTools\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\ValueRepresentation;
use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use Omeka\Entity\Item;
use Omeka\Settings\SiteSettings;
use Omeka\View\Helper\Api;
use Omeka\Api\Representation\SiteRepresentation;
use AdvancedSearch\View\Helper\GetSearchConfig;
use function PHPUnit\Framework\isInstanceOf;


/**
 * View helper for returning a path the site’s search page and generate facet links for Advanced Search and Solr
 */
class AgileSearchHelper extends AbstractHelper
{
  /** Global setting: properties whose values are never linked to search (see Module::addSettingFormElements()). */
  const UNLINKED_TERMS_SETTING = 'agilethemetools_unlinked_terms';

  /** Used until the setting is saved: properties whose values make poor search links. */
  const UNLINKED_TERMS_DEFAULT = [
    'dcterms:title',
    'dcterms:alternative',
    'dcterms:description',
    'dcterms:abstract',
    'dcterms:tableOfContents',
    'dcterms:bibliographicCitation',
    'dcterms:identifier',
    'dcterms:extent',
    'dcterms:rights',
    'dcterms:rightsHolder',
    'dcterms:license',
    'dcterms:accessRights',
    'bibo:identifier',
    'bibo:number',
    'bibo:locator',
  ];

  protected $agileSearchHandler;

  /** @var SiteSettings $siteSettings */
  protected $siteSettings;
  protected $settings;
  protected $api;
  /** The service manager, for SearchSolr\Stdlib\SolrCore (see solrMaps()). */
  protected $services;
  protected $search_inclusion_list = [];

  /** @var string[]|null Facet fields on the site's main search config. */
  protected $facetFields;

  /** @var array Solr fields mapped to each property term, keyed by resource name. */
  protected $solrFieldsByTerm = [];

  /** @var string[]|null Terms whose values are never linked to search. */
  protected $unlinkedTerms;


  public function __construct($agileSearchHandler,$settings,$siteSettings,$api,$mapController=null,$services=null) {
    $this->agileSearchHandler = $agileSearchHandler;
    $this->settings = $settings;
    $this->siteSettings = $siteSettings;
    $this->api = $api;
    $this->services = $services;
  }

  /**
   * The Solr maps of a search engine for a resource type. SearchSolr 3.5.7x dropped the "solr_cores" API resource
   * (a core is now the engine itself, wrapped by SearchSolr\Stdlib\SolrCore), so maps are read through that wrapper.
   * Earlier SearchSolr (3.5.69 and before) has no wrapper: the core is its own API resource, named by the engine's
   * adapter settings. Both are supported, because a missing class here is a fatal error on every item page.
   * Defaults to the engine of the site's main search config; $engineId selects another (with SearchSolr before
   * 3.5.7x, the old core id, as callers passed before).
   *
   * @return \SearchSolr\Api\Representation\SolrMapRepresentation[]
   */
  protected function solrMaps($resourceName, $engineId = null): array {
    if (class_exists(\SearchSolr\Stdlib\SolrCore::class)) {
      $engine = $engineId
        ? $this->api->read('search_engines', $engineId)->getContent()
        : $this->getAdvancedSearchConfig()->searchEngine();
      return (new \SearchSolr\Stdlib\SolrCore($engine, $this->services))->mapsByResourceName($resourceName);
    }

    $coreId = $engineId
      ?? ($this->getAdvancedSearchConfig()->searchEngine()->settings()['engine_adapter']['solr_core_id'] ?? null);
    return $coreId
      ? $this->api->read('solr_cores', $coreId)->getContent()->mapsByResourceName($resourceName)
      : [];
  }

  public function __invoke() {
    return $this->getSearchHandler();
  }

  public function getSearchHandler() {
    return $this->agileSearchHandler;
  }

  public function siteSearchPath() {
    return $this->agileSearchHandler->siteSearchPath();
  }

  /*
   * Returns a URL to the default Advanced Search page filtering for the value.
   * @param $resourceArg can be an ItemRepresentation, Resource, or the resource name ("item", "item-set", etc.)
   * as a string.
   */

  public function getSolrSearchUrl(ValueRepresentation $value) {

    if (!$this->isLinkableProperty($value)) {
      return '';
    }

    return $this->getValueSearchUrl($value);
  }

  /**
   * Returns a URL to the site's Advanced Search page for a value, or '' when the value should not link to search.
   *
   * - The property is a facet on the site's main search config: the link preselects that facet.
   * - Otherwise, if a string Solr field (_ss, then _s) is mapped: the link filters on it. There is no facet to
   *   preselect, but the results are the same.
   * - Otherwise (full-text fields only, or no mapping): ''.
   *
   * Linked resources and URIs return '': they link to their own destination (see ValueDisplay). So do properties in
   * the global "Properties not linked to search" setting (see getUnlinkedTerms()).
   */
  public function getValueSearchUrl(ValueRepresentation $value): string {
    if ($value->valueResource() || $value->uri() || (string) $value->value() === '') {
      return '';
    }

    if (in_array($value->property()->term(), $this->getUnlinkedTerms(), true)) {
      return '';
    }

    try {
      if (!$this->isSolrSearch()) {
        return '';
      }

      $fields = $this->getSolrFieldsByTerm($value->resource()->resourceName())[$value->property()->term()] ?? [];
      $field = array_values(array_intersect($fields, $this->getConfiguredFacetFields()))[0] ?? null;

      if ($field === null) {
        foreach (['_ss', '_s'] as $suffix) {
          foreach ($fields as $candidate) {
            if (substr($candidate, -strlen($suffix)) === $suffix) {
              $field = $candidate;
              break 2;
            }
          }
        }
      }

      if ($field === null) {
        return '';
      }

      return $this->getAdvancedSearchUrl() . '?' . http_build_query(['facet' => [$field => [$value->value()]]]);
    } catch (\TypeError $e) {
      // The site has no main search config (getAdvancedSearchConfig() cannot return one).
      return '';
    }
  }

  /**
   * Returns the terms whose values are never linked to search: the global "Properties not linked to search" setting,
   * or UNLINKED_TERMS_DEFAULT until it is saved. A list saved empty leaves every property linkable.
   */
  public function getUnlinkedTerms(): array {
    if ($this->unlinkedTerms === null) {
      $value = $this->settings->get(self::UNLINKED_TERMS_SETTING);
      $this->unlinkedTerms = $value === null
        ? self::UNLINKED_TERMS_DEFAULT
        : $this->view->plugin('propertyTerms')->parse($value);
    }
    return $this->unlinkedTerms;
  }

  /**
   * Returns the facet fields configured on the site's main search config.
   */
  public function getConfiguredFacetFields(): array {
    if ($this->facetFields === null) {
      $this->facetFields = [];
      $facetSettings = $this->getAdvancedSearchConfig()->setting('facet');
      foreach ($facetSettings['facets'] ?? [] as $key => $facet) {
        $field = is_array($facet) ? ($facet['field'] ?? $key) : $facet;
        if (is_string($field) && $field !== '') {
          $this->facetFields[] = $field;
        }
      }
    }
    return $this->facetFields;
  }

  /**
   * Returns every Solr field mapped to each property term, e.g. ['dcterms:creator' => ['dcterms_creator_s',
   * 'dcterms_creator_ss', 'dcterms_creator_txt']]. Unlike getSolrMappings(), nothing is dropped.
   */
  public function getSolrFieldsByTerm($resourceName): array {
    if (!isset($this->solrFieldsByTerm[$resourceName])) {
      $fields = [];
      if ($this->isSolrSearch()) {
        foreach ($this->solrMaps($resourceName) as $map) {
          $fields[$map->source()][] = $map->fieldName();
        }
      }
      $this->solrFieldsByTerm[$resourceName] = $fields;
    }
    return $this->solrFieldsByTerm[$resourceName];
  }

  /**
   * Returns an url to a
   * @param ValueRepresentation $value
   * @return string|null
   */
  public function getFacetedSearchUrl(ValueRepresentation $value) {
    if ($this->isSolrSearch()) {
      return $this->getSolrSearchUrl($value);
    }

    $view = $this->view;
    $plugins = $view->getHelperPluginManager();
    /** @var SiteRepresentation $defaultSite */
    $defaultSite = $plugins->get('defaultSite')();
    $propId = $value->property()->term();
    $propValue= $value->value();

    $searchUrl = $this->getAdvancedSearchUrl() ?? $defaultSite->siteUrl() . '/item';

    return $searchUrl . '?'.
      urlencode("filter[0][join]")  .'=and&'.
      urlencode("filter[0][field]") ."={$propId}&".
      urlencode("filter[0][type]")  .'=eq&'.
      urlencode("filter[0][val]")   . "={$propValue}";
  }

  public function getFacetedItemSetUrl(ItemSetRepresentation $itemSet,$use_search_page=true) {
    if ($use_search_page !== true) {
      return $itemSet->url();
    }
    $searchUrl = $this->getAdvancedSearchUrl();
    $propValue = $itemSet->id();

    //facet%5Bitem_set_id%5D%5B0%5D=

    return $searchUrl .'?facet%5Bitem_set_id%5D%5B0%5D='. $propValue;

    /*
    return $searchUrl . '?'.
      urlencode("filter[0][join]")  .'=and&'.
      urlencode("filter[0][field]") ."=item_set_id&".
      urlencode("filter[0][type]")  .'=eq&'.
      urlencode("filter[0][val]")   . "={$propValue}";
    */


  }

  /**
   * This is a stub function to return an Advanced Search + Solr url for an item set based on Item Set ID.
   * It's not very foolproof, so use with caution.
   *
   * @param $id
   * @return string
   */

  public function getSolrItemSetUrl($id) {
    if (!$this->isSolrSearch()) {
      return '';
    }

    /** @var ItemSetRepresentation $itemSet */
    $itemSet = $this->api->read('item_sets', ['id' => $id])->getContent();
    return $this->getAdvancedSearchUrl() . "?" . urlencode("facet[item_set_dcterms_title_ss][0]") . "=" . urlencode($itemSet->title());
  }

  /**
   * Gets the default Advanced Search Url for the current site.
   * @return string|null
   */

  public function getAdvancedSearchUrl() {
    $advancedSearchConfig = $this->getAdvancedSearchConfig();
    return $advancedSearchConfig->url();
  }

  /**
   * Tests if the default search engine is based on Solr
   * @return bool
   */

  public function isSolrSearch() {
    $advancedSearchConfig = $this->getAdvancedSearchConfig();
    $searchEngine = $advancedSearchConfig->searchEngine();
    return $searchEngine->engineAdapter() instanceof \SearchSolr\EngineAdapter\Solarium;

  }

  /*
   * Returns an array of solr mappings to convert term names into their solr map.
   * Can be used to create working Solr queries.
   *
   * Note that a term can have multiple mapped solr fields. This function only returns one mapping so there
   * is no ambiguity. _s and _ss mappings are preferred over _txt where muptiple options exist.
   */

  public function getSolrMappings($resourceName,$solrCoreId=null):array  {
    // @todo For now just return the base url. This function should be abstracted.
    if (!$this->isSolrSearch()) {
      return [];
    }

    /** @var \SearchSolr\Api\Representation\SolrMapRepresentation[] $maps */
    $maps = $this->solrMaps($resourceName, $solrCoreId);

    $solrMappings = [];

    foreach($maps as $map) {
      $solrMappings[$map->fieldName()] = $map->source();
    }

    // This is a little too clever and needs to be hardened. We want to prefer mapped _ss fields over _txt when
    // multiple solr mappings for a term name exist. This simply sorts all the solr field values, then flips
    // the array so that values become keys. As per the array_flip guidance, the first value will be chosen and
    // the rest will be removed, so _ss will be chosen over _txt. There needs to be better logic here.

    asort($solrMappings);

    return array_flip($solrMappings);

  }

  /*
   * Sets a search inclusion list. Allows you to set a list of properties that should be linked to the
   * advanced search page. If no inclusion list is supplied then all propertyies are linked.
   *
   * @todo: The Advanced Resource Template modules allows you to set an inclusion list via a configurable settings.
   *   This helper should support that setting if it exists.
   * @todo: Allow for an exclusion list to also be set as per the Advanced Resource Template module.
   */

  public function setSearchInclusionList($fields): void {
    $this->search_inclusion_list = $fields;
  }

  /*
   * Checks to see if a property inclusion list has been set.
   */

  public function hasSearchInclusionList():bool {
    return count($this->search_inclusion_list);
  }

  /*
   * Checks to see if value is linkable.
   */

  public function isLinkableProperty(ValueRepresentation $value):bool {
    if ($this->hasSearchInclusionList() && !in_array($value->property()->term(),$this->search_inclusion_list)) {
      return false;
    }

    return true;

  }

  /*
   * Get the current advanced search config for the site.
   */

  public function getAdvancedSearchConfig(): SearchConfigRepresentation {
    $view = $this->view;
    $plugins = $view->getHelperPluginManager();
    $status = $plugins->get('status');
    $isAdmin = $status->isAdminRequest();
    $isSite = $status->isSiteRequest();
    $params = $plugins->get('params');
    $searchConfigId = null;


    if ($isSite) {
      /** @var SiteRepresentation $site */
      $site = $plugins->get('currentSite')();
      $searchConfigId = $this->siteSettings->get('advancedsearch_main_config');
    }

    /** @var GetSearchConfig $getSearchConfig */
    $getSearchConfig = $plugins->get('getSearchConfig');
    return $getSearchConfig($searchConfigId);
  }
}
