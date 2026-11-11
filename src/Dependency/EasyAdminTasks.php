<?php
namespace AgileThemeTools\Dependency;

use AgileThemeTools\Job\CheckDependencies;
use Laminas\EventManager\Event;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

/**
 * Adds an "Agile modules" group to Easy Admin's Check and fix page, with three tasks: write the dependency audit and
 * the state of the Agile patches to the job log; apply the missing Agile patches (Job\ApplyPatches); apply the Agile
 * Mirador preset (Setup\MiradorPreset). See ThemeBuild\EasyAdminTasks for how Easy
 * Admin's two events are used.
 */
class EasyAdminTasks
{
    const FIELDSET = 'agilethemetools_dependencies';
    const PROCESS_CHECK = 'agile_dependency_check';
    const PROCESS_MIRADOR_PRESET = 'agile_mirador_preset';
    const PROCESS_APPLY_PATCHES = 'agile_apply_patches';

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
                    self::PROCESS_CHECK => 'Check the modules the Agile theme system depends on, their versions and their Agile patches (changes nothing; results are in the job log)', // @translate
                    self::PROCESS_APPLY_PATCHES => 'Apply the Agile patches that are missing (fixes to third-party modules shipped with Agile Theme Tools; run it after updating one of these modules, before its upgrade). The files must be writable by the web server; otherwise the job log gives the command to run on the server. Originals are copied to files/agile-patches/backups', // @translate
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
        } elseif ($event->getParam('process') === self::PROCESS_APPLY_PATCHES) {
            $event->setParam('job', \AgileThemeTools\Job\ApplyPatches::class);
            $event->setParam('args', []);
        } elseif ($event->getParam('process') === self::PROCESS_MIRADOR_PRESET) {
            $event->setParam('job', \AgileThemeTools\Job\ApplyMiradorPreset::class);
            $event->setParam('args', []);
        }
    }
}
