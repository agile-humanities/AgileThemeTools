<?php
namespace AgileThemeTools\View\Helper;

use AgileThemeTools\Styling\ThemeFonts;
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\Placeholder\Container\AbstractContainer;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * Loads the site's Google fonts into the page. A theme layout calls `$this->themeFonts()->inject();` and the
 * preconnect and stylesheet links are placed before everything else in headLink, so fonts start loading early.
 * Fonts bundled with the theme are declared by the compiled stylesheet and need nothing here.
 */
class ThemeFontsHelper extends AbstractHelper
{
    protected ThemeFonts $fonts;
    protected ?SiteRepresentation $site;

    public function __construct(ThemeFonts $fonts, ?SiteRepresentation $site)
    {
        $this->fonts = $fonts;
        $this->site = $site;
    }

    public function __invoke(): self
    {
        return $this;
    }

    public function inject(): void
    {
        if (!$this->site) {
            return;
        }
        $headLink = $this->getView()->headLink();
        // Prepend in reverse so the tags end up in their natural order at the top.
        foreach (array_reverse($this->fonts->getHeadTags($this->site)) as $attributes) {
            $headLink($attributes, AbstractContainer::PREPEND);
        }
    }

    public function getService(): ThemeFonts
    {
        return $this->fonts;
    }
}
