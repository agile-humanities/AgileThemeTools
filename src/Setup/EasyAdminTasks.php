<?php
namespace AgileThemeTools\Setup;

use AgileThemeTools\Job\ApplySitePresets;
use Laminas\EventManager\Event;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Api\Manager as ApiManager;

/**
 * Adds an "Agile site setup" group to Easy Admin's Check and fix page: the site presets (standard item pages,
 * standard homepage, search pages, block test pages), applied to one site as a background job. See the Setup\*Preset
 * classes for what each writes; all of them can be rerun.
 */
class EasyAdminTasks
{
    const FIELDSET = 'agilethemetools_site_setup';
    const PROCESS = 'agile_site_setup';

    protected ApiManager $api;

    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    public function addFormElements(Event $event): void
    {
        /** @var \EasyAdmin\Form\CheckAndFixForm $form */
        $form = $event->getTarget();

        $siteOptions = [];
        foreach ($this->api->search('sites', ['sort_by' => 'title'])->getContent() as $site) {
            $siteOptions[$site->id()] = sprintf('%s (%s) — theme %s', $site->title(), $site->slug(), $site->theme());
        }

        $form->add([
            'name' => self::FIELDSET,
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Agile site setup', // @translate
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
                    self::PROCESS => 'Apply the Agile presets to a site (pages are replaced by slug, so presets can be rerun)', // @translate
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
                'name' => self::PROCESS,
                'options' => [
                    'label' => 'Site and presets', // @translate
                ],
                'attributes' => [
                    'class' => self::PROCESS,
                ],
            ])
            ->get(self::PROCESS)
            ->add([
                'name' => 'site_id',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Site', // @translate
                    'value_options' => $siteOptions,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => self::FIELDSET . '-site_id',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a site…', // @translate
                ],
            ])
            ->add([
                'name' => 'presets',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Presets', // @translate
                    'value_options' => [
                        'item_pages' => 'Item pages: the standard item page blocks (Advanced media, heading, downloads, summary, full metadata) and the Agile Mirador preset', // @translate
                        'homepage' => 'Homepage: a "Home" page with the homepage introduction slide viewer, an about block and the recent items, set as the homepage', // @translate
                        'search_pages' => 'Search pages: Agile filters with Solr, Agile filters without Solr, and Advanced Search\'s default form, with the same facets; the Solr one becomes the site\'s main search when Solr answers', // @translate
                        'sample_pages' => 'Block test pages: every Omeka core, Agile and third-party block, and a section with three child pages, added to the navigation', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => self::FIELDSET . '-presets',
                    'value' => ['item_pages', 'homepage', 'search_pages'],
                ],
            ])
            ->add([
                'name' => 'solr_url',
                'type' => Element\Url::class,
                'options' => [
                    'label' => 'Solr core URL (search pages)', // @translate
                    'info' => 'The Solr core the "Agile filters with Solr" page indexes into, as seen from this server, e.g. http://localhost:8983/solr/omeka. Empty: the Solr page is skipped.', // @translate
                ],
                'attributes' => [
                    'id' => self::FIELDSET . '-solr_url',
                    'placeholder' => 'http://localhost:8983/solr/omeka',
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
        $inputFilter->get(self::FIELDSET)->add(['name' => 'process', 'required' => false]);
        foreach (['site_id', 'presets', 'solr_url'] as $name) {
            $inputFilter->get(self::FIELDSET)->get(self::PROCESS)->add(['name' => $name, 'required' => false]);
        }
    }

    public function handleJob(Event $event): void
    {
        if ($event->getParam('process') !== self::PROCESS) {
            return;
        }
        $values = $event->getParam('params')[self::FIELDSET][self::PROCESS] ?? [];
        $siteId = (int) ($values['site_id'] ?? 0);
        $presets = array_values(array_filter((array) ($values['presets'] ?? [])));
        if (!$siteId || !$presets) {
            // Leave "job" unset: Easy Admin reports the process as unknown, the closest it offers to "nothing chosen".
            return;
        }
        $event->setParam('job', ApplySitePresets::class);
        $event->setParam('args', [
            'site_id' => $siteId,
            'presets' => $presets,
            'solr_url' => trim((string) ($values['solr_url'] ?? '')),
        ]);
    }
}
