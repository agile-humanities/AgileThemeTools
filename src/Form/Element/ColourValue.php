<?php
namespace AgileThemeTools\Form\Element;

use Laminas\Form\Element\Text;

/**
 * A colour setting of the theme system: a hex or named colour, or a SASS expression the theme system understands
 * ($clr2, $white, neutral(40), set-color(2, 50), mix($white, $clr2, 10%), rgba(…)). Rendered with a live swatch,
 * a colour picker and, when `contrast_with` names another colour field, a WCAG contrast readout against it
 * (see FormColourValue and asset/js/colour-value.js).
 *
 * Options:
 * - contrast_with (string): the name of the field this colour is shown on (a foreground) or under (a background).
 * - contrast_role ('fg'|'bg', default 'fg'): whether this field is the text/foreground or the background of the pair.
 */
class ColourValue extends Text
{
    protected $attributes = [
        'type' => 'text',
        'class' => 'agile-colour-value',
        'spellcheck' => 'false',
        'autocomplete' => 'off',
    ];

    public function getContrastWith(): ?string
    {
        $with = $this->getOption('contrast_with');
        return is_string($with) && $with !== '' ? $with : null;
    }

    public function getContrastRole(): string
    {
        return $this->getOption('contrast_role') === 'bg' ? 'bg' : 'fg';
    }
}
