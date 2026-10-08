<?php
namespace AgileThemeTools\View\Helper;

use AgileThemeTools\Dependency\DependencyAudit;
use Laminas\View\Helper\AbstractHelper;

/**
 * Renders the dependency audit (Dependency\DependencyAudit).
 *
 *   $this->agileDependencies()           the full table, for this module's configuration page
 *   $this->agileDependencies('summary')  a short panel listing only what is wrong: empty when nothing is
 */
class AgileDependencies extends AbstractHelper
{
    protected DependencyAudit $audit;

    public function __construct(DependencyAudit $audit)
    {
        $this->audit = $audit;
    }

    public function __invoke(string $mode = 'full'): string
    {
        if ($mode === 'summary') {
            $problems = $this->audit->problems();
            return $problems ? $this->getView()->partial('common/dependency-audit-summary', ['problems' => $problems, 'audit' => $this->audit]) : '';
        }
        $html = $this->getView()->partial('common/dependency-audit', ['rows' => $this->audit->rows(), 'audit' => $this->audit]);
        // The Advanced Search configurations and the form element classes the Agile form layout knows (G2 fixtures).
        if (class_exists(\AdvancedSearch\Api\Representation\SearchConfigRepresentation::class)) {
            try {
                $view = $this->getView();
                $configs = $view->api()->search('search_configs')->getContent();
                $coverage = \AgileThemeTools\Search\FormCoverage::report($configs, $view->plugin('formLayout'));
                $version = ($this->audit->rows()['AdvancedSearch']['installed'] ?? null) ?: ($this->audit->manifest()['AdvancedSearch']['pinned'] ?? '');
                $html .= $view->partial('common/search-form-coverage', ['coverage' => $coverage, 'advancedSearchVersion' => (string) $version]);
            } catch (\Throwable $e) {
                // The panel is informational; a failure here must not break the configuration page.
            }
        }
        return $html;
    }
}
