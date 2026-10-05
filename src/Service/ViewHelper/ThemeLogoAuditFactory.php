<?php
namespace AgileThemeTools\Service\ViewHelper;

use AgileThemeTools\Styling\LogoAudit;
use AgileThemeTools\View\Helper\ThemeLogoAudit;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ThemeLogoAuditFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ThemeLogoAudit($services->get(LogoAudit::class), $services->get('Omeka\Site\ThemeManager'));
    }
}
