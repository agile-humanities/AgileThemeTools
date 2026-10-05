<?php

namespace AgileThemeTools\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\ValueRepresentation;

/**
 * Renders a property value for public display, so the Full Metadata list, item key terms and cards agree.
 *
 * Presentation defers to the value's data type (asHtml()), with these interventions:
 * - Linked resources: a plain title link to the resource, not the data type's linkPretty() thumbnail.
 * - Dates: formatted by the dateHandler helper.
 * - Other values: linked to the site's Advanced Search page when the property has a search mapping, with the
 *   facet preselected where the search page has one (see AgileSearchHelper::getValueSearchUrl()).
 *
 * URIs, including Value Suggest values, keep their data type's external link.
 */
class ValueDisplay extends AbstractHelper
{
    /**
     * @param ValueRepresentation $value
     * @param array $options
     *   - link (bool, default true): link the value to the search page where its property allows it.
     * @return string HTML
     */
    public function __invoke(ValueRepresentation $value, array $options = []): string
    {
        $view = $this->getView();

        $valueResource = $value->valueResource();
        if ($valueResource) {
            return $valueResource->link($valueResource->displayTitle());
        }

        if ($value->uri()) {
            return $value->asHtml();
        }

        /** @var DateHandler $dateHandler */
        $dateHandler = $view->plugin('dateHandler');
        $html = $dateHandler->isDate($value)
            ? $view->escapeHtml($dateHandler->renderValue($value))
            : $value->asHtml();

        $url = ($options['link'] ?? true) ? $view->plugin('agileSearchHelper')->getValueSearchUrl($value) : '';

        // Don't nest links, e.g. an HTML value that already contains one.
        if ($url === '' || stripos($html, '<a ') !== false) {
            return $html;
        }

        return $view->plugin('hyperlink')->raw($html, $url);
    }
}
