<?php declare(strict_types=1);

namespace OaiPmhHarvester\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OaiPmhHarvester\Form\MappingForm;


class MappingFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $mappingForm = new MappingForm(null, $options ?? []);
        $mappingForm->setModuleManager($services->get('Omeka\ModuleManager'));
        $mappingForm->setApiManager($services->get('Omeka\ApiManager'));
        return $mappingForm;
    }
}
