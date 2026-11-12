<?php

namespace AgileThemeTools\Controller;

use Omeka\Controller\Site\ItemController;

class AgileItemController extends ItemController {
  public function browseAction() {
    $site = $this->currentSite();
    $settings = $this->siteSettings();
    $search_page = $settings->get('search_main_page');
    // A redirect to the legacy search page of BibLibre's Search module, only while that module provides the route:
    // the setting outlives the module, and toRoute() on a missing route is a fatal error on every item browse.
    $router = $this->getEvent()->getRouter();
    if ($search_page && method_exists($router, 'hasRoute') && $router->hasRoute('search-page-' . $search_page)) {
      $route = 'search-page-' . $search_page;
      $siteSlug = $site->slug(); // Returns 'main'
      return $this->redirect()->toRoute($route, ['site-slug' => $siteSlug], ['query' => $this->request->getQuery()->toArray()]);
    }
    else {
      return parent::browseAction();
    }
  }

}
