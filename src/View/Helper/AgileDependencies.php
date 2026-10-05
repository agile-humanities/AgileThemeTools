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
        return $this->getView()->partial('common/dependency-audit', ['rows' => $this->audit->rows(), 'audit' => $this->audit]);
    }
}
