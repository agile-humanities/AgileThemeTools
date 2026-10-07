<?php
namespace AgileThemeTools\Form\SiteAdmin;

use AgileThemeTools\Form\Element\ColourValue;
use AgileThemeTools\Form\Element\FontStack;
use AgileThemeTools\Styling\PaletteCss;
use AgileThemeTools\Styling\ThemeFonts;
use Laminas\Form\Element;
use Laminas\Form\Form;

/**
 * The "Colours and fonts" page: the palette colours and font roles, applied as soon as they are saved, with no
 * theme build. The companion of the full styling form (SiteThemeSettingsForm) for people who do not build.
 *
 * The fields are the inputs the theme's compiled stylesheet reads from CSS variables (PaletteCss::inputs()), so
 * the two forms edit the same saved values: a later build uses what was chosen here.
 */
class ColoursAndFontsForm extends Form
{
    /** @var array [key => ['kind' => …, 'variable' => …, 'default' => …]] */
    protected array $inputs = [];

    const COLOUR_LABELS = [
        'clr1' => ['Colour 1: primary brand colour', 'The main brand colour, the default accent and the logo\'s key colour. Tested against every surface below, never changed.'],
        'clr2' => ['Colour 2: secondary brand colour', 'A second brand colour, used as an accent. Tested, never changed.'],
        'clr3' => ['Colour 3: dark surface', 'A step in from the blackpoint, for panels and bands that stand out from dark areas without contrasting with them.'],
        'clr4' => ['Colour 4: light surface', 'A step in from the whitepoint, for panels and bands that stand out from the page without contrasting with it.'],
        'clr5' => ['Colour 5: visual impact', 'Buttons and calls to action. Its text colour is picked automatically.'],
        'clr6' => ['Colour 6: accent', 'Further accent. Tested against the surfaces below.'],
        'clr7' => ['Colour 7: accent', 'Further accent.'],
        'clr8' => ['Colour 8: accent', 'Further accent.'],
        'clr9' => ['Colour 9: accent', 'Further accent.'],
        'black' => ['Blackpoint', 'The darkest colour of the site: body text and the inverse of the page. Chosen to suit the palette; true black only when that is the intent.'],
        'white' => ['Whitepoint', 'The page and text on dark areas.'],
    ];

    const FONT_LABELS = [
        'basefont' => ['Main text font', 'Body text and most of the site; labels, buttons and menus follow it unless the theme separates them.'],
        'headingfont' => ['Heading font', 'Headings and titles.'],
        'altfont' => ['Alternative font', 'Special-use text such as quotations and decks.'],
        'codefont' => ['Code font', 'Code and other fixed-width text.'],
        'labelfont' => ['Label and interface font', 'Labels, buttons and menus.'],
        'listfont' => ['List font', 'Lists.'],
    ];

    public function setInputs(array $inputs): self
    {
        $this->inputs = $inputs;
        return $this;
    }

    public function init(): void
    {
        $this->setAttribute('method', 'POST');
        $this->setAttribute('id', 'colours-and-fonts-form');

        foreach ($this->inputs as $key => $input) {
            if ($input['kind'] === 'colour') {
                [$label, $info] = self::COLOUR_LABELS[$key] ?? [$key, ''];
                $field = new ColourValue($key);
            } else {
                [$label, $info] = self::FONT_LABELS[$key] ?? [$key, ''];
                $field = new FontStack($key);
                $field->setOption('role', $key);
                $source = new Element\Hidden($key . ThemeFonts::SOURCE_SUFFIX);
                $source->setAttribute('id', $key . ThemeFonts::SOURCE_SUFFIX);
                $this->add($source);
            }
            $field->setLabel($label);
            $field->setOption('info', $info);
            $field->setOption('element_group', $input['kind'] === 'colour' ? 'colours' : 'fonts');
            $field->setAttribute('id', $key);
            $field->setAttribute('data-variable', $input['variable']);
            $field->setValue($input['default']);
            $this->add($field);
        }

        foreach (\AgileThemeTools\Styling\ColourRoles::OVERRIDABLE as $key => [$label, $info]) {
            $field = new ColourValue($key);
            $field->setLabel($label);
            $field->setOption('info', $info);
            $field->setOption('element_group', 'roles');
            $field->setAttribute('id', $key);
            $field->setAttribute('placeholder', 'automatic');
            $field->setValue($this->overrides[$key] ?? '');
            $this->add($field);
        }

        $this->add(['name' => 'csrf', 'type' => Element\Csrf::class, 'options' => ['csrf_options' => ['timeout' => 3600]]]);
        $this->add(['name' => 'submit', 'type' => Element\Submit::class, 'attributes' => ['value' => 'Save', 'class' => 'button']]); // @translate

        $filter = $this->getInputFilter();
        foreach ($this->inputs as $key => $input) {
            $filter->add(['name' => $key, 'required' => false, 'filters' => [['name' => 'StringTrim']]]);
            if ($input['kind'] === 'font') {
                $filter->add(['name' => $key . ThemeFonts::SOURCE_SUFFIX, 'required' => false]);
            }
        }
        foreach (array_keys(\AgileThemeTools\Styling\ColourRoles::OVERRIDABLE) as $key) {
            $filter->add(['name' => $key, 'required' => false, 'filters' => [['name' => 'StringTrim']]]);
        }
    }

    /** @var array [key => saved override or ''] */
    protected array $overrides = [];

    public function setOverrides(array $overrides): self
    {
        $this->overrides = $overrides;
        return $this;
    }

    public function colourKeys(): array
    {
        return array_keys(array_filter($this->inputs, fn ($i) => $i['kind'] === 'colour'));
    }

    /** The derived roles a themer may replace with a brand-approved tint (empty = automatic). */
    public function overrideKeys(): array
    {
        return array_keys(\AgileThemeTools\Styling\ColourRoles::OVERRIDABLE);
    }

    public function fontKeys(): array
    {
        return array_keys(array_filter($this->inputs, fn ($i) => $i['kind'] === 'font'));
    }
}
