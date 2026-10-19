<?php
namespace AgileThemeTools\Dependency;

use AgileThemeTools\Job\CheckDependencies;
use Laminas\EventManager\Event;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

/**
 * Adds an "Agile modules" group to Easy Admin's Check and fix page, with two tasks: write the dependency audit to
 * the job log; apply the Agile Mirador preset (Setup\MiradorPreset). See ThemeBuild\EasyAdminTasks for how Easy
 * Admin's two events are used.
 */
class EasyAdminTasks
{
    const FIELDSET = 'agilethemetools_dependencies';
    const PROCESS_CHECK = 'agile_dependency_check';
    const PROCESS_MIRADOR_PRESET = 'agile_mirador_preset';

    public function addFormElements(Event $event): void
    {
        /** @var \EasyAdmin\Form\CheckAndFixForm $form */
        $form = $event->getTarget();
        $form->add([
            'name' => self::FIELDSET,
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Agile modules', // @translate
            ],
            'attributes' => [
                'id' => self::FIELDSET,
                'class' => 'field-container',
            ],
        ]);
        $form->get(self::FIELDSET)->add([
            'name' => 'process',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Tasks', // @translate
                'label_attributes' => ['style' => 'display: inline-block'],
                'value_options' => [
                    self::PROCESS_CHECK => 'Check the modules the Agile theme system depends on, and their versions (changes nothing; results are in the job log)', // @translate
                    self::PROCESS_MIRADOR_PRESET => 'Apply the Agile Mirador preset to the installation and every site: dark theme, image tools, no workspace panel (overwrites the Mirador version, plugins and viewer configuration settings)', // @translate
                ],
            ],
            'attributes' => [
                'id' => self::FIELDSET . '-process',
                'required' => false,
                'class' => 'fieldset-process',
            ],
        ]);
    }

    public function addInputFilters(Event $event): void
    {
        /** @var \Laminas\InputFilter\InputFilterInterface $inputFilter */
        $inputFilter = $event->getParam('inputFilter');
        if ($inputFilter->has(self::FIELDSET)) {
            $inputFilter->get(self::FIELDSET)->add(['name' => 'process', 'required' => false]);
        }
    }

    public function handleJob(Event $event): void
    {
        if ($event->getParam('process') === self::PROCESS_CHECK) {
            $event->setParam('job', CheckDependencies::class);
            $event->setParam('args', []);
        } elseif ($event->getParam('process') === self::PROCESS_MIRADOR_PRESET) {
            $event->setParam('job', \AgileThemeTools\Job\ApplyMiradorPreset::class);
            $event->setParam('args', []);
        }
    }
}
