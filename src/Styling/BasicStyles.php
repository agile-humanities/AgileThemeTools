<?php
namespace AgileThemeTools\Styling;

use AgileThemeTools\Theme\ThemeProfile;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Settings\Settings;
use Omeka\Site\Theme\Theme;

/**
 * Basic styles for the module's own templates (generic requirements B8).
 *
 * A few small, plain, hand-maintained stylesheets — roughly one per template group — that make the module's
 * generic templates presentable under any theme, in the usual Omeka manner: minimal rules, no design. They load on
 * public pages only when the global setting is on (default off), and never for an Agile theme (recognised by its
 * declared identity): the Advanced theme styles these templates from its SASS and the Simplified theme carries that
 * stylesheet. Other themes — Omeka's default theme, anything else — get the palette through the CSS variables
 * --colour--N when the theme defines them, neutral greys otherwise.
 */
class BasicStyles
{
    const SETTING = 'agilethemetools_basic_css';

    /** The stylesheets, in loading order, relative to the module's asset/ folder. */
    const STYLESHEETS = [
        'css/basic/agile-text-blocks.css',   // byline, deck, excerpt, quotation, callout, titles, contributors, glossary; the shared variables
        'css/basic/agile-listings.css',      // section listings, link cards, item listing, representative image, poster, section splash
        'css/basic/agile-slide-viewer.css',  // slide viewer and homepage introduction blocks
        'css/basic/agile-item.css',          // cards, item browse, item header and media blocks, media renderers
    ];

    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get(self::SETTING, false);
    }

    /** Whether the stylesheets would apply to pages rendered with this theme: on, and the theme does not style the templates itself. */
    public function appliesTo(?Theme $theme): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        return !$theme || !ThemeProfile::of($theme)->stylesModuleTemplates();
    }

    /** The setting is on but this theme styles the module's templates itself: the admin should switch it off. */
    public function isRedundantFor(?Theme $theme): bool
    {
        return $this->isEnabled() && $theme && ThemeProfile::of($theme)->stylesModuleTemplates();
    }

    /** Appends the stylesheets to the page head (after the theme's own, which were added earlier in the layout). */
    public function inject(PhpRenderer $view, ?Theme $theme): void
    {
        if (!$this->appliesTo($theme)) {
            return;
        }
        foreach (self::STYLESHEETS as $file) {
            $view->headLink()->appendStylesheet($view->assetUrl($file, 'AgileThemeTools'));
        }
    }
}
