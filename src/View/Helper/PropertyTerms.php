<?php

namespace AgileThemeTools\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * Reads and applies lists of property terms, such as the theme settings edited with the PropertyList element
 * (card_terms, key_terms, full_metadata_terms) and the global list of properties not linked to search.
 */
class PropertyTerms extends AbstractHelper
{
    /**
     * @var array Default values the current theme declares for its settings in theme.ini, keyed by setting name.
     */
    protected $settingDefaults;

    public function __construct(array $settingDefaults)
    {
        $this->settingDefaults = $settingDefaults;
    }

    public function __invoke()
    {
        return $this;
    }

    /**
     * Parses a comma-separated list of terms, dropping blanks and duplicates.
     */
    public function parse($list): array
    {
        $terms = array_map('trim', explode(',', is_array($list) ? implode(',', $list) : (string) $list));
        return array_values(array_unique(array_filter($terms, 'strlen')));
    }

    /**
     * Returns the terms in a theme setting. A setting the site has never saved falls back to the default declared
     * for it in theme.ini; a setting saved empty stays empty.
     */
    public function fromThemeSetting(string $name): array
    {
        $value = $this->getView()->themeSetting($name);
        if ($value === null) {
            $value = $this->settingDefaults[$name] ?? '';
        }
        return $this->parse($value);
    }

    /**
     * Keeps only the given terms from resource values (as from values()), in the order given.
     */
    public function select(array $values, array $terms): array
    {
        $selected = [];
        foreach ($terms as $term) {
            if (isset($values[$term])) {
                $selected[$term] = $values[$term];
            }
        }
        return $selected;
    }

    /**
     * Sorts resource values (as from values()) by displayed label: the template's alternate label, else the
     * property label.
     */
    public function sortByLabel(array $values): array
    {
        $translate = $this->getView()->plugin('translate');
        $labels = [];
        foreach ($values as $term => $propertyData) {
            $labels[$term] = $propertyData['alternate_label'] ?: $translate($propertyData['property']->label());
        }

        $collator = extension_loaded('intl') ? new \Collator('root') : null;
        uksort($values, function ($a, $b) use ($labels, $collator) {
            return $collator ? $collator->compare($labels[$a], $labels[$b]) : strcasecmp($labels[$a], $labels[$b]);
        });

        return $values;
    }
}
