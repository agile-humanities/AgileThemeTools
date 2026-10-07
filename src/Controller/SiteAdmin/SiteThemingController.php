<?php

namespace AgileThemeTools\Controller\SiteAdmin;

use AgileThemeTools\Form\View\Helper\AgileFormCollectionElementGroups;
use AgileThemeTools\Job\BuildSiteTheme;
use AgileThemeTools\ThemeBuild\BuildManager;
use Laminas\Form\Form;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use AgileThemeTools\Form\SiteAdmin\SiteThemeSettingsForm;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\Manager as LinkManager;
use Omeka\Site\Navigation\Translator;
use Omeka\Site\ResourcePageBlockLayout\Manager as ResourcePageBlockLayoutManager;
use Omeka\Site\Theme\Manager as ThemeManager;
use Omeka\Site\Theme\Theme;

/**
 * @class SiteThemingController
 * Manages the Site Theme Settings form.
 * Extended by the SiteThemeSettingsControllerPlugin to provide siteThemeSettings()->set & siteThemeSettings()->get
 * methods.
 *
 * The edit form is the default for this page.
 */


class SiteThemingController extends AbstractActionController {
  /** @var ThemeManager $themeManager */
  protected $themeManager;

  /** @var SiteRepresentation|null $site */
  protected $site;

  /** @var BuildManager $builder */
  protected $builder;

  /** @var \AgileThemeTools\Styling\ThemeFonts $fonts */
  protected $fonts;

  public function __construct(ThemeManager $themeManager, SiteRepresentation|null $site, BuildManager $builder, \AgileThemeTools\Styling\ThemeFonts $fonts) {
      $this->themeManager = $themeManager;
      $this->site = $site;
      $this->builder = $builder;
      $this->fonts = $fonts;
      //$this->navLinks = $navLinks;
      //$this->navTranslator = $navTranslator;
      //$this->resourcePageBlockLayoutManager = $resourcePageBlockLayoutManager;
    }
  public function editAction() {
    // $site = $this->currentSite();
    // $settings = $this->siteSettings();
    // $theme = $this->getCurrentTheme();
    // $config = $theme->getConfigSpec();


    /** @var SiteThemeSettingsForm $form */
    $form = $this->getForm(SiteThemeSettingsForm::class);
    $form->setAttribute('action', $this->url()->fromRoute(null, [], true));


    // Handle user-supplied form values
    if ($this->getRequest()->isPost()) {

        if(array_key_exists('reset',$this->getRequest()->getPost()->toArray())) {
          $this->resetFormValues($form);
        } else {
          $this->updateThemeSettings($form);
        }

    } else {
      // Otherwise populate the form with current values.
      $formElements = $form->getElements();
      unset($formElements['csrf']); // The CSRF token is managed elsewhere

      // Styling values saved for another base theme are not this theme's: the form then shows the theme defaults.
      $currentTheme = $this->getCurrentTheme();
      $stylingApplies = !$currentTheme || $this->builder->stylingApplies($currentTheme, $this->currentSite());

      // Set individual values in the site_theme_settings table
      foreach (array_keys($formElements) as $id) {
        if ($id === \AgileThemeTools\Styling\CustomCss::SITE_SETTING) {
          continue; // A site setting, not a styling setting.
        }
        $value = $stylingApplies ? $this->siteThemeSettings()->get($id) : null;
        if ($value) {
          $form->get($id)->setValue($value);
        } // else use the default value as determined by _00_main_configuration in theme.
      }
      $form->get(\AgileThemeTools\Styling\CustomCss::SITE_SETTING)->setValue($this->siteSettings()->get(\AgileThemeTools\Styling\CustomCss::SITE_SETTING));
    }

    $view = new ViewModel();
    $view->setVariable('form',$form);
    $view->setVariable('site', $this->currentSite());
    $view->setVariable('theme', $this->getCurrentTheme() ?: null);
    // Set when the site's saved styling values belong to another base theme than the current theme's.
    $theme = $this->getCurrentTheme();
    $view->setVariable('otherStylingBase', $theme && !$this->builder->stylingApplies($theme, $this->currentSite())
        ? $this->builder->getStylingBase($this->currentSite())
        : null);
    $view->setTemplate('site-admin/site-theming/edit');

    /*
      $searchConfig = $this->api()
            ->update('search_configs', $id, $formData, [], ['isPartial' => true])
            ->getContent();

        $this->messenger()->addSuccess(new PsrMessage(
            'Search page "{name}" saved.', // @translate
            ['name' => $searchConfig->name()]
        ));

        $this->manageSearchConfigSettings(
            $searchConfig,
            $formData['manage_config_availability'] ?: [],
            $formData['manage_config_default'] ?: []
        );

        return $this->redirect()->toRoute('admin/search-manager');
     */

    return $view;
    //return parent::indexAction();

  }

  /**
   * Colours and fonts: the palette and font roles, saved to the same styling settings as the full form and applied
   * at once through the palette stylesheet (Styling\PaletteCss). No build.
   */
  public function coloursAction() {
    $site = $this->currentSite();
    $theme = $this->themeManager->getTheme($site->theme());
    $profile = $theme ? \AgileThemeTools\Theme\ThemeProfile::of($theme) : null;
    if (!$theme || !$profile->isAgile()) {
      $this->messenger()->addError('Colours and fonts can only be set for an Agile theme.'); // @translate
      return $this->redirect()->toRoute('admin/site/slug/action', ['site-slug' => $site->slug(), 'action' => 'theme']);
    }
    $palette = $this->builder->getPaletteCss();
    if (!$palette->isVariableDriven($site)) {
      $this->messenger()->addError($profile->supportsSiteBuilds()
        ? 'This theme\'s stylesheet has its colours and fonts compiled in, so they cannot be changed without a build: use "Configure theme styles". A stylesheet that takes variables is made with "gulp build --variables".' // @translate
        : 'This theme\'s stylesheet has its colours and fonts compiled in; they cannot be changed from here.'); // @translate
      return $this->redirect()->toRoute('admin/site/slug/action', ['site-slug' => $site->slug(), 'action' => 'theme']);
    }
    try {
      $inputs = $palette->inputs($theme);
    } catch (\RuntimeException $e) {
      $this->messenger()->addError($e->getMessage());
      return $this->redirect()->toRoute('admin/site/slug/action', ['site-slug' => $site->slug(), 'action' => 'theme']);
    }
    $current = $palette->values($theme, $site);
    foreach ($inputs as $key => &$input) {
      $input['default'] = $current[$key];
    }
    unset($input);

    $overrides = $palette->overrides($theme, $site);
    /** @var \AgileThemeTools\Form\SiteAdmin\ColoursAndFontsForm $form */
    $form = $this->getForm(\AgileThemeTools\Form\SiteAdmin\ColoursAndFontsForm::class, ['inputs' => $inputs, 'overrides' => $overrides]);
    $form->setAttribute('action', $this->url()->fromRoute(null, [], true));

    if ($this->getRequest()->isPost()) {
      $form->setData($this->params()->fromPost());
      if ($form->isValid()) {
        $data = $form->getData();
        foreach ($inputs as $key => $input) {
          $value = (string) ($data[$key] ?? '');
          $usable = $input['kind'] === 'colour' ? \AgileThemeTools\Styling\PaletteCss::isColour($value) : \AgileThemeTools\Styling\PaletteCss::isFontList($value);
          if (!$usable) {
            $this->messenger()->addWarning(sprintf($this->translate('"%1$s" is not a colour or font list that can be applied directly; %2$s was left unchanged.'), $value, $key));
            continue;
          }
          $this->siteThemeSettings()->set($key, $value);
          if ($input['kind'] === 'font' && isset($data[$key . \AgileThemeTools\Styling\ThemeFonts::SOURCE_SUFFIX])) {
            $this->siteThemeSettings()->set($key . \AgileThemeTools\Styling\ThemeFonts::SOURCE_SUFFIX, (string) $data[$key . \AgileThemeTools\Styling\ThemeFonts::SOURCE_SUFFIX]);
          }
        }
        // Brand-approved tints for the derived roles: a plain colour replaces the derivation, empty restores it.
        foreach ($form->overrideKeys() as $key) {
          $value = trim((string) ($data[$key] ?? ''));
          if ($value === '') {
            $this->siteThemeSettings()->delete($key);
          } elseif (\AgileThemeTools\Styling\PaletteCss::isColour($value)) {
            $this->siteThemeSettings()->set($key, $value);
          } else {
            $this->messenger()->addWarning(sprintf($this->translate('"%1$s" is not a plain colour; %2$s keeps its automatic value.'), $value, $key));
            $this->siteThemeSettings()->delete($key);
          }
        }
        $this->recordStylingBase();
        foreach (\AgileThemeTools\Styling\ColourRoles::failures($this->builder->getSiteConfig($theme, $site)) as $failure) {
          $this->messenger()->addWarning($this->translate('Colour roles: ') . $failure . '.');
        }
        $status = $this->builder->getStatus($theme, $site);
        if (!empty($status['built']) || !empty($status['legacy'])) {
          // A per-site build has the colours compiled in; the variables cannot change them until it is rebuilt.
          $this->messenger()->addSuccess('Colours and fonts saved. This site is served from its own theme build, so they take effect when the site theme is next built.'); // @translate
        } else {
          $this->messenger()->addSuccess('Colours and fonts saved. They apply to the site now.'); // @translate
        }
        foreach ($this->fonts->getGoogleFamilies($site) as $family) {
          $probe = $this->fonts->probeGoogleFamily($family, false);
          if (!$probe['ok']) {
            $this->messenger()->addError($probe['message']);
          } elseif ($probe['weights'] && count($probe['weights']) < 4) {
            $this->messenger()->addWarning($probe['message']);
          }
        }
        return $this->redirect()->toRoute(null, [], [], true);
      }
      $this->messenger()->addFormErrors($form);
    } else {
      // The font sources saved with the values.
      foreach ($form->fontKeys() as $key) {
        $source = $this->siteThemeSettings()->get($key . \AgileThemeTools\Styling\ThemeFonts::SOURCE_SUFFIX);
        if ($source && $form->has($key . \AgileThemeTools\Styling\ThemeFonts::SOURCE_SUFFIX)) {
          $form->get($key . \AgileThemeTools\Styling\ThemeFonts::SOURCE_SUFFIX)->setValue($source);
        }
      }
    }

    $view = new ViewModel([
      'form' => $form,
      'site' => $site,
      'theme' => $theme,
      'supportsSiteBuilds' => $profile->supportsSiteBuilds(),
      'siteHasBuild' => (function () use ($theme, $site) { $s = $this->builder->getStatus($theme, $site); return !empty($s['built']) || !empty($s['legacy']); })(),
      'otherStylingBase' => !$this->builder->stylingApplies($theme, $site) ? $this->builder->getStylingBase($site) : null,
      'colourConfig' => \AgileThemeTools\Styling\ColourRoles::colourConfig($this->builder->stylingApplies($theme, $site) ? $this->builder->getSiteConfig($theme, $site) : $this->builder->getDefaultConfig($theme)),
    ]);
    $view->setTemplate('site-admin/site-theming/colours');
    return $view;
  }

  /*
   * Resets form values in database. User must still run the build.
   */

  protected function resetFormValues(SiteThemeSettingsForm $form) {

    foreach($form->getSiteIdentificationValues() + $form->getDefaultValues() as $id => $value) {
      $this->siteThemeSettings()->set($id, $value);
    }
    $this->recordStylingBase();
  }

  /** The styling values just saved belong to the current theme's base (theme.ini agile_base). */
  protected function recordStylingBase(): void {
    $theme = $this->getCurrentTheme();
    if ($theme) {
      $this->builder->setStylingBase($theme, $this->currentSite());
    }
  }

  /*
   * Saves or updates theme settings to the database
   */

  protected function updateThemeSettings(SiteThemeSettingsForm $form):void {

    $form->setData($this->params()->fromPost());

    if ($form->isValid()) {
      $formData = $form->getData();
      unset($formData['csrf']);

      // The custom CSS is a site setting; everything else is a styling (SASS) setting.
      if (array_key_exists(\AgileThemeTools\Styling\CustomCss::SITE_SETTING, $formData)) {
        $this->siteSettings()->set(\AgileThemeTools\Styling\CustomCss::SITE_SETTING, (string) $formData[\AgileThemeTools\Styling\CustomCss::SITE_SETTING]);
        unset($formData[\AgileThemeTools\Styling\CustomCss::SITE_SETTING]);
      }

      foreach ($formData as $id => $value) {
        $this->siteThemeSettings()->set($id, $value);
      }
      $this->recordStylingBase();

      // Saving records the settings; building is a separate, visible step (the panel now reports "Rebuild needed").
      $this->messenger()->addSuccess('Theme styling settings saved. Build the site theme to apply them.'); // @translate

      // Check the Google fonts now, while the person who chose them is looking.
      foreach ($this->fonts->getGoogleFamilies($this->currentSite()) as $family) {
        $probe = $this->fonts->probeGoogleFamily($family, false);
        if (!$probe['ok']) {
          $this->messenger()->addError($probe['message']);
        } elseif ($probe['weights'] && count($probe['weights']) < 4) {
          $this->messenger()->addWarning($probe['message']);
        }
      }
    } else {
      $this->messenger()->addFormErrors($form);
    }

  }

  /**
   * Queues a build of this site's theme assets.
   */
  public function buildAction() {
    return $this->dispatchBuild([$this->currentSite()->id()]);
  }

  /**
   * Queues a build for every site that uses this theme with per-site styling on.
   */
  public function buildAllAction() {
    $theme = $this->getCurrentTheme();
    $sites = $theme ? $this->builder->getConfigurableSites($theme) : [];
    return $this->dispatchBuild(array_map(fn ($site) => $site->id(), $sites));
  }

  /**
   * The build panel, re-rendered, plus whether a build is running. Polled by theme-build.js.
   */
  public function statusAction() {
    $site = $this->currentSite();
    $theme = $this->getCurrentTheme();
    $status = $theme ? $this->builder->getStatus($theme, $site) : null;
    $html = $this->getPluginManager()->getServiceLocator()->get('ViewHelperManager')->get('themeBuildPanel')($site, $theme ?: null);
    // Plain JSON response: the admin view strategy would otherwise wrap a view model in the layout.
    $response = $this->getResponse();
    $response->getHeaders()->addHeaderLine('Content-Type', 'application/json; charset=utf-8');
    $response->setContent(json_encode([
      'running' => $status && $status['activeJob'] !== null,
      'jobId' => $status && $status['activeJob'] ? $status['activeJob']->getId() : null,
      'html' => $html,
    ]));
    return $response;
  }

  protected function dispatchBuild(array $siteIds) {
    // The site's Theme page, where the build panel shows progress.
    $redirect = $this->redirect()->toRoute('admin/site/slug/action', ['site-slug' => $this->currentSite()->slug(), 'action' => 'theme']);
    if (!$this->getRequest()->isPost()) {
      return $redirect;
    }
    $form = $this->getForm(Form::class);
    $form->setData($this->params()->fromPost());
    if (!$form->isValid()) {
      $this->messenger()->addFormErrors($form);
      return $redirect;
    }
    if (!$siteIds) {
      $this->messenger()->addWarning('No sites to build.'); // @translate
      return $redirect;
    }
    $theme = $this->getCurrentTheme();
    if ($theme && $this->builder->findActiveJob($this->currentSite())) {
      $this->messenger()->addWarning('A build of this site is already running.'); // @translate
      return $redirect;
    }
    $job = $this->jobDispatcher()->dispatch(BuildSiteTheme::class, ['site_ids' => array_values($siteIds)]);
    $this->messenger()->addSuccess(count($siteIds) > 1
      ? sprintf($this->translate('Building %1$d sites (job #%2$d). The Theme page shows progress.'), count($siteIds), $job->getId())
      : sprintf($this->translate('Building the site theme (job #%d). The Theme page shows progress.'), $job->getId()));
    return $redirect;
  }

  protected function getCurrentTheme(): Theme|false {

    $theme = $this->themeManager->getTheme($this->currentSite()->theme());
    if (!$theme) {
      return false;
    }
    if (!$theme->isConfigurable()) {
      throw new Exception\RuntimeException(
          'The current theme is not configurable.'
      );
    }

    return $theme;
  }

  public function AgileFormCollectionElementGroups(Form $form) {
    $agileFormCollectionElementGroups = new AgileFormCollectionElementGroups();
    return $agileFormCollectionElementGroups($form);
  }

}
