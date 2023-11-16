<?php

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2017-2020
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace AgileThemeTools\Controller;

use Search\Controller\IndexController;
use Search\Query;
use Search\Response;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

class AgileSearchIndexController extends IndexController {

  public function searchAction() {
    $pageId = (int) $this->params('id');
    $request = $this->rebuildProperties();
    $isPublic = $this->status()->isSiteRequest();
    if ($isPublic) {
      $site = $this->currentSite();
      $siteSettings = $this->siteSettings();
      $siteSearchPages = $siteSettings->get('search_pages', []);
      if (!in_array($pageId, $siteSearchPages)) {
        return $this->notFoundAction();
      }
      // Check if it is an item set redirection.
      $itemSetId = (int) $this->params('item-set-id');
      if ($itemSetId) {
        // May throw an not found exception.
        $this->api()->read('item_sets', $itemSetId);
      }
    } else {
      $site = null;
    }

    // The page is required, else there is no form.
    /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
    $searchPage = $this->api()->read('search_pages', $pageId)->getContent();

    $view = new ViewModel([
      // The form is not set in the view, but via helper searchingForm()
      // or via searchPage.
      'searchPage' => $searchPage,
      'site' => $site,
      'query' => new Query,
      // Set a default empty response and values to simplify view.
      'response' => new Response,
      'sortOptions' => [],
    ]);

    $qstring = '';

    if ($request && isset($request['property']) && $request['property'][0]['text']) {
      $props = $request['property'];
      $first = \array_shift($props);
      $qstring .= "{$first['type']}:\"{$first['text']}\"";
      foreach ($props as $prop) {
        $qstring .= " {$prop['joiner']}{$prop['type']}:\"{$prop['text']}\"";
      }
    }
    $item_sets = FALSE;
    if (isset($request['item_set_id'])) {
      $item_sets = array_filter($request['item_set_id']);
    }

    if ($request && $item_sets) {
      $id_string = '';
      foreach ($request['item_set_id'] as $item_Set_id) {
        $id_string .= "item_set_id_is:$item_Set_id ";
      }
      if ($qstring && $id_string) {
        $qstring .= " AND  ($id_string)";
      } else {
        $qstring = $id_string;
      }
    }
    $has_q = isset($request['q']) && $request['q'];
    if ($has_q) {
      $request['q'] = "\"{$request['q']}\"";
    }
    if ($has_q && $qstring) {
      $request['q'] .= " AND $qstring";
    } else {
      if (!isset($request['q'])) {
        $request['q'] = '';
      }
      $request['q'] .= $qstring;
    }
    // fixes default malconfiguration for facets
    if (isset ($request['limit'])) {
      $limits = $request['limit'];
      unset ($request['limit']);
      foreach ($limits as $key => $value) {
        $request['limit'][$key][0] = \array_values($value);
      }
    }

    $form = $this->searchForm($searchPage);
    // The form may be empty for a direct query.
    $isJsonQuery = !$form;

    if ($form) {
      $request = $this->validateSearchRequest($searchPage, $form, $request);
      if ($request === false) {
        return $view;
      }
    }

    // Check if the query is empty and use the default query in that case.
    // So the default query is used only on the search page.
    list($request, $isEmptyRequest) = $this->cleanRequest($request);
    if ($isEmptyRequest) {
      $searchPageSettings = $searchPage->settings();
      $defaultResults = @$searchPageSettings['default_results'] ?: 'default';
      switch ($defaultResults) {
        case 'none':
          $defaultQuery = '';
          break;
        case 'query':
          $defaultQuery = @$searchPageSettings['default_query'];
          break;
        case 'default':
        default:
          // "*" means the default query managed by the search engine.
          $defaultQuery = '*';
          break;
      }
      if ($defaultQuery === '') {
        if ($isJsonQuery) {
          return new JsonModel([
            'status' => 'error',
            'message' => 'No query.', // @translate
          ]);
        }
        return $view;
      }
      $parsedQuery = [];
      parse_str($defaultQuery, $parsedQuery);
      // Keep the other arguments of the request (mainly pagination, sort,
      // and facets).
      $request = $parsedQuery + $request;
    }

    $result = $this->searchRequestToResponse($request, $searchPage, $site);
    if ($result['status'] === 'fail') {
      // Currently only "no query".
      if ($isJsonQuery) {
        return new JsonModel([
          'status' => 'error',
          'message' => 'No query.', // @translate
        ]);
      }
      return $view;
    }
    if ($result['status'] === 'error') {
      if ($isJsonQuery) {
        return new JsonModel($result);
      }
      $this->messenger()->addError($result['message']);
      return $view;
    }

    if ($isJsonQuery) {
      $response = $result['data']['response'];
      $indexSettings = $searchPage->index()->settings();
      $result = [];
      foreach ($indexSettings['resources'] as $resource) {
        $result[$resource] = $response->getResults($resource);
      }
      return new JsonModel($result);
    }

    return $view
      ->setVariables($result['data'], true)
      ->setVariable('searchPage', $searchPage);
  }

  private function rebuildProperties() {
    $mapping = [
      'Subject' => 'dcterms_subject_ss',
      'Spatial Coverage' => 'dcterms_spatial_t',
      'Temporal Coverage' => 'dcterms_temporal_s'
    ];
    $request = $this->params()->fromQuery();
    if (isset ($request['property'])) {
      $props = $request['property'];
      foreach ($props as $index => $prop) {
        if (isset($prop['property'])) {
          $property = $this->api()->read('properties', $prop['property'])->getContent()->label();
          if (isset($mapping[$property])) {
            $new_prop = [
              'joiner' => 'AND',
              'type' => $mapping[$property],
              'text' => $prop['text'],
            ];
            $request['property'][$index] = $new_prop;
          }
        }
      }
    }
    return $request;
  }

}
