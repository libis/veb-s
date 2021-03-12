<?php
namespace BlockPlus\Service\Form;

use BlockPlus\Form\PageMetadataFieldset;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PageMetadataFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $siteSettings = $services->get('Omeka\Settings\Site');
        $form = new PageMetadataFieldset(null, $options);
        $form->setPageTypes($siteSettings->get('blockplus_page_types', []));
        return $form;
    }
}
