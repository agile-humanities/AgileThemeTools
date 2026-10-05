<?php
namespace AgileThemeTools\View\Helper;

use AgileThemeTools\ThemeBuild\BuildManager;
use Laminas\Form\Form;
use Laminas\Form\FormElementManager;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Theme\Theme;

/**
 * Renders the site theme build panel: last build, whether a rebuild is needed and why, preflight problems with
 * their fixes, the build buttons and the log. Used on the site's Theme page and on the theme styling form, and
 * re-rendered by the status endpoint while a build runs.
 */
class ThemeBuildPanel extends AbstractHelper
{
    protected BuildManager $builder;
    protected FormElementManager $formElementManager;

    public function __construct(BuildManager $builder, FormElementManager $formElementManager)
    {
        $this->builder = $builder;
        $this->formElementManager = $formElementManager;
    }

    /**
     * @param array $options
     *   - preflight (bool, default true): run the preflight checks (spawns node briefly).
     *   - compact (bool, default false): status and buttons only, for the Theme page.
     */
    public function __invoke(SiteRepresentation $site, ?Theme $theme = null, array $options = []): string
    {
        $theme = $theme ?? $this->builder->getSiteTheme($site);
        if (!$theme) {
            return '';
        }
        $view = $this->getView();
        $status = $this->builder->getStatus($theme, $site);
        $preflight = ($options['preflight'] ?? true) ? $this->builder->preflight($theme, $site) : null;

        // The log to show: the running build's, else the last failure's, else the live build's.
        $logDir = null;
        if ($status['activeJob']) {
            $logDir = $status['buildDir'] . BuildManager::BUILDING_SUFFIX;
        } elseif ($status['failed']) {
            $logDir = $status['buildDir'] . BuildManager::FAILED_SUFFIX;
        } elseif ($status['built']) {
            $logDir = $status['buildDir'];
        }
        $logFile = $logDir ? $logDir . '/' . BuildManager::LOG_FILE : null;
        $log = $logFile && is_readable($logFile) ? (string) file_get_contents($logFile) : '';

        // Omeka adds a CSRF element to every form it builds (named after the form); the build actions validate it.
        $csrfHtml = '';
        foreach ($this->formElementManager->get(Form::class)->getElements() as $element) {
            if ($element instanceof \Laminas\Form\Element\Csrf) {
                $csrfHtml = $view->formHidden($element);
            }
        }

        return $view->partial('common/theme-build-panel', [
            'csrfHtml' => $csrfHtml,
            'site' => $site,
            'theme' => $theme,
            'status' => $status,
            'preflight' => $preflight,
            'log' => $log,
            'configurableSites' => $this->builder->getConfigurableSites($theme),
            'compact' => (bool) ($options['compact'] ?? false),
        ]);
    }
}
