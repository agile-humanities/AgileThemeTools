<?php
namespace AgileThemeTools\Form\View\Helper;

use AgileThemeTools\Styling\ThemeFonts;
use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\AbstractHelper;
use Omeka\Site\Theme\Manager as ThemeManager;

/**
 * Renders a FontStack: the real (hidden) value, the source and family controls, the fallback and the preview.
 */
class FormFontStack extends AbstractHelper
{
    protected ThemeFonts $fonts;
    protected ThemeManager $themes;
    protected ?\Omeka\Api\Representation\SiteRepresentation $site;

    /** @var bool The bundled @font-face rules are needed once per page, not once per control. */
    protected bool $bundledFacesEmitted = false;

    public function __construct(ThemeFonts $fonts, ThemeManager $themes, ?\Omeka\Api\Representation\SiteRepresentation $site = null)
    {
        $this->fonts = $fonts;
        $this->themes = $themes;
        $this->site = $site;
    }

    public function __invoke(ElementInterface $element)
    {
        return $this->render($element);
    }

    public function render(ElementInterface $element): string
    {
        $view = $this->getView();
        $theme = $this->site ? ($this->themes->getTheme($this->site->theme()) ?: $this->themes->getCurrentTheme()) : $this->themes->getCurrentTheme();
        if ($theme && !$this->bundledFacesEmitted) {
            $this->bundledFacesEmitted = true;
            $faces = $this->fonts->getBundledFontFaceCss($theme, $this->site, (string) $view->basePath());
            if ($faces !== '') {
                $view->headStyle()->appendStyle($faces);
            }
        }
        $role = method_exists($element, 'getRole') ? $element->getRole() : $element->getName();
        $parsed = $this->fonts->parseDeclaration((string) $element->getValue());
        $suggestions = $this->fonts->getSuggestions($theme);

        return $view->partial('common/font-stack-form', [
            'element' => $element,
            'role' => $role,
            'roles' => ThemeFonts::ROLES,
            'parsed' => $parsed,
            'inferredSource' => $this->fonts->inferSource($parsed, $suggestions[ThemeFonts::SOURCE_BUNDLED]),
            'suggestions' => $suggestions,
            'generics' => ThemeFonts::GENERICS,
            'googleAxes' => ThemeFonts::GOOGLE_AXES,
        ]);
    }
}
