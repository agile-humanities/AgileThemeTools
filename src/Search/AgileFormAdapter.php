<?php declare(strict_types=1);
namespace AgileThemeTools\Search;

use AdvancedSearch\FormAdapter\MainFormAdapter;

/**
 * The Agile Theme Tools search form, offered as a Form choice in an Advanced Search configuration (generic
 * requirements G2).
 *
 * It keeps everything Advanced Search does — the form object and its filter configuration, autosuggest, the
 * query and response — and replaces only the two templates: the form is laid out element by element through the
 * module's form-layout helper (search/agile/form), and the headers load Advanced Search's script and the
 * module's search support script (search/agile/form-headers). The search page template that goes with it is
 * search/agile/search, set on the configuration's "Theme template" (the setup presets do this).
 */
class AgileFormAdapter extends MainFormAdapter
{
    protected $formPartial = 'search/agile/form';
    protected $formPartialHeaders = 'search/agile/form-headers';
    protected $label = 'Agile Theme Tools'; // @translate

    /** The page template an administrator sets on the search configuration to use the module's search page. */
    const PAGE_TEMPLATE = 'search/agile/search';
}
