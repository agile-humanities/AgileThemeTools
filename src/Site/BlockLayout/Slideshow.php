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
use Zend\Form\Element\Text;
use Laminas\Form\Element\Textarea;
use Laminas\View\Renderer\PhpRenderer;
use Zend\Form\Element\Checkbox;

class Slideshow extends AbstractBlockLayout
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
        return 'Slide Viewer'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();
        $data['introduction'] = isset($data['introduction']) ? $this->htmlPurifier->purify($data['introduction']) : '';
        $data['title'] = isset($data['title']) ? $this->htmlPurifier->purify($data['title']): '';
        $data['subtitle'] = isset($data['subtitle']) ? $this->htmlPurifier->purify($data['subtitle']): '';
        $data['buttonText'] = isset($data['buttonText']) ? $this->htmlPurifier->purify($data['buttonText']): '';
        $data['labelText'] = isset($data['labelText']) ? $this->htmlPurifier->purify($data['labelText']): '';


        $block->setData($data);
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
                         SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        $hasTitle = new Checkbox("o:block[__blockIndex__][o:data][hastitle]");
        $hasTitle->setAttribute('class', 'block-hastitle');
        $hasTitle->setLabel('Make a Title Slide.');

        $title = new Text("o:block[__blockIndex__][o:data][title]");
        $title->setAttribute('class', 'block-title');
        $title->setLabel('Slideshow Title (optional)');

        $subtitle = new Text("o:block[__blockIndex__][o:data][subtitle]");
        $subtitle->setAttribute('class', 'block-label-text');
        $subtitle->setLabel('Subtitle (optional)');

        $introductionField = new Textarea("o:block[__blockIndex__][o:data][introduction]");
        $introductionField->setLabel('Introduction (optional)');
        $introductionField->setAttribute('class', 'block-introduction full wysiwyg');
        $introductionField->setAttribute('rows',20);

        $buttonText = new Text("o:block[__blockIndex__][o:data][buttonText]");
        $buttonText->setAttribute('class', 'block-button-text');
        $buttonText->setLabel('Button Text (e.g. “Start slideshow”. Leave blank for no button.)');

        $labelText = new Text("o:block[__blockIndex__][o:data][labelText]");
        $labelText->setAttribute('class', 'block-label-text');
        $labelText->setLabel('Label Text (optional surtitle)');
        
        // Slide Viewer Configuration Options
                
        $slidesperRow = new Text("o:block[__blockIndex__][o:data][slidesperrow]");
        $slidesperRow->setAttribute('class', 'block-slider-config-slidesperrow');
        $slidesperRow->setLabel('Number of slides per page');
        $slidesperRow->setAttribute('value',1);
        
        $autoPlay = new Checkbox("o:block[__blockIndex__][o:data][autoplay]");
        $autoPlay->setAttribute('class', 'block-slider-config-autoplay');
        $autoPlay->setLabel('Play slideshow automatically');
        $autoPlay->setAttribute('value',false);
        
        $fade = new Checkbox("o:block[__blockIndex__][o:data][fade]");
        $fade->setAttribute('class', 'block-slider-config-fade');
        $fade->setLabel('Use cross-fade effect');
        $fade->setAttribute('value',false);
            
        $region = new RegionMenuSelect();

        if ($block) {
            $title->setAttribute('value',$block->dataValue('title'));
            $hasTitle->setAttribute('value',$block->dataValue('hastitle'));
            $introductionField->setAttribute('value', $block->dataValue('introduction'));
            $region->setAttribute('value', $block->dataValue('region'));
            $buttonText->setAttribute('value',$block->dataValue('buttonText'));
            $labelText->setAttribute('value',$block->dataValue('labelText'));
            $subtitle->setAttribute('value',$block->dataValue('subtitle'));
            $slidesperRow->setAttribute('value',!empty($block->dataValue('slidesperrow')) ? $block->dataValue('slidesperrow') : 1);
            $autoPlay->setAttribute('value',!empty($block->dataValue('autoplay')) ? $block->dataValue('autoplay') : false);
            $fade->setAttribute('value',!empty($block->dataValue('fade')) ? $block->dataValue('fade') : false);
        }

        $html = $view->formRow($title);
        $html .= $view->blockAttachmentsForm($block);
        $html .= '<a href="#" class="collapse" aria-label="collapse"><h4>' . $view->translate('Options'). '</h4></a>';
        $html .= '<div class="collapsible">';
        $html .= $view->formRow($region);
        $html .= $view->blockShowTitleSelect($block);
        $html .= '</div>';
        $html .= '<hr />';
        $html .= '<a href="#" class="collapse" aria-label="collapse"><h4>' . $view->translate('Title Slide Options'). '</h4></a>';
        $html .= '<div class="collapsible">';
        $html .= '<p style="font-size: 12px">Additional information for the title slide</p>';
        $html .= $view->formRow($hasTitle);
        $html .= '<p style="font-size: 12px">The first attachment will be used as a title image.</p>';
        $html .= $view->formRow($labelText);
        $html .= $view->formRow($subtitle);
        $html .= $view->formRow($introductionField);
        $html .= $view->formRow($buttonText);
        $html .= '</div>';
        $html .= '<hr />';
        $html .= '<a href="#" class="collapse" aria-label="collapse"><h4>' . $view->translate('Slider Options'). '</h4></a>';
        $html .= '<div class="collapsible">';
        $html .= $view->formRow($slidesperRow);
        $html .= $view->formRow($autoPlay);
        $html .= $view->formRow($fade);
        $html .= '</div>';
        return $html;
    }

    public function prepareRender(PhpRenderer $view)
    {
        //$view->headLink()->appendStylesheet($view->basePath('modules/AgileThemeTools/node_modules/slick-carousel/slick/slick.css'));
        //$view->headScript()->appendFile($view->basePath('modules/AgileThemeTools/node_modules/slick-carousel/slick/slick.min.js'));
        //$view->headScript()->appendFile($view->assetUrl('js/slideshow.js', 'AgileThemeTools'));
        $view->headScript()->appendFile($view->assetUrl('js/slick.js', 'AgileThemeTools'));
        $view->headScript()->appendFile($view->assetUrl('js/00_agileComponent.js', 'AgileThemeTools'));
        $view->headScript()->appendFile($view->assetUrl('js/slideViewerComponent.js', 'AgileThemeTools'));
        $view->headScript()->appendFile($view->assetUrl('js/regional_html_handler.js', 'AgileThemeTools'));
    }


    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $attachments = $block->attachments();
        if (!$attachments) {
            return '';
        }
      

        $data = $block->data();
        $showTitleOption = $block->dataValue('show_title_option', 'item_title');
        list($scope,$region) = explode(':',$data['region']);
        $thumbnailType = 'large'; 

        $image_attachments = [];
        $audio_attachment = null;
        
        foreach($attachments as $attachment) {
            $item = $attachment->item();
            if ($item) {
                $media = $attachment->media() ?: $item->primaryMedia();
                
                // Filter for media type. $media->mediaType() returns a MIME type.
    
                if ($media) {
                    if (strpos($media->mediaType(),'audio') !== false && $audio_attachment == null) {
                      $audio_attachment = $attachment;
                    } else {
                      $image_attachments[] = $attachment;
                    }
                }
            }
        }
        
        $values = [
            'block' => $block,
            'useTitleSlide' => $data['hastitle'],
            'titleSlideAttachment' => null,
            'titleSlideItem' => null,
            'titleSlideMedia' => null,
            'titleSlideTitle' => $data['title'],
            'titleSlideSubtitle' => isset($data['subtitle']) ? $data['subtitle'] : '',
            'titleSlideIntro' => $data['introduction'],
            'buttonText' => isset($data['buttonText']) ? $data['buttonText'] : '',
            'labelText'  => isset($data['labelText']) ? $data['labelText'] : '',
            'subtitle'  => isset($data['subtitle']) ? $data['subtitle'] : '',
            'attachments' => $image_attachments,
            'thumbnailType' => $thumbnailType,
            'showTitleOption' => $showTitleOption,
            'blockId' => $block->id(),
            'regionClass' => 'region-' . $region,
            'targetID' => '#' . $region,
            'hasAudioAttachment' => $audio_attachment != null,
            'audioAttachment' => $audio_attachment,
            'optionSlidesPerRow' => (int)$data['slidesperrow'],
            'optionAutoPlay' => $data['autoplay'] ? "true" : "false",
            'optionFade' => $data['fade'] ? "true" : "false"
        ];
        
        if (count($image_attachments) > 0) {
            $values['titleSlideAttachment'] = $image_attachments[0];
            $values['titleSlideItem'] = $image_attachments[0]->item();
            $values['titleSlideMedia'] = $image_attachments[0]->media() ?: $image_attachments[0]->primaryMedia();
        }

        return $view->partial('common/block-layout/slideshow', $values);
    }
}
