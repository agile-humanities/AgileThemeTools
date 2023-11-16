<?php
namespace AgileThemeTools\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Site\BlockLayout\AbstractBlockLayout;

class PageTitle extends AbstractBlockLayout
{
    public function getLabel()
    {
        return 'Page title'; // @translate
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        return $view->escapeHtml($page->title());
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {

        return $view->partial(
            'common/block-layout/page-title.phtml',
            [
                'title' => $view->escapeHtml($block->page()->title())
            ]
        );

        //return sprintf('<h1>%s</h1>', $view->escapeHtml($block->page()->title()));
    }
}
