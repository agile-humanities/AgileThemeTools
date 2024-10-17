<?php

namespace AgileThemeTools\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Api\Manager;
use Omeka\Stdlib\HtmlPurifier;
use Omeka\Api\Representation\SiteRepresentation;
use Doctrine\DBAL\Connection;

class AgileSearchHandler extends AbstractActionController {

    protected $api;
    protected $htmlPurifier;
    protected $formElementManager;
    protected $siteSlug;
    protected $site;
    protected $conn;

    function __construct(Manager $api, HtmlPurifier $htmlPurifier, $siteSlug, SiteRepresentation $site, Connection $conn) {
        $this->api = $api;
        $this->htmlPurifier = $htmlPurifier;
        $this->siteSlug = $siteSlug;
        $this->site = $site;
        $this->conn = $conn;
    }

    public function siteSearchPath() {

        $qb = $this->conn->createQueryBuilder();
        $qb->select('s.value')
            ->from('site_setting','s')
            ->where('s.site_id='. $this->site->id())
            ->andwhere('s.id="search_main_page"');
        $stmt = $this->conn->executeQuery($qb, $qb->getParameters());
        $site_page_id = $stmt->fetch(\PDO::FETCH_COLUMN);

        if ($site_page_id) {
            $qb = $this->conn->createQueryBuilder();
            $qb->select('sp.path')
                ->from('search_page', 'sp')
                ->where('sp.id=' . (int)json_decode($site_page_id));

            $stmt = $this->conn->executeQuery($qb, $qb->getParameters());
            $path = $stmt->fetch(\PDO::FETCH_COLUMN);

            if ($path) {
                // Form action parameter to pass to search field (see common/search-form)
                return $this->site->siteUrl() . "/" . $path;
            }
        }

        return $this->site->siteUrl() . "/item"; // Default site browse path

    }
}
