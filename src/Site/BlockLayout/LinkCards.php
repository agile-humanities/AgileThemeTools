<?php
namespace AgileThemeTools\Site\BlockLayout;

use AgileThemeTools\Controller\SectionManager;
use AgileThemeTools\Form\Element\RegionMenuSelect;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\HtmlPurifier;
use Zend\Form\Element\Text;
use Laminas\Form\Element\Textarea;
use Laminas\Form\FormElementManager as FormElementManager;
use Laminas\View\Renderer\PhpRenderer;

class LinkCards extends AbstractBlockLayout
{

    /**
     * @var HtmlPurifier
     */
    protected $htmlPurifier;
    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    protected $blockLayoutManager;

    protected $errorStore;

    public function getLabel()
    {
        return 'Site Link Cards'; // @translate
    }

    public function __construct($blockLayoutManager, $htmlPurifier, FormElementManager $formElementManager, ErrorStore $errorStore)
    {
        $this->htmlPurifier = $htmlPurifier;
        $this->formElementManager = $formElementManager;
        $this->blockLayoutManager = $blockLayoutManager;
        $this->errorStore = $errorStore;
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();
        $data['introduction'] = isset($data['introduction']) ? $this->htmlPurifier->purify($data['introduction']) : '';
        $data['title'] = isset($data['title']) ? $this->htmlPurifier->purify($data['title']) : '';
        $data['buttonText'] = isset($data['buttonText']) ? $this->htmlPurifier->purify($data['buttonText']) : '';
        $data['buttonPath'] = isset($data['buttonPath']) ? $this->htmlPurifier->purify($data['buttonPath']) : '';
        $data['classes'] = isset($data['classes']) ? $this->htmlPurifier->purify($data['classes']) : '';
        $data['itemFields'] = [];
        
        for ($i=0;$i<3;$i++) {
          if (!empty($data["itemLabel_{$i}"]) || !empty($data["itemTitle_{$i}"]) || !empty($data["itemUrl_{$i}"])) {
            $data['itemFields'][$i]['label'] =  isset($data["itemLabel_{$i}"]) ? $this->htmlPurifier->purify($data["itemLabel_{$i}"]) : '';
            $data['itemFields'][$i]['title'] = isset($data["itemTitle_{$i}"]) ? $this->htmlPurifier->purify($data["itemTitle_{$i}"]) : '';
            $data['itemFields'][$i]['subtitle'] = isset($data["itemSubTitle_{$i}"]) ? $this->htmlPurifier->purify($data["itemSubTitle_{$i}"]) : '';
            $data['itemFields'][$i]['thumbnailUrl'] = isset($data["itemTitle_{$i}"]) ? $this->htmlPurifier->purify($data["itemThumbnailUrl_{$i}"]) : '';
            $data['itemFields'][$i]['description'] = isset($data["itemDescription_{$i}"]) ? $this->htmlPurifier->purify($data["itemDescription_{$i}"]) : '';
            $data['itemFields'][$i]['url'] = isset($data["itemTitle_{$i}"]) ? $this->htmlPurifier->purify($data["itemUrl_{$i}"]) : '';
          }
        }
        
        $block->setData($data);
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {

        $title = new Text("o:block[__blockIndex__][o:data][title]");
        $title->setAttribute('class', 'block-title');
        $title->setLabel('Title');

        $introductionField = new Textarea("o:block[__blockIndex__][o:data][introduction]");
        $introductionField->setLabel('Intro Text');
        $introductionField->setAttribute('class', 'block-introduction full wysiwyg');
        $introductionField->setAttribute('rows',20);

        $buttonText = new Text("o:block[__blockIndex__][o:data][buttonText]");
        $buttonText->setAttribute('class', 'block-button-text');
        $buttonText->setLabel('Button Text (optional)');

        $buttonPath = new Text("o:block[__blockIndex__][o:data][buttonPath]");
        $buttonPath->setAttribute('class', 'block-button-path');
        $buttonPath->setLabel('Button Path (optional)');

        $region = new RegionMenuSelect();

        $classes = new Text("o:block[__blockIndex__][o:data][classes]");
        $classes->setAttribute('class', 'block-button-classes');
        $classes->setLabel('Block Class (optional, separate with spaces)');
        
        for ($i=0;$i<3;$i++) {
          $iLabel = $i+1;

          ${'itemLabel' . $i} = new Text("o:block[__blockIndex__][o:data][itemLabel_{$i}]");
          ${'itemLabel' . $i}->setAttribute('class', 'block-item-label');
          ${'itemLabel' . $i}->setLabel("A label for this item {$iLabel} (e.g. “Exhibit”)");
          
          ${'itemTitle' . $i} = new Text("o:block[__blockIndex__][o:data][itemTitle_{$i}]") ;
          ${'itemTitle' . $i}->setAttribute('class', 'block-item-title');
          ${'itemTitle' . $i}->setLabel("A title for item {$iLabel}");

          ${'itemSubTitle' . $i} = new Text("o:block[__blockIndex__][o:data][itemSubTitle_{$i}]") ;
          ${'itemSubTitle' . $i}->setAttribute('class', 'block-item-title');
          ${'itemSubTitle' . $i}->setLabel("Optional subtitle for {$iLabel}");

          ${'itemThumbnailUrl' . $i} = new Text("o:block[__blockIndex__][o:data][itemThumbnailUrl_{$i}]") ;
          ${'itemThumbnailUrl' . $i}->setAttribute('class', 'block-item-title');
          ${'itemThumbnailUrl' . $i}->setLabel("An full URL to an external image to use as a thumbnail. Use in place of the attachment above.");

          ${'itemDescription' . $i} = new Textarea("o:block[__blockIndex__][o:data][itemDescription_{$i}]]");
          ${'itemDescription' . $i}->setLabel('Description');
          ${'itemDescription' . $i}->setAttribute('class', 'block-description full wysiwyg');
          ${'itemDescription' . $i}->setAttribute('rows',15);

          ${'itemUrl' . $i} = new Text("o:block[__blockIndex__][o:data][itemUrl_{$i}]");
          ${'itemUrl' . $i}->setAttribute('class', 'block-item-url');
          ${'itemUrl' . $i}->setLabel("The URL link for item {$iLabel}");
        }
        
        $buttonPath = new Text("o:block[__blockIndex__][o:data][buttonPath]");
        $buttonPath->setAttribute('class', 'block-button-path');
        $buttonPath->setLabel('Button Path (optional)');

        if ($block) {
            $title->setAttribute('value',$block->dataValue('title'));
            $introductionField->setAttribute('value', $block->dataValue('introduction'));
            $buttonPath->setAttribute('value',$block->dataValue('buttonPath'));
            $buttonText->setAttribute('value',$block->dataValue('buttonText'));
            $region->setAttribute('value', $block->dataValue('region'));
            $classes->setAttribute('value',$block->dataValue('classes'));
            
            for ($i=0;$i<3;$i++) {
                ${'itemLabel' . $i}->setAttribute('value',$block->dataValue("itemLabel_{$i}"));
                ${'itemTitle' . $i}->setAttribute('value',$block->dataValue("itemTitle_{$i}"));
                ${'itemSubTitle' . $i}->setAttribute('value',$block->dataValue("itemSubTitle_{$i}"));
                ${'itemThumbnailUrl' . $i}->setAttribute('value',$block->dataValue("itemThumbnailUrl_{$i}"));
                ${'itemDescription' . $i}->setAttribute('value',$block->dataValue("itemDescription_{$i}"));
                ${'itemUrl' . $i}->setAttribute('value',$block->dataValue("itemUrl_{$i}"));
            }
            
        } else {
            $region->setAttribute('value','region:default');
        }

        $html = "<h3>Create Link Cards with Introduction</h3>";
        $html .= $view->formRow($title);
        $html .= $view->formRow($introductionField);
        $html .= "<p>Select 1-3 items to link. Caption them to add descriptions:</p>";
        $html .= $view->blockAttachmentsForm($block);
        $html .= "<p>Provide titles and links for each image above:</p>";
        $html .= "<hr/>";
        
        for ($i=0;$i<3;$i++) {
          $iLabel = $i+1;
          
          $html .= '<a href="#" class="collapse collapsed" aria-label="collapse"><h4>' . $view->translate("Fields for Item {$iLabel}"). '</h4></a>';
          $html .= '<div class="collapsible collapsed">';
          $html .= $view->formRow(${'itemLabel' . $i});
          $html .= $view->formRow(${'itemTitle' . $i});
          $html .= $view->formRow(${'itemSubTitle' . $i});
          $html .= $view->formRow(${'itemThumbnailUrl' . $i});
          $html .= $view->formRow(${'itemDescription' . $i});
          $html .= $view->formRow(${'itemUrl' . $i});
          $html .= '</div>';
        }
        $html .= "<hr/>";
        
        $html .= '<a href="#" class="collapse" aria-label="collapse"><h4>' . $view->translate('Additional Options'). '</h4></a>';
        $html .= '<div class="collapsible collapsed">';
        $html .= $view->formRow($buttonText);
        $html .= $view->formRow($buttonPath);
        $html .= $view->formRow($classes);
        $html .= $view->blockThumbnailTypeSelect($block);
        $html .= $view->formRow($region);
        $html .= '</div>';

        return $html;
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $attachments = $block->attachments();
        $data = $block->data();
        $thumbnailType = $block->dataValue('thumbnail_type', 'square');
        list($scope,$region) = explode(':',$data['region']);

        return $view->partial('common/block-layout/link-cards', [
            'block' => $block,
            'attachments' => $attachments,
            'itemFields' => $data['itemFields'],
            'blockId' => $block->id(),
            'buttonText' => $data['buttonText'],
            'buttonPath' => $data['buttonPath'],
            'title' => $data['title'],
            'introduction' => $data['introduction'],
            'class' => $data['classes'],
            'hasTitle' => !empty($data['title']),
            'hasIntroduction' => !empty($data['introduction']),
            'hasButton' => !empty($data['buttonText']) && !empty($data['buttonPath']),
            'thumbnailType' => $thumbnailType,
            'targetID' => '#' . $region
        ]);
    }
}
