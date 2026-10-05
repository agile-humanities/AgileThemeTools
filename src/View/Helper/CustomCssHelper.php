<?php
namespace AgileThemeTools\View\Helper;

use AgileThemeTools\Styling\CustomCss;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * Loads the administrators' custom CSS layers into the page. A theme layout calls
 *
 *     $this->customCss()->inject();
 *
 * after adding its own stylesheets, so the global layer, then the site layer, come last in the cascade.
 */
class CustomCssHelper extends AbstractHelper
{
    protected CustomCss $customCss;
    protected ?SiteRepresentation $site;

    public function __construct(CustomCss $customCss, ?SiteRepresentation $site)
    {
        $this->customCss = $customCss;
        $this->site = $site;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /** Appends the custom stylesheets to headLink (or, when a file could not be written, to headStyle). */
    public function inject(): void
    {
        $view = $this->getView();
        foreach ($this->customCss->getStylesheets($this->site, (string) $view->basePath()) as $sheet) {
            if (isset($sheet['url'])) {
                $view->headLink()->appendStylesheet($sheet['url']);
            } else {
                $view->headStyle()->appendStyle($sheet['inline']);
            }
        }
    }

    public function getService(): CustomCss
    {
        return $this->customCss;
    }
}
