<?php
namespace AgileThemeTools\Site\BlockLayout;

use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Text;
use Laminas\Form\Element\Textarea;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\HtmlPurifier;

/**
 * Page summary: how a page presents itself elsewhere — in section listings, link cards and search results. One
 * block holding the representative image (an attached item or media), a summary, a byline and a label. Site
 * pages have no metadata of their own in Omeka, so this is where that lives; the card display mode reads it
 * (DisplayMode\Card). It renders nothing on the page itself unless "show on the page" is ticked, and replaces the
 * older representative-image, excerpt and byline blocks, which cards still read as fallbacks.
 */
class PageSummary extends AbstractBlockLayout
{
    protected HtmlPurifier $htmlPurifier;

    public function __construct(HtmlPurifier $htmlPurifier)
    {
        $this->htmlPurifier = $htmlPurifier;
    }

    public function getLabel()
    {
        return 'Page summary'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();
        $data['summary'] = $this->htmlPurifier->purify((string) ($data['summary'] ?? ''));
        $data['byline'] = trim(strip_tags((string) ($data['byline'] ?? '')));
        $data['label'] = trim(strip_tags((string) ($data['label'] ?? '')));
        $data['show'] = empty($data['show']) ? '0' : '1';
        $block->setData($data);
    }

    public function form(PhpRenderer $view, SiteRepresentation $site, SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null)
    {
        $summary = new Textarea('o:block[__blockIndex__][o:data][summary]');
        $summary->setAttribute('class', 'block-html full wysiwyg')->setAttribute('rows', 6);
        $byline = new Text('o:block[__blockIndex__][o:data][byline]');
        $label = new Text('o:block[__blockIndex__][o:data][label]');
        $show = new Checkbox('o:block[__blockIndex__][o:data][show]');
        if ($block) {
            $summary->setAttribute('value', $block->dataValue('summary'));
            $byline->setAttribute('value', $block->dataValue('byline'));
            $label->setAttribute('value', $block->dataValue('label'));
            $show->setAttribute('value', $block->dataValue('show') ? '1' : '0');
        }
        $summary->setLabel('Summary')->setOption('info', 'How the page is described in listings and search results. Leave empty to summarise the page\'s first text block automatically.'); // @translate
        $byline->setLabel('Byline')->setOption('info', 'Shown under the title on cards, e.g. the author or date.'); // @translate
        $label->setLabel('Label')->setOption('info', 'The small label above the title on cards. Empty: "Page".'); // @translate
        $show->setLabel('Show on the page')->setOption('info', 'Normally this block is only read by listings. Tick to also show the summary at its place on the page.'); // @translate
        return $view->blockAttachmentsForm($block, false, [], 1)
            . '<p class="explanation">' . $view->escapeHtml($view->translate('Representative image: attach the item or media whose image stands for this page in listings. One attachment is used.')) . '</p>'
            . $view->formRow($summary) . $view->formRow($byline) . $view->formRow($label) . $view->formRow($show);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        if (!$block->dataValue('show')) {
            return '';
        }
        return $view->partial('common/block-layout/page-summary', [
            'block' => $block,
            'summary' => $block->dataValue('summary'),
            'byline' => $block->dataValue('byline'),
            'label' => $block->dataValue('label'),
            'attachments' => $block->attachments(),
        ]);
    }
}
