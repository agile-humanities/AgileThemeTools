<?php

namespace AgileThemeTools\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Api\Manager;
use Omeka\Stdlib\HtmlPurifier;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Laminas\Form\FormElementManager as FormElementManager;
use Laminas\Filter\RealPath;

class SectionManager extends AbstractActionController {

  protected $api;
  protected $htmlPurifier;
  protected $formElementManager;
  protected $siteSlug;
  protected $site;
  protected $pages;
  protected $template_path;
  protected $pageIndex;

  function __construct(Manager $api, HtmlPurifier $htmlPurifier,FormElementManager $formElementManager, $siteSlug, SiteRepresentation|null $site) {
    $this->api = $api;
    $this->htmlPurifier = $htmlPurifier;
    $this->formElementManager = $formElementManager;
    $this->siteSlug = $siteSlug;
    $this->site = $site;
    $this->pages = [];
    $this->pageIndex = [];
    $this->indexPages();

    $filter = new RealPath();
    $this->template_path = $filter(__DIR__ .  '/../../view');
  }

  function indexAction()
  {
    return $this->getSections();

  }

  /*
   * @method indexPages()
   *
   * Populates an array of pages based on the public navigation structure.
   * Indexes subpages by parent slug for easy retrieval of child pages.
   */

  private function indexPages() {
    // See toArray() https://docs.zendframework.com/zend-navigation/containers/
    // Also, Omeka keeps its site and page slugs as $page['params']['site-slug'] and $page['params']['page-slug']
    // Items without a page-slug are likely direct routes and ignored as sections.

    $this->pages = $this->site ? $this->site->publicNav()->toArray() : [];
    $this->getSections(); // Populates the page index. Requires pages be built.
  }

  /*
   * @method getSections()
   * This function does double-duty (for convenience – probably should refactor).
   * - It returns a full list of pages and nested subpages that can be designated a “section” of the site. This
   *   will be used to populate the section listing menu.
   * - It populates the pageIndex property, which allows functions to look up all the immediate child pages by slug.
   *
   */

  public function getSections() {

    $sections = [];

    foreach ($this->pages as $page) {
      // Top-level user-added pages are considered a “section”.
      if (array_key_exists('params',$page) && array_key_exists('page-slug',$page['params'])) {
        $sections[$page['params']['page-slug']] = $page['label'];
        $this->pageIndex[$page['params']['page-slug']] = $page['pages'];
        $this->extractSubPages($page,$sections);
      }
    }

    return $sections;

  }

  /*
   * @method extractSubPages
   * A recursive function that traverses child pages in the navigation tree.
   */

  private function extractSubPages($page,&$sections,$depth=1) {
    if (array_key_exists('pages',$page) && count($page['pages']) > 0) {
      foreach($page['pages'] as $subpage) {
        if (array_key_exists('params',$subpage) && array_key_exists('page-slug',$subpage['params'])) {
          $sections[$subpage['params']['page-slug']] = str_pad('',$depth, "  ",STR_PAD_LEFT) . str_pad($subpage['label'],strlen($subpage['label']) + $depth,"-",STR_PAD_LEFT);
          $this->extractSubPages($subpage,$sections,$depth+1);
          $this->pageIndex[$subpage['params']['page-slug']] = $subpage['pages'];
        }
      }
    }
  }

  /**
   * The pages of a section, for the listing templates: the section's child pages in navigation order, the section's
   * own page first when asked for, the first $count of them when a count is given.
   *
   * Each entry is what the templates and the card display mode read: 'o:id', 'o:slug', 'o:title', 'o:url',
   * 'o:active' and 'o:pageRepresentation' (the SitePageRepresentation, for $this->card($page)). Nothing of the
   * page's blocks is loaded here: the card mode reads the one block it needs from the representation, and the
   * list templates need none. The site's pages are fetched once per request, in one query, however many listing
   * blocks a page carries.
   *
   * @param string $sectionSlug The page slug of the parent page.
   * @param int|string|null $count The number of pages to return, if any (the first N, in navigation order).
   * @param bool $includeCurrentPage Include the parent page ahead of its children.
   * @return array[] Keyed by page slug.
   */
  public function getPagesBySection($sectionSlug, $count = null, $includeCurrentPage = false) {
    if (!$this->site || !key_exists($sectionSlug, $this->pageIndex)) {
      return [];
    }

    $slugs = [];
    if ($includeCurrentPage) {
      $slugs[] = $sectionSlug;
    }
    foreach ($this->pageIndex[$sectionSlug] as $childPage) {
      if (array_key_exists('params', $childPage) && array_key_exists('page-slug', $childPage['params'])) {
        $slugs[] = $childPage['params']['page-slug'];
      }
    }
    $slugs = array_values(array_unique($slugs));
    $count = (int) $count;
    if ($count > 0) {
      $slugs = array_slice($slugs, 0, $count);
    }

    $pagesBySlug = $this->getSitePages();
    $activeSlug = $this->getActivePageSlug();
    $sectionPages = [];
    foreach ($slugs as $slug) {
      if (!isset($pagesBySlug[$slug])) {
        continue; // In the navigation but not readable here (a private page, or one removed since the menu was built).
      }
      $page = $pagesBySlug[$slug];
      $sectionPages[$slug] = [
        'o:id' => $page->id(),
        'o:slug' => $slug,
        'o:title' => $page->title(),
        'o:url' => $page->siteUrl(),
        'o:active' => $slug === $activeSlug,
        'o:pageRepresentation' => $page,
      ];
    }

    return $sectionPages;
  }

  /** @var SitePageRepresentation[]|null The site's pages keyed by slug, read once per request. */
  protected $sitePages = null;

  /** @return SitePageRepresentation[] Keyed by slug. */
  protected function getSitePages(): array {
    if ($this->sitePages === null) {
      $this->sitePages = [];
      if ($this->site) {
        foreach ($this->api->search('site_pages', ['site_id' => $this->site->id()])->getContent() as $page) {
          $this->sitePages[$page->slug()] = $page;
        }
      }
    }
    return $this->sitePages;
  }

  public function getActivePageSlug() {
    $nav = $this->site->publicNav();
    $container = $nav->getContainer();
    $activePage = $nav->findActive($container);
    return ($activePage && array_key_exists('page-slug',$activePage['page']->getParams())) ? $activePage['page']->getParams()['page-slug'] : '';
  }
}
