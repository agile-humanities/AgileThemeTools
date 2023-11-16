<?php
namespace AgileThemeTools\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * View helper for rendering a HTML hyperlink.
 */
class pageSlug extends AbstractHelper
{
  /**
   * Provide the slug for the current page.
   *
   * @return string
   */
  public function __invoke()
  {

  }

  /**
   * @param $prefix string Provide an optional slug prefix
   * @return string
   */

  public function get($prefix='') {
    $pathArray = explode('/', strtok($_SERVER['REQUEST_URI'],'?'));

    return count($pathArray) > 0 ? $prefix . array_pop($pathArray) : '';

  }
}
