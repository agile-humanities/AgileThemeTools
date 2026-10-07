<?php
namespace AgileThemeTools\DisplayMode;

use Omeka\ServiceManager\AbstractPluginManager;

/**
 * The display modes registered under `display_modes` in the module configuration (see Omeka's block layout and
 * resource page block layout managers, which this follows). Other modules and themes' modules may add modes.
 */
class Manager extends AbstractPluginManager
{
    protected $autoAddInvokableClass = false;

    protected $instanceOf = DisplayModeInterface::class;
}
