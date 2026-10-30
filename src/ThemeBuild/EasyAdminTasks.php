<?php
namespace AgileThemeTools\ThemeBuild;

use AgileThemeTools\Job\BuildSiteTheme;
use Laminas\EventManager\Event;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

/**
 * Adds a "Theme builds" group to Easy Admin's Check and fix page, so site theme builds can be run from one place
 * for one site or every site, and their prerequisites checked, without visiting each site's Theme page.
 *
 * Easy Admin exposes two events: form.add_elements on its CheckAndFixForm (add a fieldset with a "process" radio
 * and optional sub-fieldsets named after the process values they belong to) and easyadmin.job on its controller
 * (map a submitted process to a job class and arguments).
 */
class EasyAdminTasks
{
    const FIELDSET = 'agilethemetools_theme_build';
    const PROCESS_CHECK = 'theme_build_check';
    const PROCESS_SITE = 'theme_build_site';
    const PROCESS_ALL = 'theme_build_all';
    const PROCESS_INSTALL = 'theme_build_install';

    protected BuildManager $builder;

    public function __construct(BuildManager $builder)
    {
        $this->builder = $builder;
    }

    public function addFormElements(Event $event): void
    {
        /** @var \EasyAdmin\Form\CheckAndFixForm $form */
        $form = $event->getTarget();

        $siteOptions = [];
        foreach ($this->builder->getAllConfigurableSites() as $site) {
            $theme = $this->builder->getSiteTheme($site);
            $group = $theme ? $theme->getName() : '';
            $siteOptions[$group]['label'] = $group;
            $siteOptions[$group]['options'][$site->id()] = sprintf('%s (%s)', $site->title(), $site->slug());
        }
        $siteCount = array_sum(array_map(fn ($group) => count($group['options']), $siteOptions));

        $form->add([
            'name' => self::FIELDSET,
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Theme builds', // @translate
            ],
            'attributes' => [
                'id' => self::FIELDSET,
                'class' => 'field-container',
            ],
        ]);

        $fieldset = $form->get(self::FIELDSET);
        $fieldset->add([
            'name' => 'process',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Tasks', // @translate
                'label_attributes' => ['style' => 'display: inline-block'],
                'value_options' => [
                    self::PROCESS_CHECK => 'Check that site themes can be built (prerequisites and permissions for every site with per-site styling; builds nothing)', // @translate
                    self::PROCESS_SITE => 'Build the theme of one site', // @translate
                    self::PROCESS_ALL => sprintf('Build the themes of all sites with per-site styling (%d)', $siteCount), // @translate
                    self::PROCESS_INSTALL => 'Install the build dependencies (npm install) of the theme of one site, so it can be built on this server', // @translate
                ],
            ],
            'attributes' => [
                'id' => self::FIELDSET . '-process',
                'required' => false,
                'class' => 'fieldset-process',
            ],
        ]);

        $fieldset
            ->add([
                'type' => Fieldset::class,
                'name' => self::PROCESS_SITE,
                'options' => [
                    'label' => 'Site to build', // @translate
                ],
                'attributes' => [
                    'class' => self::PROCESS_SITE,
                ],
            ])
            ->get(self::PROCESS_SITE)
            ->add([
                'name' => 'site_id',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Site', // @translate
                    'info' => 'Only sites whose theme has "Use theme configuration" turned on are listed. Progress and results are shown on the site\'s Theme page and in the job log.', // @translate
                    'value_options' => $siteOptions,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => self::FIELDSET . '-site_id',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a site…', // @translate
                ],
            ]);
    }

    public function addInputFilters(Event $event): void
    {
        /** @var \Laminas\InputFilter\InputFilterInterface $inputFilter */
        $inputFilter = $event->getParam('inputFilter');
        if (!$inputFilter->has(self::FIELDSET)) {
            return;
        }
        $inputFilter->get(self::FIELDSET)->add([
            'name' => 'process',
            'required' => false,
        ]);
        $inputFilter->get(self::FIELDSET)->get(self::PROCESS_SITE)->add([
            'name' => 'site_id',
            'required' => false,
        ]);
    }

    public function handleJob(Event $event): void
    {
        $process = $event->getParam('process');
        if (!in_array($process, [self::PROCESS_CHECK, self::PROCESS_SITE, self::PROCESS_ALL, self::PROCESS_INSTALL], true)) {
            return;
        }
        $params = $event->getParam('params');

        if ($process === self::PROCESS_INSTALL) {
            // The theme of the site chosen in the "Site to build" selector.
            $siteId = (int) ($params[self::FIELDSET][self::PROCESS_SITE]['site_id'] ?? 0);
            $theme = null;
            foreach ($this->builder->getAllConfigurableSites() as $candidate) {
                if ($candidate->id() === $siteId) {
                    $theme = $this->builder->getSiteTheme($candidate);
                }
            }
            if (!$theme) {
                return; // No site chosen: Easy Admin reports the process as unknown.
            }
            $event->setParam('job', \AgileThemeTools\Job\InstallThemeDependencies::class);
            $event->setParam('args', ['theme' => $theme->getId(), 'site_ids' => [$siteId]]);
            return;
        }

        if ($process === self::PROCESS_SITE) {
            $siteId = (int) ($params[self::FIELDSET][self::PROCESS_SITE]['site_id'] ?? 0);
            if (!$siteId) {
                // Leave "job" unset: Easy Admin reports the process as unknown, which is the closest it offers to
                // "no site selected".
                return;
            }
            $siteIds = [$siteId];
        } else {
            $siteIds = array_map(fn ($site) => $site->id(), $this->builder->getAllConfigurableSites());
        }

        $event->setParam('job', BuildSiteTheme::class);
        $event->setParam('args', [
            'site_ids' => $siteIds,
            'preflight_only' => $process === self::PROCESS_CHECK,
        ]);
    }
}
