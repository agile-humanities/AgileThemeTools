<?php
namespace AgileThemeTools\DisplayMode;

use AgileThemeTools\Form\Element\PropertyList;
use Laminas\Form\Form;
use Omeka\Form\Element\PropertySelect;

/**
 * The "Cards" settings group, added to Omeka's global settings form and to each site's settings form. The site form
 * offers "use the installation's setting" on every field.
 */
class CardSettingsForm
{
    const GROUP = 'agilethemetools_cards';

    const IMAGE_GUIDANCE = 'The property whose linked media is the card image, when the resource has no thumbnail of its own. '
        . 'foaf:depiction ("an image depicting this thing") ships with Omeka, so nothing has to be imported. '
        . 'To designate one of an item\'s attached files: edit the item, add the property Depiction (FOAF), choose the value type "Omeka resource" and pick the media. '
        . 'If the property is not offered, the item\'s resource template limits the properties: add foaf:depiction to the template, or show all properties in the form. '
        . 'Order of preference for the card image: the resource\'s own Thumbnail field, then this property, then the primary media, then the first attached image. See the module\'s docs/CARDS.md.'; // @translate

    /**
     * @param callable $get  function (string $key): mixed — the saved value for a settings key
     */
    public static function addElements(Form $form, callable $get, bool $siteLevel): void
    {
        $form->setOption('element_groups', ($form->getOption('element_groups') ?: []) + [self::GROUP => 'Cards']); // @translate
        $name = fn (string $key) => CardSettings::settingName($key);
        $inherit = $siteLevel ? 'Use the installation\'s setting' : null; // @translate
        $value = fn (string $key) => $get($name($key)) ?? ($siteLevel ? '' : CardSettings::DEFAULTS[$key]);
        $choice = function (string $key, string $label, string $info, array $options) use ($form, $name, $value, $inherit) {
            $form->add([
                'name' => $name($key),
                'type' => 'Select',
                'options' => ['element_group' => self::GROUP, 'label' => $label, 'info' => $info, 'value_options' => ($inherit !== null ? ['' => $inherit] : []) + $options],
                'attributes' => ['id' => $name($key), 'value' => (string) $value($key)],
            ]);
        };
        $property = function (string $key, string $label, string $info) use ($form, $name, $value, $inherit) {
            $form->add([
                'name' => $name($key),
                'type' => PropertySelect::class,
                'options' => ['element_group' => self::GROUP, 'label' => $label, 'info' => $info, 'term_as_value' => true, 'empty_option' => $inherit ?? ''],
                'attributes' => ['id' => $name($key), 'value' => (string) $value($key), 'class' => 'chosen-select', 'data-placeholder' => 'Select a property'], // @translate
            ]);
        };

        $property('title_term', 'Card title property', 'Empty: the site\'s browse heading property, else each resource\'s own title.'); // @translate
        $property('subtitle_term', 'Card subtitle property', 'Shown under the title; usually the creator. Empty: no subtitle' . ($siteLevel ? ' unless the installation sets one' : '') . '.'); // @translate
        $choice('label_source', 'Card label', 'The small label above the title.', ['type' => 'The resource\'s dcterms:type value (else its class)', 'class' => 'The resource class label']); // @translate
        $property('summary_term', 'Summary property', 'The text summarised on cards. dcterms:description by default; dcterms:abstract where items carry a hand-written summary.'); // @translate
        $form->add([
            'name' => $name('summary_length'),
            'type' => 'Number',
            'options' => ['element_group' => self::GROUP, 'label' => 'Summary length', 'info' => 'Maximum characters. The summary is cut at the last sentence end that fits, failing that at a word. 0: the whole text.' . ($siteLevel ? ' Empty: the installation\'s setting.' : '')], // @translate
            'attributes' => ['id' => $name('summary_length'), 'value' => (string) $value('summary_length'), 'min' => 0, 'step' => 10],
        ]);
        $form->add([
            'name' => $name('metadata_terms'),
            'type' => PropertyList::class,
            'options' => ['element_group' => self::GROUP, 'label' => 'Card metadata', 'info' => 'The short metadata list under the summary, in this order. Drag to reorder.' . ($siteLevel ? ' Empty: the theme\'s Card Terms setting if set, else the installation\'s setting.' : ''), 'empty_label' => $siteLevel ? 'None selected: inherited.' : 'None selected: Date Created and Date.'], // @translate
            'attributes' => ['id' => $name('metadata_terms'), 'value' => (string) $value('metadata_terms')],
        ]);
        $property('image_term', 'Representative image property', self::IMAGE_GUIDANCE);
        $choice('thumbnail_type', 'Thumbnail size', 'The thumbnail type used for the card image.', ['large' => 'Large', 'medium' => 'Medium', 'square' => 'Square']); // @translate
        $choice('show_item_sets', 'Collection line', 'Whether an item card lists the item sets it belongs to.', ['1' => 'Show', '0' => 'Hide']); // @translate
        $choice('show_rights', 'Rights line', 'Whether a card shows the rights statement.', ['1' => 'Show', '0' => 'Hide']); // @translate
        $property('rights_term', 'Rights property', 'The property shown on the rights line.'); // @translate
    }

    public static function addInputFilters($inputFilter): void
    {
        foreach (array_keys(CardSettings::DEFAULTS) as $key) {
            $inputFilter->add(['name' => CardSettings::settingName($key), 'required' => false, 'allow_empty' => true]);
        }
    }
}
