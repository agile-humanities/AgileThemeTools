<?php
namespace AgileThemeTools\View\Helper;

use AgileThemeTools\Styling\LogoAudit;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Theme\Manager as ThemeManager;
use Omeka\Site\Theme\Theme;

/**
 * Renders the findings of the logo audit for a site: nothing when every logo is fine. Used on the site's Theme
 * page and on the theme settings form, where the logos are chosen.
 */
class ThemeLogoAudit extends AbstractHelper
{
    protected LogoAudit $audit;
    protected ThemeManager $themes;

    public function __construct(LogoAudit $audit, ThemeManager $themes)
    {
        $this->audit = $audit;
        $this->themes = $themes;
    }

    public function __invoke(SiteRepresentation $site, ?Theme $theme = null): string
    {
        $theme = $theme ?? $this->themes->getTheme($site->theme());
        if (!$theme || ThemeManager::STATE_ACTIVE !== $theme->getState()) {
            return '';
        }
        $logos = array_values(array_filter($this->audit->audit($theme, $site), fn ($logo) => $logo['findings']));
        return $logos ? $this->getView()->partial('common/theme-logo-audit', ['logos' => $logos, 'site' => $site]) : '';
    }
}
