# The Agile search page

Advanced Search owns searching: its configurations, filters, engines, autosuggest, query and response. Agile Theme
Tools adds a **form adapter** and a **search page** to it, so any theme — the Agile base themes, Omeka's default,
others — gets the Agile search page without carrying search templates of its own.

## Switching a search configuration to it

Two settings on the Advanced Search configuration (Admin > Search manager > the configuration):

1. **Form**: choose *Agile Theme Tools*. This is the form adapter (`Search\AgileFormAdapter`, registered under
   `advanced_search_form_adapters`). It keeps Advanced Search's form object, its filter configuration, autosuggest
   and query handling, and replaces only the form template and the headers.
2. **Theme template (phtml file)**, under Display > Advanced: `search/agile/search`. This is the search page —
   facets, results header and footer, results as cards, mobile controls — shipped in the module under
   `view/search/agile/`.

The setup presets set both. On the generic stack, `dev-tools/search-config-agile.php` does the same.

## The form layout (G1)

The adapter's form template lays the form out element by element through the `formLayout` view helper
(`View\Helper\FormLayout`): one wrapper and label pattern per element kind, from the partials in
`view/common/form-layout/` — `field` (text, search, number, select, checkbox, multi-checkbox, radio, range,
textarea), `fieldset` (recursed), `collection` (repeatable rows), `hidden`, `button`, `range-double` and
`multi-text` (Advanced Search's own elements, drawn by their own helpers inside the wrapper so its script keeps
working), and `unknown`. Wrapper classes: `.form-field .field-type-<type> .field-name-<name>`, plus
`.field-required` and `.field-has-error`. A theme overrides a partial for markup; the rules that decide grouping
and columns are still to come (the design pass).

The hooks Advanced Search's script expects are preserved: `.search-filters-advanced > fieldset.filter` with the
`.search-filter-plus` button and the row template for the advanced filters, the range slider's classes, the
facet classes. The module's own `asset/js/search-support.js` (facet collapsing, the mobile filter panel, display
controls) loads after Advanced Search's script from `search/agile/form-headers.phtml`.

An element class the layout does not know is rendered by Laminas' row helper inside a generic wrapper marked
`data-unknown-element`, and `FormLayout::unknownTypes()` records it; the dependency audit will report these
against the pinned Advanced Search version (G2 fixtures, to come).

## What moved out of the theme

The Advanced Agile Base Theme carried 22 search templates (about 2,200 lines) and a search script. They now
live in the module: `view/search/agile/{search, form, form-headers, facets-list, facets, facet-checkboxes,
facet-links, facet-link, facet-actives, results, results-header-footer, search-filters-links,
mobile-browse-controls}.phtml` and `asset/js/search-support.js`. The theme styles the module's markup; an
end-case theme that must change one partial overrides that partial alone. The theme keeps its header search box
(`common/search-form.phtml`) and its overrides of Omeka's core advanced search form (`common/advanced-search*`),
which belong to Omeka's own item search page, not to Advanced Search.
