<?php
namespace AgileThemeTools\Site\BlockLayout;

use AgileThemeTools\Form\Element\RegionMenuSelect;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Laminas\Form\FormElementManager as FormElementManager;
use Omeka\Entity\SitePageBlock;
use Omeka\Stdlib\HtmlPurifier;
use Omeka\Stdlib\ErrorStore;
use Laminas\Form\Element\Textarea;
use Laminas\Form\Element\Select;
use Laminas\View\Renderer\PhpRenderer;

class Deck extends AbstractBlockLayout
{
    /**
     * @var HtmlPurifier
     */
    protected $htmlPurifier;
    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    public function __construct(HtmlPurifier $htmlPurifier, FormElementManager $formElementManager)
    {
        $this->htmlPurifier = $htmlPurifier;
        $this->formElementManager = $formElementManager;
    }

    public function getLabel()
    {
        return 'Deck'; // @translate
    }


    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();
        $html = isset($data['html']) ? $this->htmlPurifier->purify($data['html']) : '';
        $data['html'] = $html;
        $block->setData($data);
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
                         SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {

        $textarea = new Textarea("o:block[__blockIndex__][o:data][html]");
        $textarea->setAttribute('class', 'block-html full wysiwyg');
        $textarea->setAttribute('rows',20);

        $region = new RegionMenuSelect();
        
        if ($block) {
            $textarea->setAttribute('value', $block->dataValue('html'));
            $region->setAttribute('value', $block->dataValue('region'));
        }

        return $view->partial(
            'site-admin/block-layout/deck.phtml',
            [
                'htmlform' => $view->formRow($textarea),
                'regionform' => $view->formRow($region),
                'data' => $block ? $block->data() : []
            ]
        );


    }

    public function prepareRender(PhpRenderer $view)
    {
        $view->headScript()->appendFile($view->assetUrl('js/regional_html_handler.js', 'AgileThemeTools'));
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block) {

        $data = $block->data();
        list($scope,$region) = explode(':',$data['region']);
        return $view->partial(
            'common/block-layout/deck.phtml',
            [
                'html' => $data['html'],
                'blockId' => $block->id(),
                'regionClass' => 'region-' . $region,
                'targetID' => '#' . $region
            ]
        );
    }


}


