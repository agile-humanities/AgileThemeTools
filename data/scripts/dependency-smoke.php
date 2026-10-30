<?php declare(strict_types=1);
/**
 * Dependency smoke test: exercises every interface Agile Theme Tools relies on in the modules it depends on
 * (config/dependencies.php), so a newer release of a dependency can be tested before its pin moves. Class and
 * method checks run in process; the IIIF and search page checks fetch from this installation over HTTP.
 *
 * Run inside the Omeka installation, as the web server user:
 *
 *   php modules/AgileThemeTools/data/scripts/dependency-smoke.php [--base-url http://localhost] [--site slug]
 *
 * Prints one line per check and exits 1 when any check fails. Rerun it on the stack that carries the versions
 * you want to pin; when it passes, move "pinned" in config/dependencies.php and regenerate docs/DEPENDENCIES.md
 * (data/scripts/dependencies.php docs).
 */

$options = getopt('', ['base-url::', 'site::']);
$baseUrl = rtrim((string) ($options['base-url'] ?? 'http://localhost'), '/');

$omekaPath = dirname(__DIR__, 4);
require $omekaPath . '/bootstrap.php';
$app = Omeka\Mvc\Application::init(require OMEKA_PATH . '/application/config/application.config.php');
$services = $app->getServiceManager();
$services->get('Omeka\AuthenticationService')->getStorage()->write($services->get('Omeka\EntityManager')->find(Omeka\Entity\User::class, 1));
$api = $services->get('Omeka\ApiManager');
$modules = $services->get('Omeka\ModuleManager');
$helpers = $services->get('ViewHelperManager');
$plugins = $services->get('ControllerPluginManager');
$config = $services->get('Config');

$results = [];
$check = function (string $module, string $label, $ok, string $detail = '') use (&$results): void {
    $results[] = [$module, $label, (bool) $ok, $detail];
    printf("%-5s %-16s %s%s\n", $ok ? 'ok' : 'FAIL', $module, $label, $detail !== '' ? " — $detail" : '');
};
$version = function (string $id) use ($modules): string {
    $module = $modules->getModule($id);
    return $module ? ($module->getIni('version') ?? '?') . ' (' . $module->getState() . ')' : 'absent';
};
$active = fn (string $id) => ($m = $modules->getModule($id)) && $m->getState() === Omeka\Module\Manager::STATE_ACTIVE;
$methods = function (string $class, array $names): array {
    return array_values(array_filter($names, fn ($n) => !method_exists($class, $n)));
};
// A URL built in this process has no host ("http:///iiif/…"): keep its path, which $http() resolves against --base-url.
$pathOf = fn (string $url): string => '/' . ltrim((string) preg_replace('#^[a-z]+://[^/]*#i', '', $url), '/');
$http = function (string $path) use ($baseUrl): array {
    $ch = curl_init($baseUrl . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_HEADER => false]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return [$code, $body, $type];
};

// A public site, an item with an image and that item's image, for the HTTP checks.
$site = null;
foreach ($api->search('sites')->getContent() as $candidate) {
    if ($candidate->isPublic() && (empty($options['site']) || $candidate->slug() === $options['site'])) {
        $site = $candidate;
        break;
    }
}
$imageItem = null;
$imageMedia = null;
foreach ($api->search('items', ['sort_by' => 'created', 'sort_order' => 'desc', 'limit' => 100])->getContent() as $item) {
    foreach ($item->media() as $media) {
        if (strpos((string) $media->mediaType(), 'image/') === 0 && $media->hasThumbnails()) {
            $imageItem = $item;
            $imageMedia = $media;
            break 2;
        }
    }
}
printf("Omeka %s; base URL %s; site %s; image item %s\n\n", $services->get('Omeka\Settings')->get('version'), $baseUrl, $site ? $site->slug() : 'none', $imageItem ? '#' . $imageItem->id() : 'none');

// ---- Omeka core -----------------------------------------------------------------------------------------------
$rpb = $services->get('Omeka\ResourcePageBlockLayoutManager');
$check('omeka', 'ResourcePageBlockLayoutManager API', !$methods(get_class($rpb), ['getResourcePageBlocks', 'getResourcePageRegions', 'setSiteSettings', 'getRegisteredNames']));
$check('omeka', 'Theme API', !$methods(Omeka\Site\Theme\Theme::class, ['getSettingsKey', 'getConfigSpec', 'getIni', 'getPath', 'isConfigurable']));
$check('omeka', 'Site settings / theme settings key', $site && is_array($services->get('Omeka\Settings\Site')->get('theme_settings_' . $site->theme(), [], $site->id())));
$check('omeka', 'Our resource page blocks registered', !array_diff(['itemHeading', 'itemSummary', 'itemFullMetadata'], $rpb->getRegisteredNames()));

// ---- EasyAdmin: Check and fix events, the add-ons installer ----------------------------------------------------
if ($active('EasyAdmin')) {
    $v = $version('EasyAdmin');
    $check('EasyAdmin', "Check and fix form and controller classes ($v)", class_exists(EasyAdmin\Form\CheckAndFixForm::class) && class_exists(EasyAdmin\Controller\Admin\CheckAndFixController::class));
    try {
        $form = $services->get('FormElementManager')->get(EasyAdmin\Form\CheckAndFixForm::class);
        $form->init();
        $ours = array_filter(['agilethemetools_dependencies', 'agilethemetools_theme_build', 'agilethemetools_site_setup'], fn ($n) => $form->has($n));
        $check('EasyAdmin', 'Our task groups appear on the Check and fix form (form.add_elements)', count($ours) === 3, implode(', ', $ours));
    } catch (\Throwable $e) {
        $check('EasyAdmin', 'Our task groups appear on the Check and fix form', false, $e->getMessage());
    }
    $addons = $plugins->has('easyAdminAddons') ? $plugins->get('easyAdminAddons') : null;
    $check('EasyAdmin', 'Add-ons installer plugin API (dataFromNamespace, dataFromUrl, installAddon, dirExists, getAddons)', $addons && !$methods(get_class($addons), ['dataFromNamespace', 'dataFromUrl', 'installAddon', 'dirExists', 'getAddons']));
    $check('EasyAdmin', 'Generic task runner script', is_file(OMEKA_PATH . '/modules/EasyAdmin/data/scripts/task.php'));
} else {
    $check('EasyAdmin', 'active', false, $version('EasyAdmin'));
}

// ---- Log: job logs readable in the admin ----------------------------------------------------------------------
$check('Log', 'Log entity and job dispatcher (' . $version('Log') . ')', class_exists(Log\Entity\Log::class) && strpos(get_class($services->get(Omeka\Job\Dispatcher::class)), 'Log\\') === 0);

// ---- IiifServer: manifests and the URL helpers the presets and blocks use ---------------------------------------
if ($active('IiifServer')) {
    $v = $version('IiifServer');
    $check('IiifServer', "iiifUrl and iiifMediaUrl helpers ($v)", $helpers->has('iiifUrl') && $helpers->has('iiifMediaUrl'));
    if ($imageItem) {
        try {
            $manifestUrl = $helpers->get('iiifUrl')($imageItem, 'iiifserver/manifest');
            $path = $pathOf($manifestUrl);
            [$code, $body] = $http($path);
            $json = json_decode($body, true);
            $canvases = $json['items'] ?? ($json['sequences'][0]['canvases'] ?? null);
            $check('IiifServer', "Manifest $path", $code === 200 && is_array($canvases) && count($canvases) > 0, "HTTP $code, " . (is_array($canvases) ? count($canvases) . ' canvas(es)' : 'no canvases'));
            $service = $canvases[0]['items'][0]['items'][0]['body']['service'] ?? ($canvases[0]['images'][0]['resource']['service'] ?? null);
            $service = is_array($service) && isset($service[0]) ? $service[0] : $service;
            $serviceId = is_array($service) ? ($service['id'] ?? $service['@id'] ?? '') : '';
            $check('IiifServer', 'Canvas declares an image service', $serviceId !== '', $serviceId);
        } catch (\Throwable $e) {
            $check('IiifServer', 'Manifest', false, $e->getMessage());
        }
    }
} else {
    $check('IiifServer', 'active', false, $version('IiifServer'));
}

// ---- ImageServer: info.json and tiles -------------------------------------------------------------------------
if ($active('ImageServer')) {
    $v = $version('ImageServer');
    $check('ImageServer', "imageSize and tileInfo helpers ($v)", $helpers->has('imageSize') && $helpers->has('tileInfo'));
    if ($imageMedia) {
        $infoUrl = $helpers->has('iiifMediaUrl') ? $helpers->get('iiifMediaUrl')($imageMedia, 'imageserver/info') : null;
        $path = $infoUrl ? $pathOf($infoUrl) : '/iiif/' . $imageMedia->id() . '/info.json';
        [$code, $body] = $http($path);
        $info = json_decode($body, true);
        $check('ImageServer', "info.json $path", $code === 200 && !empty($info['width']) && !empty($info['height']), "HTTP $code, " . ($info['width'] ?? '?') . '×' . ($info['height'] ?? '?'));
        $base = $info['id'] ?? $info['@id'] ?? null;
        if ($base) {
            // A size that is not pre-tiled, so the image server renders it (a pre-tiled region redirects to the file).
            [$tcode, $tbody, $ttype] = $http($pathOf($base) . '/full/300,/0/default.jpg');
            $check('ImageServer', 'A derived image (full/300,)', $tcode === 200 && strpos($ttype, 'image/') === 0 && strlen($tbody) > 0, "HTTP $tcode $ttype, " . strlen($tbody) . ' bytes');
        }
        $size = $helpers->get('imageSize')($imageMedia, 'large');
        $check('ImageServer', 'imageSize() of the large derivative', is_array($size) && !empty($size['width']), json_encode($size));
    }
} else {
    $check('ImageServer', 'active', false, $version('ImageServer'));
}

// ---- Mirador: the helper class MiradorManifest extends, and the settings the preset writes -----------------------
if ($active('Mirador')) {
    $v = $version('Mirador');
    $missing = $methods(Mirador\View\Helper\Mirador::class, ['__invoke', 'render']);
    $check('Mirador', "View helper class and render() ($v)", class_exists(Mirador\View\Helper\Mirador::class) && !$missing, $missing ? 'missing ' . implode(', ', $missing) : (method_exists(Mirador\View\Helper\Mirador::class, 'renderMirador4') ? 'Mirador 4 available' : 'Mirador 3 only'));
    $check('Mirador', 'mirador and miradorManifest helpers', $helpers->has('mirador') && $helpers->has('miradorManifest'));
    $keys = array_merge(array_keys($config['mirador']['config'] ?? []), array_keys($config['mirador']['settings'] ?? []), array_keys($config['mirador']['site_settings'] ?? []));
    $preset = ['mirador_version', 'mirador_plugins', 'mirador_config_item', 'mirador_config_collection'];
    $check('Mirador', 'Settings the Agile preset writes exist in the module config', !array_diff($preset, $keys), implode(', ', array_diff($preset, $keys)) ?: 'all present');
    $plugins3 = $config['mirador']['config']['mirador_plugins_3'] ?? null;
    $check('Mirador', 'image-tools plugin known', in_array('image-tools', array_keys($config['mirador']['plugins'] ?? []), true) || is_file(OMEKA_PATH . '/modules/Mirador/data/plugins/plugins-esm.php') || is_file(OMEKA_PATH . '/modules/Mirador/data/plugins/plugins.php'));
} else {
    $check('Mirador', 'active', false, $version('Mirador'));
}

// ---- AdvancedSearch: the adapter base class, the representations and helpers the Agile templates use ------------
if ($active('AdvancedSearch')) {
    $v = $version('AdvancedSearch');
    $adapters = $config['advanced_search_form_adapters']['factories'] ?? $config['advancedsearch']['form_adapters'] ?? [];
    $check('AdvancedSearch', "Agile form adapter registered ($v)", isset($adapters['agile']) || $services->has('AdvancedSearch\FormAdapterManager') && $services->get('AdvancedSearch\FormAdapterManager')->has('agile'));
    $check('AdvancedSearch', 'Form adapter base API', !$methods(AdvancedSearch\FormAdapter\MainFormAdapter::class, ['getFormPartial', 'getFormPartialHeaders', 'renderForm']));
    $missing = $methods(AdvancedSearch\Api\Representation\SearchConfigRepresentation::class, ['setting', 'subSetting', 'renderForm', 'renderSearchFilters', 'searchEngine', 'engineAdapter', 'formAdapterName', 'siteUrl', 'slug', 'name']);
    $check('AdvancedSearch', 'SearchConfig representation API', !$missing, implode(', ', $missing));
    $missing = $methods(AdvancedSearch\Response::class, ['getActiveFacets', 'getResourceTotalResults', 'getTotalResults', 'getCurrentPage', 'getPerPage', 'getResources', 'getFacetCounts']);
    $check('AdvancedSearch', 'Response API', !$missing, implode(', ', $missing));
    $missing = $methods(AdvancedSearch\Query::class, ['getQueryRefine', 'getActiveFacets']);
    $check('AdvancedSearch', 'Query API', !$missing, implode(', ', $missing));
    $need = ['searchSortSelector', 'searchPaginationPerPageSelector', 'facetActives', 'getSearchConfig', 'easyMeta'];
    $missing = array_filter($need, fn ($h) => !$helpers->has($h));
    $check('AdvancedSearch', 'View helpers the Agile templates call', !$missing, implode(', ', $missing));
    $stock = ['search', 'search-form-main', 'search-form-main-headers', 'results', 'results-header-footer', 'facets-list', 'facet-actives', 'facet-checkboxes', 'facet-links'];
    $missing = array_filter($stock, fn ($t) => !is_file(OMEKA_PATH . "/modules/AdvancedSearch/view/search/$t.phtml"));
    $check('AdvancedSearch', 'Stock templates the Advanced theme overrides still exist', !$missing, implode(', ', $missing));
    if ($site) {
        $configId = (int) $services->get('Omeka\Settings\Site')->get('advancedsearch_main_config', 0, $site->id());
        $searchConfig = $configId ? $api->read('search_configs', $configId)->getContent() : null;
        if ($searchConfig) {
            $path = $pathOf($searchConfig->siteUrl($site->slug()));
            [$code, $body] = $http($path . '?q=a');
            $check('AdvancedSearch', "Main search page $path?q=a renders", $code === 200 && strpos($body, 'search-results') !== false && !preg_match('/Fatal error|Warning:|Notice:/', $body), "HTTP $code");
        }
    }
} else {
    $check('AdvancedSearch', 'active', false, $version('AdvancedSearch'));
}

// ---- SearchSolr: the core wrapper the search helper and preset use --------------------------------------------
if ($active('SearchSolr')) {
    $v = $version('SearchSolr');
    $missing = $methods(SearchSolr\Stdlib\SolrCore::class, ['mapsByResourceName', 'status', 'ensureSuggestFieldType', 'ensureSuggestFoldedFieldType', 'ensureSuggestField']);
    $check('SearchSolr', "SolrCore API ($v)", class_exists(SearchSolr\Stdlib\SolrCore::class) && !$missing, implode(', ', $missing));
    $check('SearchSolr', 'Solarium engine adapter class', class_exists(SearchSolr\EngineAdapter\Solarium::class));
    $check('SearchSolr', 'Default mappings file', is_file(OMEKA_PATH . '/modules/SearchSolr/config/default_mappings.php'));
}

// ---- Reference: facets on the internal engine, the reference blocks -------------------------------------------
if ($active('Reference')) {
    $check('Reference', 'references view helper (' . $version('Reference') . ')', $helpers->has('references'));
    $check('Reference', 'Reference block settings in config', isset($config['reference']['block_settings']['reference']) && isset($config['reference']['block_settings']['referenceTree']));
} else {
    $check('Reference', 'active', false, $version('Reference'));
}

// ---- AdvancedMedia and StaticSitemaps -------------------------------------------------------------------------
if ($active('AdvancedMedia')) {
    $check('AdvancedMedia', 'Resource page blocks registered (' . $version('AdvancedMedia') . ')', !array_diff(['advancedMedia', 'advancedMediaDownloads'], $rpb->getRegisteredNames()));
    $check('AdvancedMedia', 'View helpers', $helpers->has('advancedMediaCompose') && $helpers->has('advancedMediaPlayer'));
} else {
    $check('AdvancedMedia', 'active', false, $version('AdvancedMedia'));
}
$check('StaticSitemaps', 'Job class (' . $version('StaticSitemaps') . ')', !$active('StaticSitemaps') || class_exists(StaticSitemaps\Job\GenerateSitemaps::class));

$failed = array_filter($results, fn ($r) => !$r[2]);
printf("\n%d checks, %d failed.\n", count($results), count($failed));
exit($failed ? 1 : 0);
