<?php
namespace AgileThemeTools\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * Provides quick access to the site representation and site slug.
 */
class SiteHelper extends AbstractHelper
{
  /**
   * Provide the slug for the current page.
   *
   * @return string
   */

  protected $site;
  protected $siteSlug;

  public function __construct($site,$siteSlug)
  {
    $this->site = $site;
    $this->siteSlug = $siteSlug;
    return $this;
  }

  public function getSite() {
    return $this->site;
  }

  public function getSiteSlug() {
    return $this->siteSlug;
  }
}
