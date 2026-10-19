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
working), and `unknown`. The wrappers use Omeka's row vocabulary, so one theme stylesheet covers Omeka core forms,
the stock Advanced Search form (rendered through Omeka's `formRow` partial) and this one:

    div.field.form-field.field-type-<type>.field-name-<name> [.required] [.field-has-error] #<id>_field
      div.field-meta > label.field-label [+ div.field-description]     (absent when the element has no label)
      div.inputs     > the control [+ label after, for a single checkbox]
      ul.messages                                                      (errors only)

A group of controls (multi-checkbox, radio) is a `fieldset.field` with a `legend`. `.form-field`, `.field-type-*`
and `.field-name-*` are the layout hooks: rules target fields by name (`.field-name-q`), never by position. Buttons
rendered by the layout carry `agile-button <type>` (the search submit is `agile-button search`), the theme's button
vocabulary, so no stylesheet needs an element selector. Selects rendered by the layout get `agile-select` and lose
Advanced Search's `chosen-select`: the Agile search page enhances its selects with Select2 (`search-support.js`,
scope `#search-page`, including filter rows added later); Chosen is left to Advanced Search's own forms. A theme
overrides a partial for markup; the rules that decide grouping and columns are still to come (the design pass).

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

## Facets and chips (2026-10-13)

The facet partials (`facet-checkboxes`, `facet-links`) draw exactly what Advanced Search prepares: value, label,
count, active state, url. Which values appear, their order and their labels are Advanced Search configuration
(facet `label`, `order`, `more`, `state`). A facet whose list comes back empty is hidden by `search-support.js`.
Facet state `static` (the default) renders a heading with the values; `expand`/`collapse`/`collapse_unless_set`
render a `<details>` accordion. Active facets and search filters render as pill chips (`.lozenge`): the sidebar's
through `facet-actives.phtml`, the results area's through the same partial fed by AS's `facetActives` helper, the
filter row through `search-filters-links.phtml`. Nothing is relabelled in the templates any more ("Item Set" →
"Collection" is the facet's label in the configuration).
