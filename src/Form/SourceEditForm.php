<?php declare(strict_types=1);

namespace OaiPmhHarvester\Form;

use Laminas\Form\Element\Select;
use Laminas\Form\Element\Text;
use Laminas\Form\Element\Url;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use OaiPmhHarvester\Job\HarvestSource;
use Omeka\Api\Manager as ApiManager;

class SourceEditForm extends Form
{
    protected ApiManager $apiManager;

    public function init()
    {
        $this->add([
            'name' => 'o:base_url',
            'type' => Url::class,
            'options' => [
                'label' => 'Base URL', // @translate
            ],
            'attributes' => [
                'disabled' => true,
            ],
        ]);

        $this->add([
            'name' => 'o:configuration',
            'type' => Fieldset::class,
        ]);

        $this->get('o:configuration')->add([
            'name' => 'o:id',
            'type' => Select::class,
            'options' => [
                'label' => 'Configuration', // @translate
                'value_options' => $this->getConfigurationValueOptions(),
                'empty_option' => '',
            ],
            'attributes' => [
                'id' => 'configuration-id',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'o:name',
            'type' => Text::class,
            'options' => [
                'label' => 'Name', // @translate
            ],
            'attributes' => [
                'id' => 'name',
                'required' => true,
            ],
        ]);

        $metadataPrefixOptions = $this->getOption('metadataPrefixOptions', []);
        if (!$metadataPrefixOptions) {
            $metadataPrefixOptions = ['oai_dc' => 'oai_dc'];
        }

        $this->add([
            'name' => 'o:metadata_prefix',
            'type' => Select::class,
            'options' => [
                'label' => 'Metadata prefix', // @translate
                'value_options' => $metadataPrefixOptions,
            ],
            'attributes' => [
                'id' => 'metadata-prefix',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'o:sets',
            'type' => Element\SetsTextarea::class,
            'options' => [
                'label' => 'Sets', // @translate
                'info' => 'One set per line. If empty, the whole repository will be harvested.', // @translate
                'source_id' => $this->getOption('source_id'),
            ],
            'attributes' => [
                'id' => 'sets',
                'rows' => '10',
            ],
        ]);

        $this->add([
            'name' => 'o:update_mode',
            'type' => 'Laminas\Form\Element\Select',
            'options' => [
                'label' => 'Update mode', // @translate
                'info' => 'How existing items are updated', // @translate
                'empty_option' => 'No update', // @translate
                'value_options' => [
                    HarvestSource::UPDATE_MODE_REPLACE_ALL_METADATA => 'Replace all metadata', // @translate
                    HarvestSource::UPDATE_MODE_REPLACE_ALL_METADATA_BUT_ARK => 'Replace all metadata except ARK identifiers in dcterms:identifier', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'sets',
                'rows' => '10',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'o:base_url',
            'required' => false,
        ]);

        $inputFilter->add([
            'name' => 'o:update_mode',
            'allow_empty' => true,
        ]);
    }

    public function getApiManager(): ApiManager
    {
        return $this->apiManager;
    }

    public function setApiManager(ApiManager $apiManager)
    {
        $this->apiManager = $apiManager;
    }

    protected function getConfigurationValueOptions()
    {
        $api = $this->getApiManager();
        $configurations = $api->search('oaipmhharvester_configurations')->getContent();

        return array_map(fn($c) => ['value' => $c->id(), 'label' => $c->name()], $configurations);
    }
}
