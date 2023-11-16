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
use Zend\Form\Element\Text;

class GlossaryItem extends AbstractBlockLayout
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
        return 'Glossary Item'; // @translate
    }


    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();
        $data['term'] = isset($data['term']) ? $this->htmlPurifier->purify($data['term']) : '';
        $data['description'] = isset($data['description']) ? $this->htmlPurifier->purify($data['description']) : '';
        $data['anchor'] = isset($data['anchor']) ? $this->htmlPurifier->purify($data['anchor']) : '';
        $block->setData($data);
    }
    
    public function form(PhpRenderer $view, SiteRepresentation $site,
                         SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
      
        $term = new Text("o:block[__blockIndex__][o:data][term]");
        $term->setLabel('Term');

        $description = new Textarea("o:block[__blockIndex__][o:data][description]");
        $description->setAttribute('class', 'block-html full wysiwyg');
        $description->setAttribute('rows',30);        
        $description->setLabel('Definition');
        
        $anchor = new Text("o:block[__blockIndex__][o:data][anchor]");
        $anchor->setLabel('Anchor');

        if ($block) {
            $term->setAttribute('value', $block->dataValue('term'));
            $description->setAttribute('value', $block->dataValue('description'));
            $anchor->setAttribute('value', $block->dataValue('anchor'));
        }
        
        
        
        
        $html = "<p>Add a term and its definition</p>";
        $html .= $view->formRow($term);
        $html .= $view->formRow($description);    
        $html .= '<a href="#" class="collapse" aria-label="collapse"><h4>' . $view->translate('Options'). '</h4></a>';
        $html .= '<div class="collapsible">';
        $html .= $view->formRow($anchor);
        $html .= "<p  style='font-size: 12px'><strong>Provide an optional named anchor to create an internal link for this term.</strong> It must be all lowercase with no spaces and must be unique to this term. The internal link itself will be the URL of the page followed by a pound (#) and the value given below, e.g. the URL for “my-anchor” will be https://somesite.org/s/path/to/page#my-anchor</p>";
        $html .= "</div>";
        
        return $html;


    }

    public function prepareRender(PhpRenderer $view) {
        $view->headScript()->appendFile($view->assetUrl('js/glossary.js', 'AgileThemeTools'));
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block) {

        $data = $block->data();
        
        return $view->partial(
            'common/block-layout/glossary-item',
            [
                'term' => $data['term'],
                'description' => $data['description'],
                'anchor' => array_key_exists('anchor',$data) ? $data['anchor'] : '',
                'id' => $block->id(),
            ]
        );
    }


}


