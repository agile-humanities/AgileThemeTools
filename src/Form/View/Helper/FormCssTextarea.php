<?php
namespace AgileThemeTools\Form\View\Helper;

use AgileThemeTools\Styling\CustomCss;
use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;

/**
 * Renders a CssTextarea: the textarea followed by the variables reference for the relevant site.
 */
class FormCssTextarea extends AbstractHelper
{
    protected CustomCss $customCss;
    protected ApiManager $api;

    public function __construct(CustomCss $customCss, ApiManager $api)
    {
        $this->customCss = $customCss;
        $this->api = $api;
    }

    public function __invoke(ElementInterface $element)
    {
        return $this->render($element);
    }

    public function render(ElementInterface $element): string
    {
        $view = $this->getView();
        $site = null;
        $siteId = method_exists($element, 'getSiteId') ? $element->getSiteId() : null;
        if ($siteId) {
            try {
                $site = $this->api->read('sites', $siteId)->getContent();
            } catch (\Exception $e) {
                $site = null;
            }
        }
        return $view->partial('common/css-textarea-form', [
            'element' => $element,
            'groups' => $this->customCss->getVariables($site),
            'customCss' => $this->customCss,
            'site' => $site,
        ]);
    }
}
