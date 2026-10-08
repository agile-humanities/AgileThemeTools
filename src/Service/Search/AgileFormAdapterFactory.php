<?php
namespace AgileThemeTools\Service\Search;

use AgileThemeTools\Search\AgileFormAdapter;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/** The adapter extends Advanced Search's; it can only exist when that module is active. */
class AgileFormAdapterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        if (!class_exists(\AdvancedSearch\FormAdapter\MainFormAdapter::class)) {
            throw new \RuntimeException('The Agile Theme Tools search form needs the Advanced Search module.');
        }
        return new AgileFormAdapter();
    }
}
