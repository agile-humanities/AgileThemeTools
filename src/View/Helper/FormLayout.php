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
 *
 * COMPOSITION (the stop14 form composition vocabulary, theme 40_ui/10_components/_form_composition.sass): a form
 * rendered with ['compose' => true] (the default for a Form) is laid out in rows — `.form-row` of 1–6 `.field`
 * cells, `.form-actions` for the buttons, `.form-messages` (the validation summary) above — by, in order:
 *   1. an explicit spec: ['rows' => [['q'], ['item_set_id', 'resource_class_id'], 'actions', …]] (names; a row
 *      may be ['names' => [...], 'fit' => ['name'], 'stack' => true]); elements not named are appended by the rules;
 *   2. per-element hints in the element's options: 'form_layout' => ['cell' => 'fit'|'full'|'w4'…, 'row' => 'new'];
 *   3. the packing rules: a full-width kind (search, textarea, multi-text, range-double, collection, fieldset,
 *      unknown, a text with a size of 60 or more, a group with more than 4 options) takes a row of its own;
 *      consecutive compact kinds (text, select, number, checkbox, range, a small group) fill rows of up to
 *      'max_cells' (3).
 * Hidden elements render after the rows. The writer sets --form-cells on each row and --form-row-columns on rows
 * with fit cells, so the stylesheet never has to count.
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

    /** Renders every element of a form or fieldset, in order; a Form is composed into rows unless 'compose' is false. */
    public function render($container, array $options = []): string
    {
        $compose = $options['compose'] ?? ($container instanceof FormInterface);
        if ($compose) {
            return $this->renderComposed($container, $options);
        }
        $out = '';
        foreach ($container->getIterator() as $element) {
            $out .= $this->element($element, $options);
        }
        return $out;
    }

    // ---- composition ---------------------------------------------------------------------------------------------

    const COMPACT_TYPES = ['text', 'select', 'number', 'checkbox', 'range', 'button'];
    const FULL_TYPES = ['search', 'textarea', 'multi-text', 'range-double', 'collection', 'fieldset', 'unknown'];
    const GROUP_INLINE_MAX = 4;   // a radio / multi-checkbox with more options takes a full row
    const LONG_TEXT_MIN = 60;     // a text input with a size above this takes a full row; other text fills a cell

    /** The form in rows: the explicit spec, then the hints and the packing rules for the rest. */
    public function renderComposed($container, array $options = []): string
    {
        $maxCells = max(1, (int) ($options['max_cells'] ?? 3));
        $elements = [];     // name => element, in order
        $hidden = [];
        $actions = [];
        foreach ($container->getIterator() as $element) {
            $type = $this->typeOf($element);
            $name = (string) $element->getName();
            if ($type === 'hidden') {
                $hidden[] = $element;
            } elseif ($type === 'button') {
                $actions[] = $element;
            } else {
                $elements[$name] = $element;
            }
        }

        $rows = [];        // each: ['cells' => [[element, cellOptions]...], 'stack' => bool]
        $placed = [];
        foreach ($options['rows'] ?? [] as $spec) {
            if ($spec === 'actions') {
                $rows[] = 'actions';
                continue;
            }
            $names = is_array($spec) && isset($spec['names']) ? $spec['names'] : (array) $spec;
            $fit = is_array($spec) ? (array) ($spec['fit'] ?? []) : [];
            $cells = [];
            foreach ($names as $n) {
                if (!isset($elements[$n])) {
                    continue;
                }
                $cells[] = [$elements[$n], ['fit' => in_array($n, $fit, true)] + $this->hint($elements[$n])];
                $placed[$n] = true;
            }
            if ($cells) {
                $rows[] = ['cells' => $cells, 'stack' => !empty($spec['stack'])];
            }
        }

        // The rest, packed.
        $current = [];
        $flush = function () use (&$current, &$rows) {
            if ($current) {
                $rows[] = ['cells' => $current, 'stack' => false];
                $current = [];
            }
        };
        foreach ($elements as $name => $element) {
            if (isset($placed[$name])) {
                continue;
            }
            $hint = $this->hint($element);
            $full = ($hint['cell'] ?? null) === 'full' || (($hint['cell'] ?? null) !== 'compact' && $this->isFullWidth($element));
            if (($hint['row'] ?? null) === 'new') {
                $flush();
            }
            if ($full) {
                $flush();
                $rows[] = ['cells' => [[$element, $hint]], 'stack' => false];
                continue;
            }
            $current[] = [$element, $hint];
            if (count($current) >= $maxCells) {
                $flush();
            }
        }
        $flush();
        if ($actions && !in_array('actions', $rows, true)) {
            $rows[] = 'actions';
        }

        $view = $this->getView();
        $escape = $view->plugin('escapeHtml');
        $out = $this->renderMessages($container, $options);
        foreach ($rows as $row) {
            if ($row === 'actions') {
                if ($actions && empty($options['actions_outside'])) {
                    $out .= '<div class="form-actions">';
                    foreach ($actions as $button) {
                        $out .= $this->element($button, $options);
                    }
                    $out .= '</div>';
                }
                continue;
            }
            $count = count($row['cells']);
            $classes = ['form-row', 'form-row--' . $count];
            if ($row['stack']) {
                $classes[] = 'form-row--stack';
            }
            $style = '--form-cells: ' . $count;
            $tracks = [];
            $hasFit = false;
            foreach ($row['cells'] as [$element, $cellOptions]) {
                $isFit = !empty($cellOptions['fit']) || ($cellOptions['cell'] ?? null) === 'fit';
                $tracks[] = $isFit ? 'auto' : 'minmax(0, 1fr)';
                $hasFit = $hasFit || $isFit;
            }
            if ($hasFit && $count > 1) {
                $style .= '; --form-row-columns: ' . implode(' ', $tracks);
            }
            $out .= '<div class="' . $escape(implode(' ', $classes)) . '" style="' . $escape($style) . '">';
            foreach ($row['cells'] as [$element, $cellOptions]) {
                $cellClasses = [];
                if (!empty($cellOptions['fit']) || ($cellOptions['cell'] ?? null) === 'fit') {
                    $cellClasses[] = 'form-cell--fit';
                } elseif (preg_match('/^w(\d+)$/', (string) ($cellOptions['cell'] ?? ''), $m)) {
                    $cellClasses[] = 'form-cell--w' . $m[1];
                }
                if (!empty($cellOptions['options_stacked'])) {
                    $cellClasses[] = 'form-cell--options-stacked';
                }
                $out .= $this->element($element, $options + ['cell_classes' => $cellClasses]);
            }
            $out .= '</div>';
        }
        foreach ($hidden as $element) {
            $out .= $this->element($element, $options);
        }
        return $out;
    }

    /** The element's own layout hint: options 'form_layout' => ['cell' => 'fit'|'full'|'compact'|'w<N>', 'row' => 'new', 'options_stacked' => true]. */
    protected function hint(ElementInterface $element): array
    {
        $hint = $element->getOption('form_layout');
        return is_array($hint) ? $hint : [];
    }

    /** Does this element take a row of its own? */
    protected function isFullWidth(ElementInterface $element): bool
    {
        $type = $this->typeOf($element);
        if (in_array($type, self::FULL_TYPES, true)) {
            return true;
        }
        if ($type === 'text') {
            return (int) $element->getAttribute('size') >= self::LONG_TEXT_MIN;
        }
        if (in_array($type, ['multi-checkbox', 'radio'], true)) {
            $count = method_exists($element, 'getValueOptions') ? count($element->getValueOptions()) : 0;
            return $count > self::GROUP_INLINE_MAX;
        }
        return !in_array($type, self::COMPACT_TYPES, true);
    }

    /** The validation summary: one link per element with messages, above the rows. Empty when the form is clean. */
    protected function renderMessages($container, array $options): string
    {
        if (!method_exists($container, 'getMessages')) {
            return '';
        }
        $messages = $container->getMessages();
        if (!$messages) {
            return '';
        }
        $view = $this->getView();
        $escape = $view->plugin('escapeHtml');
        $translate = $view->plugin('translate');
        $items = '';
        foreach ($messages as $name => $elementMessages) {
            $element = $container->has($name) ? $container->get($name) : null;
            $id = $element ? ($element->getAttribute('id') ?: preg_replace('/[^a-zA-Z0-9_-]+/', '-', $name)) : null;
            $label = $element && $element->getLabel() ? $translate($element->getLabel()) : $name;
            foreach ((array) $elementMessages as $message) {
                $text = is_array($message) ? implode(' ', $message) : (string) $message;
                $items .= '<li>' . ($id ? '<a href="#' . $escape($id) . '_field">' : '') . $escape($label) . ($id ? '</a>' : '') . ': ' . $escape($text) . '</li>';
            }
        }
        return '<div class="form-messages" role="alert"><p class="form-messages-title">' . $escape($translate('Please check the form')) . '</p><ul>' . $items . '</ul></div>';
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
        foreach ($options['cell_classes'] ?? [] as $cellClass) {
            $classes[] = $cellClass;
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
