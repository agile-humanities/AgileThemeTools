<?php
namespace AgileThemeTools\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use AgileThemeTools\Controller\AgileSearchHandler;

/**
 * View helper for returning a path the site’s search page.
 */
class AgileSearchHelper extends AbstractHelper
{
    protected $agileSearchHandler;

    public function __construct(AgileSearchHandler $agile_search_handler)
    {
        $this->agileSearchHandler = $agile_search_handler;

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
}
