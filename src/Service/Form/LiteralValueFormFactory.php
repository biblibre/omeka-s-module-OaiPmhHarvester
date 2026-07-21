<?php declare(strict_types=1);

namespace OaiPmhHarvester\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OaiPmhHarvester\Form\LiteralValueForm;


class LiteralValueFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $literalValueForm = new LiteralValueForm(null, $options ?? []);
        $literalValueForm->setModuleManager($services->get('Omeka\ModuleManager'));
        $literalValueForm->setApiManager($services->get('Omeka\ApiManager'));
        return $literalValueForm;
    }
}
