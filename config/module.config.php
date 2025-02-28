<?php
  namespace AgileThemeTools;

  use AgileThemeTools\Service\Controller\AgileSearchHandlerFactory;
  use AgileThemeTools\Service\ViewHelper\AgileSearchHelperFactory;
  use AgileThemeTools\View\Helper\AgileSearchHelper;

  return array(
      'view_manager' => [
          'template_path_stack' => [
              dirname(__DIR__) . '/view',
          ],
      ],
      'block_layouts' => [
          'invokables' => [
              'pageTitle' => Site\BlockLayout\PageTitle::class,
          ],
          'factories' => [
              // 'glossary' => Service\BlockLayout\GlossaryItemFactory::class,
              'excerpt' => Service\BlockLayout\ExcerptFactory::class,
              'regionalHtml' => Service\BlockLayout\RegionalHtmlFactory::class,
              'regionalTitle' => Service\BlockLayout\RegionalTitleFactory::class,
              'byline' => Service\BlockLayout\BylineFactory::class,
              'contributorList' => Service\BlockLayout\ContributorListFactory::class,
              'representativeImage' => Service\BlockLayout\RepresentativeImageFactory::class,
              'slideshow' => Service\BlockLayout\SlideshowFactory::class,
              'poster' => Service\BlockLayout\PosterFactory::class,
              'itemListing' => Service\BlockLayout\ItemListingFactory::class,
              'linkCards' => Service\BlockLayout\LinkCardsFactory::class,
              'callout' => Service\BlockLayout\CalloutFactory::class,
              'quotation' => Service\BlockLayout\QuotationFactory::class,
              'deck' => Service\BlockLayout\DeckFactory::class,
              'sectionPageListing' => Service\BlockLayout\SectionPageListingFactory::class,
              'homepageSplash' => Service\BlockLayout\HomepageSplashFactory::class,
              'sectionIntroSplash' => Service\BlockLayout\SectionIntroSplashFactory::class,
              'html' => Service\BlockLayout\HtmlFactory::class,

          ]
      ],
      'form_elements' => [
          'invokables' => [
              'regionmenu' => Form\Element\RegionMenuSelect::class,
              'SectionListingTemplateSelect' => Form\Element\SectionListingTemplateSelect::class,
              'SlideViewerCompositionSelect' => Form\Element\SlideViewerCompositionSelect::class,
          ],
          'factories' => [
              'SectionsMenuSelect' => Service\Form\Element\SectionsMenuSelectFactory::class
          ]
      ],
      'service_manager' => [
              'factories' => [
                  'SectionManager' => Service\Controller\SectionManagerFactory::class,
                  'AgileSearchHandler' => AgileSearchHandlerFactory::class,
              ],
          ],
     'view_helpers' => [
         'aliases' => [
           'agile_search_helper' => AgileSearchHelper::class,
           'agileSearchHelper' => AgileSearchHelper::class
         ],
         'factories' => [
           AgileSearchHelper::class => AgileSearchHelperFactory::class,
         ],
        'invokables' => [
            'ckEditor' => View\Helper\CustomCKEditorConfig::class,
            'card' => View\Helper\Card::class,
            'dateHandler' => View\Helper\DateHandler::class,
            'pageSlug' => View\Helper\PageSlug::class,
            'textSummary' => View\Helper\TextSummary::class

        ],
     ]
 /*         'router' => [
              'routes' => [
                  'add-page-action' => [
                      'type' => \Zend\Router\Http\Segment::class,
                      'options' => [
                          'route' => '/admin/site/s/:site-slug/add-page',
                          'constraints' => [
                              'site-slug' => '[a-zA-Z0-9_-]+',
                          ],
                          'defaults' => [
                              '__NAMESPACE__' => 'RegionalOptionsForm\Controller',
                              'controller' => 'BlockOptionsAdminController',
                              'action' => 'add',
                          ]
                      ]
                  ]
              ]
          ] */

  );

