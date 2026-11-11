<?php
namespace AgileThemeTools\View\Helper;

use AgileThemeTools\Dependency\DependencyAudit;
use AgileThemeTools\Patch\PatchManager;
use Laminas\View\Helper\AbstractHelper;

/**
 * Renders the dependency audit (Dependency\DependencyAudit) and the state of the Agile patches (Patch\PatchManager).
 *
 *   $this->agileDependencies()           the full tables, for this module's configuration page
 *   $this->agileDependencies('summary')  a short panel listing only what is wrong: empty when nothing is
 */
class AgileDependencies extends AbstractHelper
{
    protected DependencyAudit $audit;
    protected ?PatchManager $patches;

    public function __construct(DependencyAudit $audit, ?PatchManager $patches = null)
    {
        $this->audit = $audit;
        $this->patches = $patches;
    }

    public function __invoke(string $mode = 'full'): string
    {
        if ($mode === 'summary') {
            $problems = array_merge($this->audit->problems(), $this->patchProblems());
            return $problems ? $this->getView()->partial('common/dependency-audit-summary', ['problems' => $problems, 'audit' => $this->audit]) : '';
        }
        $html = $this->getView()->partial('common/dependency-audit', ['rows' => $this->audit->rows(), 'audit' => $this->audit]);
        if ($this->patches) {
            $html .= $this->getView()->partial('common/patch-audit', ['rows' => $this->patchRows()]);
        }
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

    /**
     * Every patch of an installed module, with the keys of an audit row (label, severity, message, fix), so that the
     * summary panel lists them with the module problems.
     *
     * @return array[]
     */
    public function patchRows(): array
    {
        if (!$this->patches) {
            return [];
        }
        try {
            $statuses = $this->patches->statuses();
        } catch (\Throwable $e) {
            return [];
        }
        $rows = [];
        foreach ($statuses as $status) {
            if ($status['status'] === PatchManager::STATUS_NOT_INSTALLED) {
                continue;
            }
            $rows[] = $status + [
                'label' => sprintf('%s %s: %s', $status['module'], $status['version'], $status['title']),
                'severity' => $this->patches->severity($status),
                'fix' => $this->patchFix($status),
            ];
        }
        return $rows;
    }

    /** @return array[] The patch rows that need attention. */
    public function patchProblems(): array
    {
        return array_values(array_filter($this->patchRows(), fn ($row) => in_array($row['severity'], ['error', 'warning'], true)));
    }

    protected function patchFix(array $status): string
    {
        $cli = 'php modules/AgileThemeTools/data/scripts/patches.php apply ' . $status['id'];
        $impact = (string) ($status['definition']['impact'] ?? '');
        switch ($status['status']) {
            case PatchManager::STATUS_MISSING:
            case PatchManager::STATUS_PARTIAL:
                return trim(sprintf('%s Apply it: Easy Admin > Check and fix > Agile modules > "Apply the Agile patches", or on the server: %s', $impact, $cli));
            case PatchManager::STATUS_UNVERIFIED:
                return sprintf('Review the module code, then apply it on the server with: %s --allow-unverified', $cli);
            case PatchManager::STATUS_LEGACY:
                return sprintf('Reinstall the files of %s %s from its release, then: %s', $status['module'], $status['version'], $cli);
            case PatchManager::STATUS_CONFLICT:
                return sprintf('Reinstall the files of %s %s from its release (local changes are lost), then: %s', $status['module'], $status['version'], $cli);
            case PatchManager::STATUS_UNSUPPORTED:
                $versions = array_merge(...array_column($status['definition']['variants'], 'versions'));
                return trim(sprintf('%s Use a version of %s this patch was verified on (%s), or update Agile Theme Tools.', $impact, $status['module'], implode(', ', $versions)));
            default:
                return '';
        }
    }
}
