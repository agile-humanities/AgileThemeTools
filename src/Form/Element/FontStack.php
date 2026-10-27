<?php
namespace AgileThemeTools\Form\Element;

use Laminas\Form\Element\Text;

/**
 * A font role of the theme (main text, headings, …). The value is the CSS font-family declaration the SASS build
 * expects (`"Open Sans", sans-serif`, or `$headingfont` to reuse another role). It is edited as a source (Google
 * Fonts, bundled, system, same as another role), a family with suggestions, a fallback and a live preview that
 * loads the font (see FormFontStack, asset/js/font-stack.js). The chosen source is posted in a companion hidden
 * field named <role>_source, which the form must also carry.
 *
 * Options: role (basefont|headingfont|altfont|compactfont).
 */
class FontStack extends Text
{
    protected $attributes = [
        'type' => 'text',
        'class' => 'agile-font-stack-value',
    ];

    public function getRole(): string
    {
        return (string) ($this->getOption('role') ?: $this->getName());
    }
}
