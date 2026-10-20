<?php
  namespace AgileThemeTools;

  use AgileThemeTools\Form\View\Helper\AgileFormCollectionElementGroups;
  use AgileThemeTools\Service\Controller\AgileSearchHandlerFactory;
  use AgileThemeTools\Service\Settings\SiteThemeSettingsFactory;
  use AgileThemeTools\Service\ViewHelper\AgileItemSetsTreeHandlerFactory;
  use AgileThemeTools\Service\ViewHelper\AgileSearchHelperFactory;
  use AgileThemeTools\Service\ViewHelper\ItemSetFacetHandlerFactory;
  use AgileThemeTools\Service\ViewHelper\SiteHelperFactory;
  use AgileThemeTools\Service\ControllerPlugin\SiteThemeSettingsControllerPluginFactory;
  use AgileThemeTools\Media\FileRenderer\TextRenderer;
  use AgileThemeTools\Media\FileRenderer\VttRenderer;
  use AgileThemeTools\Service\ViewHelper\SiteAssetUrlFactory;
  use AgileThemeTools\View\Helper\AgileItemSetsTreeHandler;
  use AgileThemeTools\View\Helper\ItemSetFacetHandler;
  use AgileThemeTools\View\Helper\AgileSearchHelper;
  use AgileThemeTools\View\Helper\SitePartial;
  use AgileThemeTools\Media\FileRenderer\CircularAudioRenderer;
  use AgileThemeTools\Site\ResourcePageBlockLayout\ItemHeader;
  use AgileThemeTools\Site\ResourcePageBlockLayout\ItemHeading;
  use AgileThemeTools\Site\ResourcePageBlockLayout\ItemSummary;
use AgileThemeTools\Site\ResourcePageBlockLayout\ItemFullMetadata;
  use AgileThemeTools\Site\ResourcePageBlockLayout\ItemMedia;

  return array(
      'view_manager' => [
          'template_path_stack' => [
              dirname(__DIR__) . '/view',
          ],
      ],
      'api_assets' => [
          'allowed_media_types' => [
              'image/jpeg',
              'image/png',
              'image/gif',
              'image/webp',
              'application/pdf',
              'text/vtt',
              'text/plain'
          ],
          'allowed_extensions' => [
              'jpeg',
              'jpg',
              'png',
              'gif',
              'webp',
              'pdf',
              'vtt',
              'txt'
          ],
      ],
      // The Agile search form, offered in an Advanced Search configuration's Form select (Search\AgileFormAdapter;
      // see docs/SEARCH.md). Advanced Search reads this registry; without that module it is simply unused.
      'advanced_search_form_adapters' => [
        'factories' => [
          'agile' => Service\Search\AgileFormAdapterFactory::class,
        ],
      ],
      // Display modes: alternative presentations of a resource or page (DisplayMode\Manager; see docs/CARDS.md).
      'display_modes' => [
        'invokables' => [
          'card' => DisplayMode\Card::class,
        ],
      ],
      'block_layouts' => [
        'invokables' => [
        'pageTitle' => Site\BlockLayout\PageTitle::class,
        // Replaces Omeka's IIIF presentation block: Mirador, else Universal Viewer, else the core viewer (D2).
        'iiifPresentation' => Site\BlockLayout\IiifPresentation::class,
      ],
      'factories' => [
        // 'glossary' => Service\BlockLayout\GlossaryItemFactory::class,
        'excerpt' => Service\BlockLayout\ExcerptFactory::class,
        'pageSummary' => Service\BlockLayout\PageSummaryFactory::class,
        'regionalHtml' => Service\BlockLayout\RegionalHtmlFactory::class,
        'regionalTitle' => Service\BlockLayout\RegionalTitleFactory::class,
        'byline' => Service\BlockLayout\BylineFactory::class,
        'contributorList' => Service\BlockLayout\ContributorListFactory::class,
        'representativeImage' => Service\BlockLayout\RepresentativeImageFactory::class,
        'slideshow' => Service\BlockLayout\SlideshowFactory::class,
        // Poster: withheld from the generic distribution pending a redesign (design review 2026-10-19, E4). The class
        // and its factory stay; existing poster blocks render through Omeka's Fallback layout (nothing in public).
        // 'poster' => Service\BlockLayout\PosterFactory::class,
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
      'controllers' => [
        'invokables' => [
        ],
        'factories' => [
          'AgileThemeTools\Controller\SiteAdmin\SiteTheming' => Service\Controller\SiteAdmin\SiteThemingControllerFactory::class
        ],
      ],
      'controller_plugins' => [
        'factories' => [
          'siteThemeSettings' => SiteThemeSettingsControllerPluginFactory::class,
        ]
      ],
      'entity_manager' => [
        'mapping_classes_paths' => [
          dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
          dirname(__DIR__) . '/data/doctrine-proxies',
        ],
      ],
      'file_renderers' => [
        'invokables' => [
          //'fallback' => Media\FileRenderer\FallbackRenderer::class,
          //'thumbnail' => Media\FileRenderer\ThumbnailRenderer::class,
          'audio' => CircularAudioRenderer::class,
          'vtt' => VttRenderer::class,
          'text' => TextRenderer::class
          //'video' => Media\FileRenderer\VideoRenderer::class,
        ],
        'aliases' => [
          'text/vtt' => 'vtt',
          'text/plain' => 'text',
        ],
      ],
      'form_elements' => [
          'invokables' => [
              'regionmenu' => Form\Element\RegionMenuSelect::class,
              Form\Element\PropertyList::class => Form\Element\PropertyList::class,
              Form\Element\CssTextarea::class => Form\Element\CssTextarea::class,
              Form\Element\ColourValue::class => Form\Element\ColourValue::class,
              Form\Element\FontStack::class => Form\Element\FontStack::class,
              'SectionListingTemplateSelect' => Form\Element\SectionListingTemplateSelect::class,
              'SlideViewerCompositionSelect' => Form\Element\SlideViewerCompositionSelect::class,
          ],
          'factories' => [
              'SectionsMenuSelect' => Service\Form\Element\SectionsMenuSelectFactory::class,
              'AgileThemeTools\Form\SiteAdmin\SiteThemeSettingsForm' => Service\Form\SiteAdmin\SiteThemeSettingsFormFactory::class,
              Form\SiteAdmin\ColoursAndFontsForm::class => Service\Form\SiteAdmin\ColoursAndFontsFormFactory::class,
          ]
      ],
      'resource_page_block_layouts' => [
        'invokables' => [
          'itemHeader' => ItemHeader::class,
          'itemMedia' => ItemMedia::class,
          'itemHeading' => ItemHeading::class,
          'itemSummary' => ItemSummary::class,
          'itemFullMetadata' => ItemFullMetadata::class,
        ],
      ],
      'service_manager' => [
              'factories' => [
                  'SectionManager' => Service\Controller\SectionManagerFactory::class,
                  'AgileSearchHandler' => AgileSearchHandlerFactory::class,
                  'AgileThemeTools\Settings\SiteThemeSettings' => SiteThemeSettingsFactory::class,
                  ThemeBuild\BuildManager::class => Service\ThemeBuild\BuildManagerFactory::class,
                  Dependency\DependencyAudit::class => Service\Dependency\DependencyAuditFactory::class,
                  Styling\PaletteCss::class => Service\Styling\PaletteCssFactory::class,
                  Styling\BasicStyles::class => Service\Styling\BasicStylesFactory::class,
                  DisplayMode\Manager::class => Service\DisplayModeManagerFactory::class,
                  Styling\CustomCss::class => Service\Styling\CustomCssFactory::class,
                  Styling\LogoAudit::class => Service\Styling\LogoAuditFactory::class,
                  Styling\ThemeFonts::class => Service\Styling\ThemeFontsFactory::class,
              ],
          ],
      'view_helpers' => [
         'aliases' => [
           'agile_search_helper' => AgileSearchHelper::class,
           'agileSearchHelper' => AgileSearchHelper::class,
           'itemSetFacetHandler' => ItemSetFacetHandler::class,
           'agileItemSetsTreeHandler' => AgileItemSetsTreeHandler::class,
           'themeIdentity' => View\Helper\ThemeIdentity::class,
         ],
         'factories' => [
           AgileSearchHelper::class => AgileSearchHelperFactory::class,
           ItemSetFacetHandler::class => ItemSetFacetHandlerFactory::class,
           AgileItemSetsTreeHandler::class => AgileItemSetsTreeHandlerFactory::class,
           'siteAssetUrl' => SiteAssetUrlFactory::class,
           // Viewers for a manifest URL (the IIIF presentation block); ask only when the viewer module's class exists.
           'miradorManifest' => Service\ViewHelper\ViewerManifestFactory::class,
           'universalViewerManifest' => Service\ViewHelper\ViewerManifestFactory::class,
           'siteHelper' => SiteHelperFactory::class,
           'formPropertyList' => Service\Form\View\Helper\FormPropertyListFactory::class,
           'formCssTextarea' => Service\Form\View\Helper\FormCssTextareaFactory::class,
           'customCss' => Service\ViewHelper\CustomCssHelperFactory::class,
           'formFontStack' => Service\Form\View\Helper\FormFontStackFactory::class,
           'themeFonts' => Service\ViewHelper\ThemeFontsHelperFactory::class,
           'propertyTerms' => Service\ViewHelper\PropertyTermsFactory::class,
           'themeBuildPanel' => Service\ViewHelper\ThemeBuildPanelFactory::class,
           'themeLogoAudit' => Service\ViewHelper\ThemeLogoAuditFactory::class,
           'agileDependencies' => Service\ViewHelper\AgileDependenciesFactory::class,
           'agileBasicStyles' => Service\ViewHelper\AgileBasicStylesFactory::class,
           'card' => Service\ViewHelper\DisplayModeHelpersFactory::class,
           'displayMode' => Service\ViewHelper\DisplayModeHelpersFactory::class,
           View\Helper\ThemeIdentity::class => \Laminas\ServiceManager\Factory\InvokableFactory::class,

         ],
        'delegators' => [
            'Laminas\Form\View\Helper\FormElement' => [
                Service\Delegator\FormElementDelegatorFactory::class,
            ],
        ],
        'invokables' => [
           'formLayout' => View\Helper\FormLayout::class,
            'agileFormCollectionElementGroups' => AgileFormCollectionElementGroups::class,
            'ckEditor' => View\Helper\CustomCKEditorConfig::class,

            'dateHandler' => View\Helper\DateHandler::class,
            'valueDisplay' => View\Helper\ValueDisplay::class,
            'pageSlug' => View\Helper\PageSlug::class,
            'textSummary' => View\Helper\TextSummary::class,
            'sitePartial' => SitePartial::class,
            'formColourValue' => Form\View\Helper\FormColourValue::class,
        ],
     ],
    'router' => [
      'routes' => [
        'admin' => [
          'child_routes' => [
            'site' => [
              'child_routes' => [
                'slug' => [
                  'child_routes' => [
                    // Colours and fonts: applied on save, no build (see Styling\PaletteCss).
                    'theme-colours' => [
                      'type' => \Laminas\Router\Http\Literal::class,
                      'options' => [
                        'route' => '/theme-colours',
                        'defaults' => [
                          '__NAMESPACE__' => 'AgileThemeTools\Controller\SiteAdmin',
                          '__ADMIN__' => true,
                          'controller' => 'SiteTheming',
                          'action' => 'colours',
                        ],
                      ],
                    ],
                    'theme-styling' => [
              'type' => \Laminas\Router\Http\Literal::class,
              'options' => [
                'route' => '/theme-styling',
                'defaults' => [
                  '__NAMESPACE__' => 'AgileThemeTools\Controller\SiteAdmin',
                  '__ADMIN__' => true,
                  'controller' => 'SiteTheming',
                  'action' => 'edit',
                ],
              ],
              'may_terminate' => true,
              'child_routes' => [
                // Site theme build (see ThemeBuild\BuildManager). Literal routes take priority over the
                // controller/action segment route below.
                'build' => [
                  'type' => \Laminas\Router\Http\Literal::class,
                  'priority' => 10,
                  'options' => [
                    'route' => '/build',
                    'defaults' => [
                      '__NAMESPACE__' => 'AgileThemeTools\Controller\SiteAdmin',
                      '__ADMIN__' => true,
                      'controller' => 'SiteTheming',
                      'action' => 'build',
                    ],
                  ],
                ],
                'build-all' => [
                  'type' => \Laminas\Router\Http\Literal::class,
                  'priority' => 10,
                  'options' => [
                    'route' => '/build-all',
                    'defaults' => [
                      '__NAMESPACE__' => 'AgileThemeTools\Controller\SiteAdmin',
                      '__ADMIN__' => true,
                      'controller' => 'SiteTheming',
                      'action' => 'build-all',
                    ],
                  ],
                ],
                'status' => [
                  'type' => \Laminas\Router\Http\Literal::class,
                  'priority' => 10,
                  'options' => [
                    'route' => '/status',
                    'defaults' => [
                      '__NAMESPACE__' => 'AgileThemeTools\Controller\SiteAdmin',
                      '__ADMIN__' => true,
                      'controller' => 'SiteTheming',
                      'action' => 'status',
                    ],
                  ],
                ],
                'default' => [
                  'type' => \Laminas\Router\Http\Segment::class,
                  'options' => [
                    'route' => '/:controller[/:action]',
                    'constraints' => [
                      'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                      'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                      'controller' => 'CheckAndFix',
                      'action' => 'index',
                    ],
                  ],
                ],
                'file-manager' => [
                  'type' => \Laminas\Router\Http\Segment::class,
                  'options' => [
                    'route' => '/file-manager[/:action]',
                    'constraints' => [
                      'action' => 'browse',
                    ],
                    'defaults' => [
                      '__NAMESPACE__' => 'EasyAdmin\Controller\Admin',
                      'controller' => 'FileManager',
                      'action' => 'browse|delete-confirm|delete',
                    ],
                  ],
                ],
                'upload' => [
                  'type' => \Laminas\Router\Http\Segment::class,
                  'options' => [
                    'route' => '/upload[/:action]',
                    'constraints' => [
                      'action' => 'index|upload',
                    ],
                    'defaults' => [
                      '__NAMESPACE__' => 'EasyAdmin\Controller',
                      'controller' => 'Upload',
                      'action' => 'index',
                    ],
                  ],
                ],
              ],
            ],
                  ]
                ]
              ]
            ]
          ],
        ],
      ],
    ]
  );

