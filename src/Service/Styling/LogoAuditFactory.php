<?php
namespace AgileThemeTools\Service\Styling;

use AgileThemeTools\Styling\LogoAudit;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class LogoAuditFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $local = $services->get('Config')['file_store']['local'] ?? [];
        return new LogoAudit(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\HttpClient'),
            ($local['base_path'] ?? null) ?: (OMEKA_PATH . '/files')
        );
    }
}
