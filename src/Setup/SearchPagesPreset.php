<?php
namespace AgileThemeTools\Setup;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * The standard search pages, all with the same facets (collection, class, subject, type, author, language, date):
 *
 *   /s/<site>/find            Omeka default (Advanced Search's own form and page, as installed; left as it is)
 *   /s/<site>/search-solr     Agile filters with Solr      (Solr engine, Agile form and results page)
 *   /s/<site>/search-agile    Agile filters without Solr   (internal engine, Agile form and results page)
 *
 * The Advanced theme overrides Advanced Search's templates (its view/search/), so on that theme all three render as
 * the Agile page; the form adapter and results template only matter on other themes.
 *
 * The settings start from Advanced Search's stock configuration (data/configs/search_config.default.php), whose
 * facets are the ones above, each set to join its selected values with "and" (each value narrows the results). On
 * Solr, the aliases that name property terms (title, author, description, date) and the advanced filter rows point
 * at Solr fields instead, since Solr cannot resolve property terms there; the date facet uses a year field this
 * preset maps.
 *
 * Solr: the preset reuses the Solr engine SearchSolr created at install (keeping its 47 default maps and suggester),
 * pointing it at the core URL given, and indexes it. The Solr page is skipped when no URL is given or Solr does not
 * answer. The site's main search becomes the Solr page when there is one, the Agile internal page otherwise; on a
 * theme that does not declare Agile Theme Tools (e.g. the Simplified theme), Advanced Search's own page.
 *
 * Configs are found by slug, so a rerun updates them instead of adding more. Needs Advanced Search, and the Reference
 * module for facets on the internal engine; SearchSolr for the Solr page.
 */
class SearchPagesPreset
{
    const SLUG_SOLR = 'search-solr';
    const SLUG_AGILE = 'search-agile';
    const YEAR_FIELD = 'dcterms_date_year_i';

    /** Stock aliases that point at property terms, which Solr cannot resolve inside an alias, and their Solr fields. */
    const SOLR_ALIASES = [
        'title' => 'dcterms_title_txt',
        'author' => 'dcterms_creator_ss',
        'creator' => 'dcterms_creator_ss',
        'description' => 'dcterms_description_txt',
        'date' => self::YEAR_FIELD,
    ];

    /** The stock advanced filter rows and the Solr field each searches (dcterms:creator folds into Author). */
    const SOLR_FILTER_FIELDS = [
        'title' => 'dcterms_title_txt',
        'author' => 'dcterms_creator_txt',
        'dcterms:creator' => 'dcterms_creator_txt',
        'dcterms:subject' => 'dcterms_subject_txt',
        'date' => 'dcterms_date_txt',
        'description' => 'dcterms_description_txt',
        'resource_class_id' => 'resource_class_s',
    ];

    /**
     * @param array $options solr_url: the Solr core URL as seen from this server, e.g. http://localhost:8983/solr/omeka
     * @return string[] Lines for the job log.
     */
    public static function apply(ServiceLocatorInterface $services, SiteRepresentation $site, array $options = []): array
    {
        $modules = $services->get('Omeka\ModuleManager');
        $active = fn (string $id) => ($module = $modules->getModule($id)) && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
        if (!$active('AdvancedSearch')) {
            return ['Advanced Search is not active: no search pages written.'];
        }
        $api = $services->get('Omeka\ApiManager');
        $lines = [];
        if (!$active('Reference')) {
            $lines[] = 'The Reference module is not active: facets on the internal engine will be empty.';
        }

        // Engines. The internal engine is a singleton created by Advanced Search at install.
        $internal = self::first($api, 'search_engines', ['engine_adapter' => 'internal'])
            ?: $api->create('search_engines', ['o:name' => 'Internal (sql)', 'o:engine_adapter' => 'internal', 'o:settings' => ['resource_types' => ['items', 'item_sets']]])->getContent();
        $solr = null;
        $solrUrl = trim((string) ($options['solr_url'] ?? ''));
        if (!$solrUrl) {
            $lines[] = 'No Solr core URL given: the Solr search page is skipped.';
        } elseif (!$active('SearchSolr')) {
            $lines[] = 'SearchSolr is not active: the Solr search page is skipped.';
        } elseif (!class_exists(\SearchSolr\Stdlib\SolrCore::class)) {
            // The engine is configured the SearchSolr 3.5.70+ way (the engine is the core); older versions keep cores
            // as their own resource, and the preset would fail half-way.
            $lines[] = 'SearchSolr is older than 3.5.70: the Solr search page is skipped. Update SearchSolr, then rerun the preset.';
        } else {
            [$solr, $message] = self::solrEngine($services, $solrUrl);
            $lines[] = $message;
        }

        // Settings, from the stock configuration.
        $translate = $services->get('ViewHelperManager')->get('translate');
        $stock = (require OMEKA_PATH . '/modules/AdvancedSearch/data/configs/search_config.default.php')['o:settings'];
        $agile = $stock;
        $agile['results']['template'] = 'search/agile/search';
        // Each selected facet value narrows the results, on both engines (Advanced Search's default, "or", widens
        // them and keeps the other values listed).
        foreach ($agile['facet']['facets'] ?? [] as $key => $facet) {
            if (is_array($facet) && ($facet['join'] ?? '') === '') {
                $agile['facet']['facets'][$key]['join'] = 'and';
            }
        }

        $configs = [];
        if ($solr) {
            $agileSolr = $agile;
            foreach (self::SOLR_ALIASES as $alias => $field) {
                $agileSolr['index']['aliases'][$alias]['fields'] = [$field];
            }
            // The advanced filters (field / operator / value rows) only offer fields the engine reports, which on
            // Solr are its own field names: the same rows and labels, on the Solr text fields.
            $advanced = &$agileSolr['form']['filters']['advanced']['options']['fields'];
            if (is_array($advanced)) {
                $rows = [];
                foreach ($advanced as $key => $row) {
                    $field = self::SOLR_FILTER_FIELDS[$key] ?? null;
                    if ($field && !isset($rows[$field])) {
                        $rows[$field] = ['value' => $field, 'label' => $row['label'] ?? $key];
                    }
                }
                $advanced = $rows;
            }
            unset($advanced);
            // The stock settings point at the internal suggester; use the Solr engine's own.
            $suggester = self::first($api, 'search_suggesters', ['engine_id' => $solr->id()]);
            $agileSolr['q']['suggester'] = $suggester ? (string) $suggester->id() : '';
            $configs[] = self::config($api, 'Agile filters with Solr', self::SLUG_SOLR, $solr->id(), 'agile', $agileSolr);
        }
        $configs[] = self::config($api, 'Agile filters without Solr', self::SLUG_AGILE, $internal->id(), 'agile', $agile);
        foreach ($configs as $config) {
            $lines[] = sprintf('Search page "%s": /s/%s/%s (%s engine, %s form).', $config->name(), $site->slug(), $config->slug(), $config->searchEngine()->engineAdapterName(), $config->formAdapterName());
        }

        // Site assignment, as Advanced Search's admin does when a config is added (manageSearchConfigSettings).
        $siteSettings = $services->get('Omeka\Settings\Site');
        $ids = array_map('intval', (array) $siteSettings->get('advancedsearch_configs', [], $site->id()));
        $ids = array_values(array_unique(array_merge($ids, array_map(fn ($config) => $config->id(), $configs))));
        sort($ids);
        $siteSettings->set('advancedsearch_configs', $ids, $site->id());
        // A theme that does not declare Agile Theme Tools (the Simplified theme, any other theme) keeps Advanced
        // Search's own page (the install-time "find" config) as its main search: the Agile results page is
        // styled by the Advanced theme.
        $theme = $services->get('Omeka\Site\ThemeManager')->getTheme($site->theme());
        $stockConfig = self::first($api, 'search_configs', ['slug' => 'find']);
        $main = ($theme && $theme->getIni('agile_theme_tools')) || !$stockConfig ? $configs[0] : $stockConfig;
        $siteSettings->set('advancedsearch_main_config', $main->id(), $site->id());
        // The item, item set and media browse pages and the header's "advanced" link go through the same config.
        foreach (['advancedsearch_items_config', 'advancedsearch_item_sets_config', 'advancedsearch_media_config'] as $key) {
            $siteSettings->set($key, $main->id(), $site->id());
        }
        $siteSettings->set('advancedsearch_main_config_advanced_link', 'dialog', $site->id());
        $lines[] = sprintf('Site "%s": main search is "%s"; item, item set and media browse use it too.', $site->slug(), $main->name());

        // Indexing: Solr needs a full index; the internal engine needs none, only its suggester.
        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        if ($solr) {
            // Forced: an index job queued while the engine still had SearchSolr's install-time host ("localhost")
            // can hang waiting for Solr, and an unforced run gives way to any running index job.
            $dispatcher->dispatch(\AdvancedSearch\Job\IndexSearch::class, ['search_engine_ids' => [$solr->id()], 'resource_types' => $solr->setting('resource_types', ['items']), 'clear_index' => true, 'force' => true]);
            $lines[] = 'Solr indexing started (a separate job).';
        }
        $internalSuggester = self::first($api, 'search_suggesters', ['engine_id' => $internal->id()]);
        if ($internalSuggester) {
            $dispatcher->dispatch(\AdvancedSearch\Job\IndexSuggestions::class, ['search_suggester_id' => $internalSuggester->id()]);
            $lines[] = 'Internal suggestions indexing started (a separate job).';
        }
        return $lines;
    }

    /**
     * The Solr engine for the core URL: one already pointing at that core, else the one SearchSolr created at install
     * (repointed), else a new one with SearchSolr's default maps. Adds the year map the date facet needs. Returns
     * [engine or null when Solr does not answer, log line].
     */
    protected static function solrEngine(ServiceLocatorInterface $services, string $url): array
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        $position = strrpos($path, '/solr/');
        if (empty($parts['host']) || $position === false || !trim(substr($path, $position + 6), '/')) {
            return [null, sprintf('"%s" is not a Solr core URL (expected e.g. http://localhost:8983/solr/omeka): the Solr search page is skipped.', $url)];
        }
        $client = [
            'scheme' => $parts['scheme'] ?? 'http',
            'host' => $parts['host'],
            'port' => (int) ($parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 8983)),
            'path' => substr($path, 0, $position) . '/',
            'core' => trim(substr($path, $position + 6), '/'),
            'secure' => ($parts['scheme'] ?? 'http') === 'https',
            'username' => $parts['user'] ?? null,
            'password' => $parts['pass'] ?? null,
            'bypass_certificate_check' => false,
        ];

        $api = $services->get('Omeka\ApiManager');
        $engines = $api->search('search_engines', ['engine_adapter' => 'solarium'])->getContent();
        $same = fn ($engine) => ($c = $engine->settings()['solr']['client'] ?? []) && ($c['host'] ?? '') === $client['host'] && (int) ($c['port'] ?? 0) === $client['port'] && ($c['core'] ?? '') === $client['core'];
        $engine = current(array_filter($engines, $same)) ?: ($engines[0] ?? null);
        if ($engine) {
            $settings = $engine->settings();
            $settings['solr']['client'] = array_replace($settings['solr']['client'] ?? [], $client);
            $engine = $api->update('search_engines', $engine->id(), ['o:settings' => $settings], [], ['isPartial' => true])->getContent();
        } else {
            $engine = $api->create('search_engines', ['o:name' => 'Solr', 'o:engine_adapter' => 'solarium', 'o:settings' => [
                'resource_types' => ['items'],
                'solr' => ['client' => $client, 'server_id' => '', 'support' => '', 'resource_languages' => ''],
            ]])->getContent();
            foreach (require OMEKA_PATH . '/modules/SearchSolr/config/default_mappings.php' as $map) {
                $api->create('solr_maps', ['o:engine' => ['o:id' => $engine->id()], 'o:resource_name' => $map['resource_name'], 'o:field_name' => $map['field_name'], 'o:alias' => $map['alias'] ?? null, 'o:source' => $map['source'], 'o:pool' => $map['pool'] ?? [], 'o:settings' => $map['settings'] ?? []]);
            }
        }

        $core = new \SearchSolr\Stdlib\SolrCore($engine, $services);
        // status() is a bool; status(true) returns a message, "OK" included, so ask for the message only on failure.
        if ($core->status() !== true) {
            $status = $core->status(true);
            return [null, sprintf('Solr at %s does not answer (%s): the Solr search page is skipped. The engine "%s" keeps the URL; rerun the preset when Solr is up.', $url, is_object($status) ? (string) $status : 'no response', $engine->name())];
        }
        if (!self::first($api, 'solr_maps', ['engine_id' => $engine->id(), 'field_name' => self::YEAR_FIELD])) {
            $api->create('solr_maps', ['o:engine' => ['o:id' => $engine->id()], 'o:resource_name' => 'items', 'o:field_name' => self::YEAR_FIELD, 'o:source' => 'dcterms:date', 'o:pool' => [], 'o:settings' => ['formatter' => 'date', 'date_out' => 'year', 'part' => 'min', 'label' => 'Year']]);
        }
        // The suggester's field types, which SearchSolr only creates from its admin pages.
        try {
            $core->ensureSuggestFieldType();
            $core->ensureSuggestFoldedFieldType();
            $core->ensureSuggestField();
        } catch (\Throwable $e) {
            // Suggestions are optional: search works without them.
        }
        return [$engine, sprintf('Solr engine "%s" (#%d) points at %s and answers.', $engine->name(), $engine->id(), $url)];
    }

    /** Creates the config, or updates the one with the same slug. */
    protected static function config($api, string $name, string $slug, int $engineId, string $form, array $settings)
    {
        $data = ['o:name' => $name, 'o:slug' => $slug, 'o:search_engine' => ['o:id' => $engineId], 'o:form_adapter' => $form, 'o:settings' => $settings];
        $existing = self::first($api, 'search_configs', ['slug' => $slug]);
        return $existing
            ? $api->update('search_configs', $existing->id(), $data)->getContent()
            : $api->create('search_configs', $data)->getContent();
    }

    protected static function first($api, string $resource, array $query)
    {
        return $api->search($resource, $query + ['limit' => 1])->getContent()[0] ?? null;
    }
}
