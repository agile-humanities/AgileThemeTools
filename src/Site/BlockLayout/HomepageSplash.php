<?php
namespace AgileThemeTools\Site\BlockLayout;

use AgileThemeTools\Form\Element\RegionMenuSelect;
use AgileThemeTools\Form\Element\SlideViewerCompositionSelect;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Laminas\Form\FormElementManager as FormElementManager;
use Omeka\Entity\SitePageBlock;
use Omeka\Stdlib\HtmlPurifier;
use Omeka\Stdlib\ErrorStore;
use Laminas\Form\Element\Text;
use Laminas\Form\Element\Textarea;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Form\Element\Checkbox;
use Omeka\Api\Representation\SiteBlockAttachmentRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Representation\ItemRepresentation;

class HomepageSplash extends AbstractBlockLayout
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
        return 'Homepage Introduction'; // @translate
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
        $hasTitle->setLabel('Site Introduction');
        $hasTitle->setAttribute('value',true);

        $title = new Text("o:block[__blockIndex__][o:data][title]");
        $title->setAttribute('class', 'block-title');
        $title->setLabel('Site Title');

        $subtitle = new Text("o:block[__blockIndex__][o:data][subtitle]");
        $subtitle->setAttribute('class', 'block-label-text');
        $subtitle->setLabel('Subtitle (optional)');

        $introductionField = new Textarea("o:block[__blockIndex__][o:data][introduction]");
        $introductionField->setLabel('Introduction (optional)');
        $introductionField->setAttribute('class', 'block-introduction full wysiwyg');
        $introductionField->setAttribute('rows',20);

        $buttonText = new Text("o:block[__blockIndex__][o:data][buttonText]");
        $buttonText->setAttribute('class', 'block-button-text');
        $buttonText->setLabel('Button Text (Leave blank for no button.)');

        $labelText = new Text("o:block[__blockIndex__][o:data][labelText]");
        $labelText->setAttribute('class', 'block-label-text');
        $labelText->setLabel('Label Text (optional surtitle)');

        // Slide Viewer Configuration Options

        $slidesperRow = new Text("o:block[__blockIndex__][o:data][slidesperrow]");
        $slidesperRow->setAttribute('class', 'block-slider-config-slidesperrow');
        $slidesperRow->setLabel('Number of slides on each row (shown horizontally)');
        $slidesperRow->setAttribute('value',1);

        $slidesToShow = new Text("o:block[__blockIndex__][o:data][slidestoshow]");
        $slidesToShow->setAttribute('class', 'block-slider-config-slidestoshow');
        $slidesToShow->setLabel('Number of slides to show (shown vertically)');
        $slidesToShow->setAttribute('value',1);

        $autoPlay = new Checkbox("o:block[__blockIndex__][o:data][autoplay]");
        $autoPlay->setAttribute('class', 'block-slider-config-autoplay');
        $autoPlay->setLabel('Play slideshow automatically');
        $autoPlay->setAttribute('value',false);

        $fade = new Checkbox("o:block[__blockIndex__][o:data][fade]");
        $fade->setAttribute('class', 'block-slider-config-fade');
        $fade->setLabel('Use cross-fade effect');
        $fade->setAttribute('value',false);

        $region = new RegionMenuSelect();

        $composition = new SlideViewerCompositionSelect();

        if ($block) {
            $title->setAttribute('value',$block->dataValue('title'));
            $hasTitle->setAttribute('value',$block->dataValue('hastitle'));
            $introductionField->setAttribute('value', $block->dataValue('introduction'));
            $region->setAttribute('value', $block->dataValue('region'));
            $composition->setAttribute('value', $block->dataValue('composition'));
            $buttonText->setAttribute('value',$block->dataValue('buttonText'));
            $labelText->setAttribute('value',$block->dataValue('labelText'));
            $subtitle->setAttribute('value',$block->dataValue('subtitle'));
            $slidesperRow->setAttribute('value',!empty($block->dataValue('slidesperrow')) ? $block->dataValue('slidesperrow') : 1);
            $slidesToShow->setAttribute('value',!empty($block->dataValue('slidestoshow')) ? $block->dataValue('slidestoshow') : 1);
            $autoPlay->setAttribute('value',!empty($block->dataValue('autoplay')) ? $block->dataValue('autoplay') : false);
            $fade->setAttribute('value',!empty($block->dataValue('fade')) ? $block->dataValue('fade') : false);
        }
        $html = '<p style="font-size: 12px">This block provides common introductory fields and an accompanying slide viewer to display images. If you do not need a persistent introduction consider using the Slide Viewer block instead.</p>';
        $html .= '<hr />';
        $html .= '<a href="#" class="collapse" aria-label="collapse"><h4>' . $view->translate('Site Introduction'). '</h4></a>';
        $html .= '<div class="collapsible">';
        $html .= $view->formRow($title);
        $html .= $view->formRow($labelText);
        $html .= $view->formRow($subtitle);
        $html .= $view->formRow($introductionField);
        $html .= $view->formRow($buttonText);
        $html .= '</div>';
        $html .= '<hr />';
        $html .= $view->blockAttachmentsForm($block);
        $html .= '<a href="#" class="collapse" aria-label="collapse"><h4>' . $view->translate('Options'). '</h4></a>';
        $html .= '<div class="collapsible">';
        $html .= $view->formRow($composition);
        $html .= '<p style="font-size: 12px; margin-top: 0">Choose how to compose the homepage introduction container with the slide viewer.';
        $html .= $view->formRow($region);
        $html .= '<p style="font-size: 12px; margin-top: 0">Select the region to assign the block to. Most commonly assigned to the Splash region.</p>';
        $html .= $view->blockShowTitleSelect($block);
        $html .= '<p style="font-size: 12px; margin-top: 0">Choose what information to display as the slide title.</p>';
        $html .= '</div>';
        $html .= '<hr />';
        $html .= '<a href="#" class="collapse" aria-label="collapse"><h4>' . $view->translate('Slider Options'). '</h4></a>';
        $html .= '<div class="collapsible">';
        $html .= $view->formRow($slidesperRow);
        $html .= '<p style="font-size: 12px; margin-top: 0">Overlay: Set to “1” for best results.<br />
                  Grid: Set to “1” if you have portrait-ratio slides (taller than they are wide). Set to “2” if you have landscape slides (wider than they are tall).</p>';
        $html .= $view->formRow($slidesToShow);
        $html .= '<p style="font-size: 12px; margin-top: 0">Set to “1” for best results.</p>';
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
        $hasAttachments = count($attachments) > 0;
        $data = $block->data();
        $showTitleOption = $block->dataValue('show_title_option', 'item_title');
        list($rscope,$region) = explode(':',$data['region']);

      $thumbnailType = 'large';

        $image_attachments = [];
        $audio_attachment = null;

        /** @var SiteBlockAttachmentRepresentation $attachment */
      foreach($attachments as $attachment) {

            /** @var ItemRepresentation $item */
            $item = $attachment->item();

            if ($item) {
              /** @var MediaRepresentation $media */
              $media = $item->primaryMedia();

              // Filter for media type. $media->mediaType() returns a MIME type.

              if ($media) {
                if (strpos($media->mediaType(), 'audio') !== false && $audio_attachment == null) {
                  $audio_attachment = $attachment;
                } else {
                  $image_attachments[] = $attachment;
                }
              }
            }
        }

        return $view->partial('common/block-layout/homepage-introduction', [
            'block' => $block,
            'useTitleSlide' => true,
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
            'optionSlidesPerRow' => array_key_exists('slidesperrow',$data) ? (int)$data['slidesperrow'] : 1,
            'optionSlidesToShow' => array_key_exists('slidestoshow',$data) ? (int)$data['slidestoshow'] : 1,
            'optionAutoPlay' => array_key_exists('autoplay',$data) ? "true" : "false",
            'optionFade' => array_key_exists('fade',$data) ? "true" : "false",
            'hasAttachments' => $hasAttachments,
            'compositionClass' => array_key_exists('composition',$data) ? $data['composition'] : ''
        ]);
    }
}
