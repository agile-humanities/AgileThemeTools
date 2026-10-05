<?php
namespace AgileThemeTools\Form\View\Helper;

use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\FormText;

/**
 * Renders a ColourValue: the text input, a swatch, a native colour picker, the resolved colour and, for a
 * contrast pair, the contrast readout. All of it is driven by asset/js/colour-value.js from the form's values.
 */
class FormColourValue extends FormText
{
    public function render(ElementInterface $element): string
    {
        $view = $this->getView();
        $view->headLink()->appendStylesheet($view->assetUrl('css/colour-value.css', 'AgileThemeTools'));
        $view->headScript()->appendFile($view->assetUrl('js/colour-value.js', 'AgileThemeTools'));
        $escape = $view->plugin('escapeHtmlAttr');
        $translate = $view->plugin('translate');

        $with = method_exists($element, 'getContrastWith') ? $element->getContrastWith() : null;
        $role = method_exists($element, 'getContrastRole') ? $element->getContrastRole() : 'fg';

        return sprintf(
            '<div class="agile-colour" data-name="%s"%s>%s'
            . '<span class="agile-colour-swatch" title="%s"></span>'
            . '<input type="color" class="agile-colour-picker" value="#000000" aria-label="%s" title="%s">'
            . '<span class="agile-colour-resolved"></span>'
            . '<span class="agile-colour-contrast"></span>'
            . '</div>',
            $escape($element->getName()),
            $with ? sprintf(' data-contrast-with="%s" data-contrast-role="%s"', $escape($with), $escape($role)) : '',
            parent::render($element),
            $escape($translate('The colour this value resolves to')),
            $escape($translate('Pick a colour')),
            $escape($translate('Pick a colour (replaces the value with a hex colour)'))
        );
    }
}
