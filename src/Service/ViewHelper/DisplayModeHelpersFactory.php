<?php
namespace AgileThemeTools\Service\ViewHelper;

use AgileThemeTools\DisplayMode\Manager;
use AgileThemeTools\View\Helper\Card;
use AgileThemeTools\View\Helper\DisplayMode;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/** Builds the displayMode and card view helpers around the display-mode manager. */
class DisplayModeHelpersFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $manager = $services->get(Manager::class);
        return $requestedName === 'card' ? new Card($manager) : new DisplayMode($manager);
    }
}
