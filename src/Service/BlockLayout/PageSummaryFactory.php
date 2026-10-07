<?php
namespace AgileThemeTools\Service\BlockLayout;

use AgileThemeTools\Site\BlockLayout\PageSummary;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PageSummaryFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new PageSummary($services->get('Omeka\HtmlPurifier'));
    }
}
