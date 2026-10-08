<?php
namespace AgileThemeTools\View\Helper;

use Laminas\Form\Element;
use Laminas\Form\ElementInterface;
use Laminas\Form\FieldsetInterface;
use Laminas\Form\FormInterface;
use Laminas\View\Helper\AbstractHelper;

/**
 * $this->formLayout($form)  lays out a form, or a fieldset, element by element, by element type (generic
 * requirements G1): every element gets one wrapper and label pattern for its kind, from the partials in
 * common/form-layout/, which a theme may override. Grouping and columns are decided by rules, not by position —
 * for now the rules are the basic scaffolding: a field per element, fieldsets recursed, collections (repeatable
 * rows) and the elements of other modules rendered by their own helpers inside the wrapper, so their scripts keep
 * working.
 *
 * Element classes the map does not know are rendered by Laminas' formRow inside a generic wrapper and recorded,
 * so the dependency audit can report them (unknownTypes()).
 *
 * Wrapper classes: .form-field .field-type-<type> .field-name-<name> [.field-required] [.field-has-error]
 */
class FormLayout extends AbstractHelper
{
    /** Element class (or parent class) => layout type. First match in order wins. */
    const TYPES = [
        Element\Hidden::class => 'hidden',
        Element\Csrf::class => 'hidden',
        Element\Submit::class => 'button',
        Element\Button::class => 'button',
        Element\MultiCheckbox::class => 'multi-checkbox',   // before Checkbox: MultiCheckbox extends Checkbox
        Element\Checkbox::class => 'checkbox',
        Element\Radio::class => 'radio',                     // Radio extends MultiCheckbox: listed after it on purpose? No — see typeOf()
        Element\Select::class => 'select',
        Element\Textarea::class => 'textarea',
        Element\Range::class => 'range',
        Element\Number::class => 'number',
        Element\Search::class => 'search',
        Element\Text::class => 'text',
        Element\Collection::class => 'collection',
        'AdvancedSearch\Form\Element\RangeDouble' => 'range-double',
        'AdvancedSearch\Form\Element\MultiText' => 'multi-text',
    ];

    /** Exact classes checked before the inheritance walk, where a subclass needs a different type. */
    const EXACT = [
        Element\Radio::class => 'radio',
        'Common\Form\Element\OptionalRadio' => 'radio',
        'Common\Form\Element\OptionalMultiCheckbox' => 'multi-checkbox',
        'Common\Form\Element\OptionalSelect' => 'select',
        'AdvancedSearch\Form\Element\TextExact' => 'text',
        Element::class => 'text',                             // a plain Element carries its own type attribute (text, date …)
    ];

    /** @var array<string, string> element class => name, for the audit */
    protected static array $unknown = [];

    public function __invoke($formOrFieldset = null, array $options = [])
    {
        if ($formOrFieldset === null) {
            return $this;
        }
        return $this->render($formOrFieldset, $options);
    }

    /** Renders every element of a form or fieldset, in order. */
    public function render($container, array $options = []): string
    {
        $out = '';
        foreach ($container->getIterator() as $element) {
            $out .= $this->element($element, $options);
        }
        return $out;
    }

    /** One element, through the partial for its type. */
    public function element(ElementInterface $element, array $options = []): string
    {
        $view = $this->getView();
        $type = $this->typeOf($element);
        $name = (string) $element->getName();
        $classes = ['form-field', 'field-type-' . $type, 'field-name-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $name)];
        if ($element->getAttribute('required')) {
            $classes[] = 'field-required';
        }
        if ($element->getMessages()) {
            $classes[] = 'field-has-error';
        }
        $vars = [
            'element' => $element,
            'type' => $type,
            'name' => $name,
            'label' => $element->getLabel(),
            'info' => $element->getOption('info'),
            'classes' => implode(' ', $classes),
            'options' => $options,
            'layout' => $this,
        ];
        if ($type === 'fieldset' && $element instanceof FieldsetInterface && !$element instanceof FormInterface) {
            return $view->partial('common/form-layout/fieldset', $vars);
        }
        if (in_array($type, ['hidden', 'button', 'collection', 'range-double', 'multi-text', 'unknown'], true)) {
            return $view->partial('common/form-layout/' . $type, $vars);
        }
        return $view->partial('common/form-layout/field', $vars);
    }

    public function typeOf(ElementInterface $element): string
    {
        $class = get_class($element);
        if (isset(self::EXACT[$class])) {
            return self::EXACT[$class];
        }
        if ($element instanceof Element\Collection) {
            return 'collection';
        }
        if ($element instanceof FieldsetInterface) {
            return 'fieldset';
        }
        if ($element instanceof Element\Radio) {
            return 'radio';
        }
        foreach (self::TYPES as $known => $type) {
            if ($element instanceof $known) {
                return $type;
            }
        }
        self::$unknown[$class] = (string) $element->getName();
        return 'unknown';
    }

    /** Element classes met during this request that have no layout type: for the dependency audit. */
    public static function unknownTypes(): array
    {
        return self::$unknown;
    }
}
