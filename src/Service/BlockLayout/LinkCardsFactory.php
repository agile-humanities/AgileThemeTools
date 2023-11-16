<?php
namespace AgileThemeTools\Service\BlockLayout;

use AgileThemeTools\Site\BlockLayout\LinkCards;
use Interop\Container\ContainerInterface;
use Omeka\Stdlib\ErrorStore;
use Zend\ServiceManager\Factory\FactoryInterface;

class LinkCardsFactory implements FactoryInterface
{
    /**
     * Create the Html block layout service.
     *
     * @param ContainerInterface $serviceLocator
     * @return ItemListing
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $htmlPurifier = $serviceLocator->get('Omeka\HtmlPurifier');
        $formElementManager = $serviceLocator->get('FormElementManager');
        $errorStore = new ErrorStore();
        $blockLayoutManager = $serviceLocator->get('Omeka\BlockLayoutManager');


        return new LinkCards($blockLayoutManager,$htmlPurifier,$formElementManager,$errorStore);
    }
}
