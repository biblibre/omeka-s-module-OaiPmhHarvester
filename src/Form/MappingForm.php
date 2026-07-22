<?php

namespace OaiPmhHarvester\Form;

use Laminas\Form\Form;
use Omeka\Module\Manager as ModuleManager;
use Omeka\Api\Manager as ApiManager;

class MappingForm extends Form
{

    protected ModuleManager $moduleManager;
    protected ApiManager $apiManager;

    public function setModuleManager(ModuleManager $moduleManager): void {
        $this->moduleManager = $moduleManager;
    }

    public function setApiManager(ApiManager $apiManager): void
    {
        $this->apiManager = $apiManager;
    }

    public function init(): void
    {

        $this->add([
            'name' => 'xpath',
            'type' => \Laminas\Form\Element\Textarea::class,
            'options' => [
                'label' => 'XPath', // @translate
                'info' => 'XPath expression, relative to the <oai:record> element, for instance "oai:metadata/oai_dc:dc/dc:title"', // @translate
            ],
            'attributes' => [
                'data-field-data-key' => 'xpath',
                'class' => 'oaipmhharvester-monospace',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'property',
            'type' => \Omeka\Form\Element\PropertySelect::class,
            'options' => [
                'label' => 'Property', // @translate
                'term_as_value' => true,
            ],
            'attributes' => [
                'data-field-data-key' => 'property',
                'required' => true,
            ],
        ]);

        $typeValueOptions = [
            'literal' => 'Text', // @translate
            'uri' => 'URI', // @translate
        ];

        // Support for custom vocab module
        $customVocabModule = $this->moduleManager->getModule('CustomVocab');
        if ($customVocabModule && $customVocabModule->getState() === 'active') {
            $vocabs = $this->apiManager->search('custom_vocabs');
            foreach ($vocabs->getContent() as $vocab) {
                $typeValueOptions['customvocab:' . $vocab->id()] = 'CustomVocab - ' . $vocab->label();
            }
        }

        $this->add([
            'name' => 'type',
            'type' => \Laminas\Form\Element\Select::class,
            'options' => [
                'label' => 'Type', // @translate
                'value_options' => $typeValueOptions,
            ],
            'attributes' => [
                'data-field-data-key' => 'type',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'replacements',
            'type' => \Laminas\Form\Element\Textarea::class,
            'options' => [
                'label' => 'Replacements', // @translate
                'info' => 'Text replacements to perform. One per line. Format: old-value = new-value. Replacement is done only if the value matches exactly.', // @translate
            ],
            'attributes' => [
                'data-field-data-key' => 'replacements',
                'placeholder' => "old value 1 = new value 1\nold value 2 = new value 2", // @translate
            ],
        ]);
    }
}
