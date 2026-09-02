<?php

namespace OaiPmhHarvester\Form;

use Laminas\Form\Form;
use Omeka\Module\Manager as ModuleManager;
use Omeka\Api\Manager as ApiManager;

/**
 * @extends Form<mixed>
 */
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
            'name' => 'property',
            'type' => \Omeka\Form\Element\PropertySelect::class,
            'options' => [
                'label' => 'Destination property', // @translate
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
                'label' => 'Destination type', // @translate
                'value_options' => $typeValueOptions,
            ],
            'attributes' => [
                'data-field-data-key' => 'type',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'xpath-cond',
            'type' => \Laminas\Form\Element\Textarea::class,
            'options' => [
                'label' => 'XPath condition', // @translate
                'info' => 'XPath expression, relative to the <oai:record> element, for instance "oai:metadata/oai_dc:dc/dc:title"', // @translate
            ],
            'attributes' => [
                'data-field-data-key' => 'xpath-cond',
                'class' => 'oaipmhharvester-monospace',
                'placeholder' => "Xpath that evaluates to a truthy value (true, a node, a string, or a number) or false that determines what is ran.", // @translate
            ],
        ]);

        $this->add([
            'name' => 'truthy-kind',
            'type' => \Laminas\Form\Element\Select::class,
            'options' => [
                'label' => 'Opperation if true', // @translate
                'info' => 'XPath expression, relative to the <oai:record> element, for instance "oai:metadata/oai_dc:dc/dc:title"', // @translate
                'empty_option' => 'Operation kind',
                'value_options' => [
                    'xpath-query' => 'Query value',
                    'create-value' => 'Create value',
                ],
            ],
            'attributes' => [
                'data-field-data-key' => 'truthy-kind',
                'class' => 'oaipmhharvester-monospace',
                'required' => true,
            ],
        ]);
        $this->add([
            'name' => 'truthy-input',
            'type' => \Laminas\Form\Element\Textarea::class,
            'options' => [
                // 'label' => 'Opperation', // @translate
                'info' => 'XPath expression, relative to the <oai:record> element, for instance "oai:metadata/oai_dc:dc/dc:title"', // @translate
                'value_options' => [
                    'xpath-query' => 'Query value',
                    'create-value' => 'Create value',
                ],
            ],
            'attributes' => [
                'data-field-data-key' => 'truthy-input',
                'class' => 'oaipmhharvester-monospace',
                'placeholder' => "Xpath or Value depending on operation type", // @translate
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'truthy-replacements',
            'type' => \Laminas\Form\Element\Textarea::class,
            'options' => [
                'label' => 'Replacements', // @translate
                'info' => 'Text replacements to perform. One per line. Format: old-value = new-value. Replacement is done only if the value matches exactly.', // @translate
            ],
            'attributes' => [
                'data-field-data-key' => 'truthy-replacements',
                'placeholder' => "old value 1 = new value 1\nold value 2 = new value 2", // @translate
            ],
        ]);
        $this->add([
            'name' => 'falsy-kind',
            'type' => \Laminas\Form\Element\Select::class,
            'options' => [
                'label' => 'Opperation if false', // @translate
                'info' => 'XPath expression, relative to the <oai:record> element, for instance "oai:metadata/oai_dc:dc/dc:title"', // @translate
                'empty_option' => 'Operation kind',
                'value_options' => [
                    'xpath-query' => 'Query value',
                    'create-value' => 'Create value',
                ],
            ],
            'attributes' => [
                'data-field-data-key' => 'falsy-kind',
                'class' => 'oaipmhharvester-monospace',
            ],
        ]);
        $this->add([
            'name' => 'falsy-input',
            'type' => \Laminas\Form\Element\Textarea::class,
            'options' => [
                'info' => 'XPath expression, relative to the <oai:record> element, for instance "oai:metadata/oai_dc:dc/dc:title"', // @translate
                'value_options' => [
                    'xpath-query' => 'Query value',
                    'create-value' => 'Create value',
                ],
            ],
            'attributes' => [
                'data-field-data-key' => 'falsy-input',
                'class' => 'oaipmhharvester-monospace',
                'placeholder' => "Xpath or Value depending on operation type", // @translate
            ],
        ]);
        $this->add([
            'name' => 'falsy-replacements',
            'type' => \Laminas\Form\Element\Textarea::class,
            'options' => [
                'label' => 'Replacements', // @translate
                'info' => 'Text replacements to perform. One per line. Format: old-value = new-value. Replacement is done only if the value matches exactly.', // @translate
            ],
            'attributes' => [
                'data-field-data-key' => 'falsy-replacements',
                'placeholder' => "old value 1 = new value 1\nold value 2 = new value 2", // @translate
            ],
        ]);
    }
}
