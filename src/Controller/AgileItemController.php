<?php

namespace AgileThemeTools\Controller;

use Omeka\Controller\Site\ItemController;

class AgileItemController extends ItemController {
  public function browseAction() {
    $site = $this->currentSite();
    $settings = $this->siteSettings();
    $search_page = $settings->get('search_main_page');
    if ($search_page) {
      $route = 'search-page-' . $settings->get('search_main_page');
      $siteSlug = $site->slug(); // Returns 'main'
      return $this->redirect()->toRoute($route, ['site-slug' => $siteSlug], ['query' => $this->request->getQuery()->toArray()]);
    }
    else {
      return parent::browseAction();
    }
  }

}
