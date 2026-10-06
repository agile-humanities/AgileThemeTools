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
        'clr1' => ['Colour 1', 'Links, highlights and other calls to action.'],
        'clr2' => ['Colour 2', 'Buttons and emphasis (the "impact" colour).'],
        'clr3' => ['Colour 3', 'Links when active.'],
        'clr4' => ['Colour 4', 'Extended palette.'],
        'clr5' => ['Colour 5', 'Extended palette.'],
        'clr6' => ['Colour 6', 'Extended palette.'],
        'clr7' => ['Colour 7', 'Extended palette.'],
        'clr8' => ['Colour 8', 'The dark colour: text, the footer, and every grey is made from it.'],
        'clr9' => ['Colour 9', 'Extended palette.'],
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

        $this->add(['name' => 'csrf', 'type' => Element\Csrf::class, 'options' => ['csrf_options' => ['timeout' => 3600]]]);
        $this->add(['name' => 'submit', 'type' => Element\Submit::class, 'attributes' => ['value' => 'Save', 'class' => 'button']]); // @translate

        $filter = $this->getInputFilter();
        foreach ($this->inputs as $key => $input) {
            $filter->add(['name' => $key, 'required' => false, 'filters' => [['name' => 'StringTrim']]]);
            if ($input['kind'] === 'font') {
                $filter->add(['name' => $key . ThemeFonts::SOURCE_SUFFIX, 'required' => false]);
            }
        }
    }

    public function colourKeys(): array
    {
        return array_keys(array_filter($this->inputs, fn ($i) => $i['kind'] === 'colour'));
    }

    public function fontKeys(): array
    {
        return array_keys(array_filter($this->inputs, fn ($i) => $i['kind'] === 'font'));
    }
}
