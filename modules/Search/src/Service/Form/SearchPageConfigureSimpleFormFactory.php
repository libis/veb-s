<?php declare(strict_types=1);
namespace Search\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Search\Form\Admin\SearchPageConfigureSimpleForm;

class SearchPageConfigureSimpleFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $formElementManager = $services->get('FormElementManager');
        $form = new SearchPageConfigureSimpleForm(null, $options);
        $form->setFormElementManager($formElementManager);
        return $form;
    }
}
