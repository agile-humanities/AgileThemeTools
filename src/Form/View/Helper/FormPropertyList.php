<?php
namespace AgileThemeTools\Form\View\Helper;

use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;

/**
 * Renders a PropertyList element: a hidden input holding the terms, a searchable picker and the lozenges.
 */
class FormPropertyList extends AbstractHelper
{
    /**
     * @var ApiManager
     */
    protected $api;

    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    public function __invoke(ElementInterface $element)
    {
        return $this->render($element);
    }

    public function render(ElementInterface $element): string
    {
        $view = $this->getView();
        $labels = $this->getPropertyLabels();

        $items = [];
        foreach ($view->plugin('propertyTerms')->parse($element->getValue()) as $term) {
            $items[] = ['term' => $term] + $this->describe($term, $labels[$term] ?? null);
        }

        return $view->partial('common/property-list-form', [
            'element' => $element,
            'items' => $items,
            'groups' => $this->getPickerGroups($labels),
        ]);
    }

    /**
     * Labels for every property, keyed by term, with the alternate labels resource templates give it. Editors
     * often know a property by its alternate label (dcterms:subject as "Subject - Topic/Names").
     */
    protected function getPropertyLabels(): array
    {
        $translate = $this->getView()->plugin('translate');

        $labels = [];
        foreach ($this->api->search('properties', ['sort_by' => 'label'])->getContent() as $property) {
            $vocabulary = $property->vocabulary();
            $labels[$property->term()] = [
                'label' => $translate($property->label()),
                'vocabulary' => $vocabulary->label(),
                'prefix' => $vocabulary->prefix(),
                'alternates' => [],
            ];
        }

        foreach ($this->api->search('resource_templates')->getContent() as $template) {
            foreach ($template->resourceTemplateProperties() as $templateProperty) {
                $term = $templateProperty->property()->term();
                $alternate = $templateProperty->alternateLabel();
                if ($alternate && isset($labels[$term]) && !in_array($alternate, $labels[$term]['alternates'], true)) {
                    $labels[$term]['alternates'][] = $alternate;
                }
            }
        }

        return $labels;
    }

    /**
     * Picker options: the properties used by the site's items (any items outside a site context), grouped by
     * vocabulary with Dublin Core first, as in Omeka's own property selects.
     */
    protected function getPickerGroups(array $labels): array
    {
        $site = null;
        try {
            $site = $this->getView()->currentSite();
        } catch (\Throwable $e) {
            // No site context.
        }
        $query = $site ? ['site_id' => $site->id()] : ['used' => true];

        $groups = [];
        foreach ($this->api->search('properties', $query)->getContent() as $property) {
            $term = $property->term();
            if (!isset($labels[$term])) {
                continue;
            }
            $prefix = $labels[$term]['prefix'];
            $groups[$prefix]['label'] = $labels[$term]['vocabulary'];
            $groups[$prefix]['options'][$term] = $this->describe($term, $labels[$term]);
        }

        uksort($groups, function ($a, $b) use ($groups) {
            if ($a === 'dcterms' || $b === 'dcterms') {
                return $a === 'dcterms' ? -1 : 1;
            }
            return strcasecmp($groups[$a]['label'], $groups[$b]['label']);
        });
        foreach ($groups as &$group) {
            uasort($group['options'], function ($a, $b) {
                return strcasecmp($a['text'], $b['text']);
            });
        }

        return $groups;
    }

    /**
     * How to show a term: display is the lozenge label (the first alternate label, else the property label);
     * text is the picker option, which also carries the other names and the term so the search finds them.
     */
    protected function describe(string $term, ?array $info): array
    {
        if (!$info) {
            return ['display' => $term, 'text' => $term, 'unknown' => true];
        }

        $names = array_values(array_unique(array_merge($info['alternates'], [$info['label']])));
        $display = array_shift($names);
        $text = $names
            ? sprintf('%s (%s; %s)', $display, implode('; ', $names), $term)
            : sprintf('%s (%s)', $display, $term);

        return ['display' => $display, 'text' => $text, 'unknown' => false];
    }
}
