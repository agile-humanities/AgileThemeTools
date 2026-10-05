<?php
namespace AgileThemeTools\Form\Element;

use Laminas\Form\Element\Textarea;

/**
 * A textarea for custom CSS, rendered with a reference of the CSS variables the site's stylesheet provides
 * (see FormCssTextarea). Option `site_id` selects the site whose stylesheet is read; without it the default site is
 * used, which suits the global settings form.
 */
class CssTextarea extends Textarea
{
    protected $attributes = [
        'type' => 'textarea',
        'rows' => 18,
        'class' => 'agile-css-textarea',
        'spellcheck' => 'false',
    ];

    public function getSiteId(): ?int
    {
        $siteId = $this->getOption('site_id');
        return is_numeric($siteId) ? (int) $siteId : null;
    }
}
