<?php
  namespace AgileThemeTools\Form\SiteAdmin;
  require 'vendor/autoload.php';


  use AdvancedSearch\FormAdapter\Manager as SearchFormAdapterManager;
  use Laminas\EventManager\Event;
  use Laminas\EventManager\EventManagerAwareTrait;
  use Laminas\Form\Element;
  use Laminas\Form\Element\Hidden;
  use Laminas\Form\Element\Submit;
  use Laminas\Form\Form;
  use Laminas\Form\Element\Text;
  use AgileThemeTools\Settings\SiteThemeSettings;
  use Laminas\Form\View\Helper\FormReset;
  use Omeka\Api\Manager as ApiManager;
  use Omeka\Api\Representation\SiteRepresentation;
  use Symfony\Component\Yaml\Yaml;

  class SiteThemeSettingsForm extends Form {

    use EventManagerAwareTrait;

    /**
     * @var ApiManager
     */
    protected $apiManager;

    /**
     * @var SiteRepresentation|null $site
     */

    protected $site;
    /**
     * @var SearchFormAdapterManager
     */
    protected $formAdapterManager;

    /*
     * @var Omeka\Site\Theme\Manager $themeManager
     */
    protected $themeManager;

    /**
     * @var SiteThemeSettings $siteThemeSettings d
     */
    protected $siteThemeSettings;

    /**
     * @var \Laminas\Log\Logger $logger
     */
    protected $logger;

    /**
     * @var string $currentSiteSlug
     */

    protected $currentSiteSlug = '';

    /**
     * @var string $currentSiteId
     */
    protected $currentSiteId = '';

    /**
     * @return void
     */

    public function init(): void
    {
      $settings = $this->getSiteThemeSettings();
      $settings->setTargetId($this->currentSiteId ?? "0");

      $id = 'site-theming-config-form'. ($this->currentSiteSlug ? "-{$this->currentSiteSlug}" : '');

      parent::__construct($id);

      $this->setAttribute('id', $id);
      $this->setAttribute('method', 'POST');

      $unexposedValues = $this->getSiteIdentificationValues() + $this->getDefaultValues();
      $exposedValues = $this->exposedValues();

      $elementGroups = [];

      foreach ($exposedValues as $elementGroupId => $elementGroupValues) {
        $elementGroups[$elementGroupId] = $elementGroupValues['label'];
      }


      $this->setOption('element_groups', $elementGroups);


      foreach($exposedValues as $elementGroup => $values) {
        foreach($values['values'] as $id => $info) {
          $description = is_array($info) ? ($info['description'] ?? '') : '';
          if (is_array($info) && !empty($info['font'])) {
            // A font role: source, family, fallback and preview (see FontStack). The source travels in a hidden
            // companion field saved with the other styling settings.
            $field = new \AgileThemeTools\Form\Element\FontStack($id);
            $field->setOption('role', $id);
            $source = new Hidden($id . \AgileThemeTools\Styling\ThemeFonts::SOURCE_SUFFIX);
            $source->setAttribute('id', $id . \AgileThemeTools\Styling\ThemeFonts::SOURCE_SUFFIX);
            $source->setOption('element_group', $elementGroup);
            $this->add($source);
            unset($unexposedValues[$source->getName()]);
          } elseif (is_array($info) && !empty($info['colour'])) {
            // A colour: swatch, picker and, for a foreground/background pair, a contrast readout (see ColourValue).
            $field = new \AgileThemeTools\Form\Element\ColourValue($id);
            if (!empty($info['contrast_with'])) {
              $field->setOption('contrast_with', $info['contrast_with']);
              $field->setOption('contrast_role', $info['contrast_role'] ?? 'fg');
              $description = trim($description . ' ' . (($info['contrast_role'] ?? 'fg') === 'bg'
                ? "Contrast is checked against the text shown on it ({$info['contrast_with']})."
                : "Contrast is checked against its background ({$info['contrast_with']}). Aim for AA (4.5:1) or better."));
            }
          } else {
            $field = new Text($id);
          }
          $field->setLabel((is_array($info) ? $info['label'] : $info) ." ($". $id .")");
          $field->setOption('element_group',$elementGroup);
          if (!empty($description)) {
            $field->setOption('info',$description);
          }
          $field->setAttribute('id',$id);
          $field->setValue($this->getDefaultValue($id));
          $this->add($field);
          unset($unexposedValues[$id]);
        }
      }

      foreach($unexposedValues as $id => $value) {
        $field = new Hidden($id);
        $field->setValue($value);
        $this->add($field);
      }

      // The site's custom CSS. A site setting (see Styling\CustomCss), also editable on the site's Edit page;
      // shown here because this is where theming happens. The controller loads and saves it separately.
      $elementGroups['custom_css'] = 'Custom CSS'; // @translate
      $this->setOption('element_groups', $elementGroups);
      $this->add([
        'name' => \AgileThemeTools\Styling\CustomCss::SITE_SETTING,
        'type' => \AgileThemeTools\Form\Element\CssTextarea::class,
        'options' => [
          'element_group' => 'custom_css',
          'label' => 'Custom CSS for this site', // @translate
          'info' => 'Plain CSS loaded last on this site\'s pages, after the built theme styles and the installation-wide custom CSS (Settings > Theme styling and builds). It applies as soon as it is saved; no build is needed. The same setting is on the site\'s Edit page under Theme styling.', // @translate
          'site_id' => $this->currentSiteId ?: null,
        ],
        'attributes' => [
          'id' => \AgileThemeTools\Styling\CustomCss::SITE_SETTING,
          'placeholder' => "/* Example */\n#main-menu {\n    background-color: var(--colour--1);\n}",
        ],
      ]);
      /*

      foreach (range(1,9) as $colourNum) {
        $colourKey ="clr{$colourNum}";
        $colourField = new Text($colourKey);
        $colourField->setLabel("Colour {$colourNum}");
        $colourField->setAttribute('id',$colourKey);
        if (!empty($this->currentSiteId)) {
          $colourField->setValue($settings->get('theme_settings', true)['values'][$colourKey] ?? $this->getDefaultValue($colourKey));
        }
        $this->add($colourField);
      }*/

      $this->add([
        'name' => 'submit',
        'type' => Submit::class,
        'attributes' => [
          'value' => 'Save styling settings',
          'id'    => 'submit',
        ],
      ]);

      $reset = new Element\Button('reset');
      $reset->setLabel('Restore default values');
      $reset->setAttributes([
        'type' => 'submit',
        'id' => 'reset',
      ]);
      $reset->setOption('info','Resets form values to those in the main configuration file. All current form values will be lost. Saving does not rebuild the site: use the Site theme build panel above after saving.');
      $reset->setValue('reset');

      $this->add($reset);

      parent::init();
    }

    public function setApiManager(ApiManager $apiManager): self
    {
      $this->apiManager = $apiManager;
      return $this;
    }

    public function setThemeManager($themeManager): self {
      $this->themeManager = $themeManager;
      return $this;
    }

    public function setFormAdapterManager(SearchFormAdapterManager $formAdapterManager): self
    {
      $this->formAdapterManager = $formAdapterManager;
      return $this;
    }

    public function setCurrentSite(SiteRepresentation|null $siteRepresentation = null ):self {
      $this->site = $siteRepresentation;
      $this->currentSiteSlug = $siteRepresentation ? $siteRepresentation->slug() : '';
      $this->currentSiteId = $siteRepresentation ? $siteRepresentation->id() : '';
      return $this;
    }
    public function setSiteThemeSettings(SiteThemeSettings $siteThemeSettings): self {
      $this->siteThemeSettings = $siteThemeSettings;

      return $this;
    }

    public function setLogger(\Laminas\Log\Logger $logger):self {
      $this->logger = $logger;
      return $this;
    }

    public function getCurrentSiteId():int|false {
      return $this->currentSiteId ?? false;
    }

    public function getCurrentSiteSlug():string|false {
      return $this->currentSiteSlug ?? false;
    }

    public function getElementKeys($csrf=false,$submit=false) {
      $elements = $this->elements;
      if ($csrf === false && array_key_exists('csrf',$elements)) {
        unset ($elements['csrf']);
      }
      if (array_key_exists('reset',$elements)) {
        unset ($elements['reset']);
      }
      if ($submit === false && array_key_exists('submit',$elements)) {
        unset ($elements['submit']);
      }
      return array_keys($elements);
    }

    public function getDefaultKeys($site=null) {
      return array_keys($this->getDefaultValues($site));
    }

    /**
     * @return SiteThemeSettings
     * @description Retrieves default values from
     */
    public function getSiteThemeSettings() {
      return $this->siteThemeSettings;
    }
    public function getDefaultValues($site=null): array {

      $site = $this->site ?? $site;

      static $cache = null;

      if ($cache === null) {
        if (!$site) {
          $this->logger->warn('Cannot resolve the current site to retrieve default values.');
          return [];
        }

        $currentTheme = $this->themeManager->getTheme($site->theme());

        // The theme's default configuration file: _00_main_configuration.yml unless theme.ini declares another.
        $defaultConfigPath = \AgileThemeTools\Theme\ThemeProfile::of($currentTheme)->configFile();

        if ($defaultConfigPath) {
          $cache = Yaml::parse(file_get_contents($defaultConfigPath));
        } else {
          $this->logger->warn('Cannot find the theme’s default style configuration file at [file] .',['file'=> $defaultConfigPath]);
        }
      }

      return $cache;
    }

    public function getSiteIdentificationValues():array {
      if (!$this->site) {
        $this->logger->warn('Cannot resolve the current site to retrieve site values.');
        return [];
      }
      return [
        'slug' => $this->site->slug(),
        'site_id' => $this->site->id()
      ];

    }



    public function getDefaultValue($key): string|null  {
      $defaults = $this->getDefaultValues();
      return array_key_exists($key,$defaults) ? $defaults[$key] : null;
    }

    protected function exposedValues() {
      // A value may be a label string, or an array with label, description, and for colours: colour => true and an
      // optional contrast pair (contrast_with = the other field; contrast_role = 'fg' when this field is text shown
      // on the other, 'bg' when it is the background under the other).
      $colour = function (string $label, ?string $with = null, string $role = 'fg', string $description = ''): array {
        $info = ['label' => $label, 'colour' => true];
        if ($description !== '') {
          $info['description'] = $description;
        }
        if ($with !== null) {
          $info['contrast_with'] = $with;
          $info['contrast_role'] = $role;
        }
        return $info;
      };

      return ([
        'general' => [
          'label' => 'Core theme system values',
          'values' => [
            'scale' => ['label' => 'Modular Scale', 'description' => 'Advanced value – be careful! Determines the proportions of all fonts and spacing in the site. Must be a real number between 1 and 2. 1.2 is tight, 1.5 is loose. See https://www.modularscale.com. A preview of the resulting sizes appears below this group.'],
            'base_line_height' => ['label' => 'Line Height', 'description' => 'General spacing for text. Must be a real number. 1 is tight, 1.5 is standard, 2 is loose.'],
            'max_width' => ['label' => 'Maximum width', 'description' => 'The maximum width of any page content in pixels.'],
            'legible_inset_width' => ['label' => 'Legible content width', 'description' => 'The width of body text areas, in pixels. Legibility increases with narrower text content widths.'],
          ]
        ],
        'structure' => [
          'label' => 'Structural site elements',
          'values' => [
            'masthead_height' => ['label' => 'Site Header height', 'description' => 'The height of the site header and main menu area. Use pixel values here. If your logo seems small, try increasing the site header height.']
          ]
        ],
        'typography' => [
          'label' => 'Typography',
          'values' => [
            'basetypesize' => ['label' => 'Base Type Size', 'description' => 'REM value. Defines the body text size. All other sizes are relative to this. 1rem is 16px and standard. 0.9rem is small, and 1.1rem is large.'],
            'basefont' => ['label' => 'Main text font', 'font' => true, 'description' => 'Body text and most of the site. Choose a Google font (loaded automatically), a font bundled with the theme, or a system font. Changing a font needs a theme build.'],
            'headingfont' => ['label' => 'Heading font', 'font' => true, 'description' => 'Headings and titles.'],
            'altfont' => ['label' => 'Alternative font', 'font' => true, 'description' => 'Special-use text such as quotations and decks. Often the same as the heading font.'],
            'labelfont' => ['label' => 'Label and interface font', 'font' => true, 'description' => 'Labels, buttons, menus and other interface text; the most used font on the site.'],
            'sizefactors' => ['label' => 'Size factors', 'description' => 'Advanced value in SASS:map format. Changes the proportion of headings as determined by the modular scale. ']
          ]
        ],
        'palette' => [
          'label' => 'Palette',
          'description' => 'Set your primary site palette.',
          'values' => [
            'clr1' => $colour('Colour 1'),
            'clr2' => $colour('Colour 2'),
            'clr3' => $colour('Colour 3'),
            'clr4' => $colour('Colour 4'),
            'clr5' => $colour('Colour 5', null, 'fg', 'Extended palette. Optional.'),
            'clr6' => $colour('Colour 6', null, 'fg', 'Extended palette. Optional.'),
            'clr7' => $colour('Colour 7', null, 'fg', 'Extended palette. Optional.'),
            'clr8' => $colour('Colour 8', null, 'fg', 'Extended palette. Optional.'),
            'clr9' => $colour('Colour 9', null, 'fg', 'Extended palette. Optional.'),
            'black' => $colour('Blackpoint', 'white', 'fg', 'The darkest colour of the site, used for body text and dark backgrounds. Neutrals are mixed from the blackpoint and whitepoint.'),
            'white' => $colour('Whitepoint', 'black', 'bg', 'The lightest colour of the site, used for the page background and text on dark backgrounds.'),
            ],
          ],
        'targeted_palette' => [
          'label' => 'Targeted colours',
          'description' => 'Key element colours',
          'values' => [
            'bgcolor' => $colour('Site background colour', 'link_colour', 'bg'),
            'impact' => $colour('High impact text colour', 'bgcolor'),
            'cta' => $colour('Call to action colour'),
            'link_colour' => $colour('Link colour', 'bgcolor'),
            'link_colour_active' => $colour('Link colour (active)', 'bgcolor'),
            'link_colour_reversed' => $colour('Link colour reversed (against dark backgrounds)', 'footer_bg'),
            'link_colour_active_reversed' => $colour('Link colour reversed (active)', 'footer_bg'),
          ]
        ],
        'site_elements' => [
          'label' => 'Key Site Elements',
          'values' => [
            'footer_bg' => $colour('Footer background colour', 'link_colour_reversed', 'bg'),
            'menu_background' => $colour('Menu background colour', 'menu_color', 'bg'),
            'menu_color' => $colour('Menu text colour', 'menu_background'),
            'welcome_block_bg' => $colour('Welcome Block Background', 'welcome_block_color', 'bg', 'Background of the homepage splash welcome block.'),
            'welcome_block_color' => $colour('Welcome Block Colour', 'welcome_block_bg', 'fg', 'Text colour of the homepage splash welcome block.'),
            'circular_audio_color' => $colour('Circular audio colour', 'circular_audio_background'),
            'circular_audio_background' => $colour('Circular audio background', 'circular_audio_caption_color', 'bg'),
            'circular_audio_caption_color' => $colour('Circular audio caption colour', 'circular_audio_background'),
            'transcript_highlight' => $colour('Audio transcript highlight colour', 'black', 'bg'),
            'transcript_hover' => $colour('Audio transcript hover colour', 'black', 'bg'),
          ]
        ],
        'ui' => [
          'label' => 'User Interface',
          'values' => [
            'ui_element_height' => ['label' => 'UI Element Height', 'description' => 'Standard height for most UI elements, including buttons.'],
            'ui_compactheight' => ['label' => 'UI Compact Element Height', 'description' => 'Height of compact UI elements, including small buttons'],
            'ui_largeheight' => ['label' => 'UI Large Element Height', 'description' => 'Height of large UI elements, including large buttons'],
            'ui_border_radius' => ['label' => 'UI Border Radius', 'description' => 'Corner size of UI elements, including buttons.'],
            'ui_border_color' => $colour('UI Border Colour', 'bgcolor', 'fg', 'Colour of UI borders. Standard is a light neutral, via the neutral(value) theme function.'),
            'btn_border_width' => ['label' => 'Button Border Width', 'description' => 'In pixels or set to 0.'],
            'btn_color' => $colour('Button Text Colour', 'btn_bg_color', 'fg', 'For standard buttons.'),
            'btn_bg_color' => $colour('Button Background Colour', 'btn_color', 'bg', 'For standard buttons.'),
            'btn_border_color' => $colour('Button Border Colour', null, 'fg', 'For standard buttons. Ignored if button borders are 0px.'),
            'btn_hover_color' => $colour('Button Text Hover Colour', 'btn_hover_bg_color', 'fg', 'For standard buttons on user hover.'),
            'btn_hover_border_color' => $colour('Button Border Hover Colour', null, 'fg', 'For standard buttons on user hover.'),
            'btn_hover_bg_color' => $colour('Button Background Hover Colour', 'btn_hover_color', 'bg', 'For standard buttons on user hover.'),
            'btn_submit_border_width' => ['label' => 'Submit Button Border Width', 'description' => 'In pixels or set to 0.'],
            'btn_submit_color' => $colour('Submit Button Text Colour', 'btn_submit_bg_color', 'fg', 'For submit buttons.'),
            'btn_submit_bg_color' => $colour('Submit Button Background Colour', 'btn_submit_color', 'bg', 'For submit buttons.'),
            'btn_submit_border_color' => $colour('Submit Button Border Colour', null, 'fg', 'For submit buttons. Ignored if button borders are 0px.'),
            'btn_submit_hover_color' => $colour('Submit Button Text Hover Colour', 'btn_submit_hover_bg_color', 'fg', 'For submit buttons.'),
            'btn_submit_hover_border_color' => $colour('Submit Button Border Hover Colour', null, 'fg', 'For submit buttons.'),
            'btn_submit_hover_bg_color' => $colour('Submit Button Background Hover Colour', 'btn_submit_hover_color', 'bg', 'For submit buttons.'),
            ],
          ],
        'advanced' => [
          'label' => 'Advanced Settings',
          'values' => [
          'source_imgs' => [
            'label' => 'Alternative image directory',
            'description' => "Specify a directory in the theme folder that contains an alternative set of images.\n\n
                Note that the top level of the image folder must have the following folders, each containing subfolders or images of their corresponding format: /svg, /png, /jpg, /webp.\n\n
                This allows the theme build system to properly process each type of image file. See the base source/img folder as an example."],
          'source_dir' => [
            'label' => 'Alternative source directory',
            'description' => 'Specify a directory in the theme folder that contains the source assets (fonts, images, scripts, SASS files) for this site.'
            ]
          ]
        ]
      ]);
    }



  }
