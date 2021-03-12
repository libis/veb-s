<?php declare(strict_types=1);
namespace Search\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Search\Form\Admin\ApiFormConfigFieldset;

class ApiFormConfigFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ApiFormConfigFieldset(null, $options);
        return $form
            ->setApiManager($services->get('Omeka\ApiManager'));
    }
}
