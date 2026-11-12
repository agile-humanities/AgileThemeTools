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

    /**
     * The url of the site's main search page: Advanced Search's main search config of the site, else the legacy
     * search page of BibLibre's Search module (setting "search_main_page", table "search_page"), else the item browse.
     * The legacy lookup is guarded: once that module is uninstalled, its table is gone and the query would fail on
     * every page that asks for the search path.
     */
    public function siteSearchPath() {
        try {
            $configId = $this->siteSettingValue('advancedsearch_main_config');
            if ($configId) {
                $config = $this->api->read('search_configs', (int) $configId)->getContent();
                // Null or empty when the route of the search page is not registered here.
                $url = (string) $config->siteUrl($this->site->slug());
                if ($url !== '') {
                    return $url;
                }
            }
        } catch (\Throwable $e) {
            // No Advanced Search, or a deleted config: try the legacy page.
        }

        try {
            $searchPageId = $this->siteSettingValue('search_main_page');
            if ($searchPageId) {
                $path = $this->conn->fetchOne('SELECT path FROM search_page WHERE id = ?', [(int) $searchPageId]);
                if ($path) {
                    return $this->site->siteUrl() . '/' . $path;
                }
            }
        } catch (\Throwable $e) {
            // BibLibre's Search module is not installed.
        }

        return $this->site->siteUrl() . '/item'; // Default site browse path
    }

    /** A site setting of this site, json-decoded (site settings are stored as json). */
    protected function siteSettingValue(string $id) {
        $value = $this->conn->fetchOne('SELECT value FROM site_setting WHERE site_id = ? AND id = ?', [$this->site->id(), $id]);
        return $value === false ? null : json_decode((string) $value, true);
    }
}
