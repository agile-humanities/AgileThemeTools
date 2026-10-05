<?php
namespace AgileThemeTools\Form\Element;

use Laminas\Form\Element;

/**
 * An ordered list of property terms, stored as a comma-separated string, e.g. "dcterms:date,dcterms:subject".
 *
 * Edited as removable, draggable lozenges with a searchable property picker (see FormPropertyList). Because the
 * value stays a plain comma-separated string, it can replace a text field holding such a list with no migration.
 *
 * Options, all optional and settable from theme.ini as options.*:
 * - sortable (bool, default true): allow drag-reordering. Turn off where order has no meaning.
 * - add_all (bool, default false): show a button that adds every property used on the site.
 * - empty_label (string): shown when the list is empty, e.g. to say what an empty list means.
 */
class PropertyList extends Element
{
    public function isSortable(): bool
    {
        $sortable = $this->getOption('sortable');
        return $sortable === null || (bool) $sortable;
    }

    public function hasAddAll(): bool
    {
        return (bool) $this->getOption('add_all');
    }

    public function getEmptyLabel(): ?string
    {
        $emptyLabel = $this->getOption('empty_label');
        return is_string($emptyLabel) && $emptyLabel !== '' ? $emptyLabel : null;
    }
}
