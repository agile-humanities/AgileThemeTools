<?php
namespace AgileThemeTools\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\ValueRepresentation;
use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use Omeka\Settings\SiteSettings;
use Omeka\View\Helper\Api;
use Omeka\Api\Representation\SiteRepresentation;
use AdvancedSearch\View\Helper\GetSearchConfig;


/**
 * View helper for returning a path the site’s search page and generate facet links for Advanced Search and Solr
 */
class AgileSearchHelper extends AbstractHelper
{
  protected $agileSearchHandler;

  /** @var SiteSettings $siteSettings */
  protected $siteSettings;
  protected $settings;


  public function __construct($agileSearchHandler,$settings,$siteSettings,$mapController=null) {
    $this->agileSearchHandler = $agileSearchHandler;
    $this->settings = $settings;
    $this->siteSettings = $siteSettings;
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

  public function getSolrSearchUrl($value) {

    //$resourceName = $resourceArg instanceof ItemRepresentation || $resourceArg instanceof Resource ? $resourceArg->resourceName() : $resourceArg;

    $solrMappings = $this->getSolrMappings($value->resource()->resourceName());
    $term = $value->property()->term();
    if (array_key_exists($term,$solrMappings) && $value->value()) {
      return $this->getAdvancedSearchUrl() . "?" . urlencode("facet[{$solrMappings[$term]}][0]") . "=" . urlencode($value->value());
    } else {
      return $this->getAdvancedSearchUrl();
    }

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

    return $defaultSite->siteUrl() . '/item?'.
      urlencode("filter[0][join]")  .'=and&'.
      urlencode("filter[0][field]") ."={$propId}&".
      urlencode("filter[0][type]")  .'=eq&'.
      urlencode("filter[0][val]")   . "={$propValue}";
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

    $advancedSearchConfig = $this->getAdvancedSearchConfig();
    $searchEngine = $advancedSearchConfig->searchEngine();
    $view = $this->view;
    $plugins = $view->getHelperPluginManager();

    /** @var Api $api */
    $api = $plugins->get('api');


    $searchEngineSettings = $searchEngine->settings();
    $solrCoreId = $solrCoreId ?? $searchEngineSettings['engine_adapter']['solr_core_id'];

    $solrCore = $api->read('solr_cores', $solrCoreId)->getContent();

    /** @var \SearchSolr\Api\Representation\SolrMapRepresentation[] $maps */
    $maps = $solrCore->mapsByResourceName($resourceName);

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
