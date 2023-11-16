<?php
namespace AgileThemeTools\Site\BlockLayout;

use AgileThemeTools\Form\Element\RegionMenuSelect;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Mvc\Controller\Plugin\Api;
use Laminas\Form\FormElementManager as FormElementManager;
use Omeka\Entity\SitePageBlock;
use Omeka\Stdlib\HtmlPurifier;
use Omeka\Stdlib\ErrorStore;
use Laminas\Form\Element\Textarea;
use Laminas\Form\Element\Select;
use Laminas\View\Renderer\PhpRenderer;

class ContributorList extends AbstractBlockLayout
{
    /**
     * @var HtmlPurifier
     */
    protected $htmlPurifier;
    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var Api
     */
    protected $api;

    public function __construct(Api $api, HtmlPurifier $htmlPurifier, FormElementManager $formElementManager)
    {
        $this->api = $api;
        $this->htmlPurifier = $htmlPurifier;
        $this->formElementManager = $formElementManager;
    }

    public function getLabel()
    {
        return 'Contributor List'; // @translate
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
            'site-admin/block-layout/byline',
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
            'common/block-layout/contributor-list',
            [
                'html' => $data['html'],
                'blockId' => $block->id(),
                'regionClass' => 'region-' . $region,
                'targetID' => '#' . $region,
                'contributors' => $this->getContributors()
            ]
        );
    }

    protected function getContributors()
    {

      $contributors = [];

      $query = [
        'site_id' => 1,
        'property' => 'dcterms:creator',
        'sort_by' => 'dcterms:title',
        'sort_order' => 'asc',
        'limit' => null
      ];

      /** @var \Omeka\Api\Response $response */
      $response = $this->api->search('items', $query);


      /** @var \Omeka\Api\Representation\ItemRepresentation $row */
      foreach ($response->getContent() as $row) {
        $rowResponse = $this->api->read('items', $row->id());

        /** @var \Omeka\Api\Representation\ItemRepresentation $item */
        $item = $rowResponse->getContent();

        /** @var \Omeka\Api\Representation\ValueRepresentation $value */
        $value = $item->value('dcterms:creator');

        if ($value) {
          $name = $value->value();

          if (strpos($name, ',') > -1) {
            // Presume last name first
            $keybase = $name;
          } else {
            // Presume last name last
            $words = explode(' ', $name);
            $keybase = array_pop($words);
            foreach ($words as $word) {
              $keybase .= " {$word}";
            }
          }

          $sortkey = str_replace(' ','-',strtolower(preg_replace("/[^A-Za-z0-9 ]/", '', $keybase)));

          // Create new entry if it does not exist
          if (!array_key_exists($sortkey, $contributors)) {
            $contributors[$sortkey] = [
              'name' => $name,
              'id' => $item->id(),
              'items' => []
            ];
          }

          $contributors[$sortkey]['items']["id-{$item->id()}"] = $item;
        }
      }

      asort($contributors);

      var_dump($contributors);

      return $contributors;
    }


}


