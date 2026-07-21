<?php

namespace OaiPmhHarvester\Form;

use Laminas\Form\Form;
use Omeka\Module\Manager as ModuleManager;
use Omeka\Api\Manager as ApiManager;

/// Form for the
class LiteralValueForm extends Form
{
    protected ModuleManager $moduleManager;
    protected ApiManager $apiManager;

    public function setModuleManager(ModuleManager $moduleManager): void {
        $this->moduleManager = $moduleManager;
    }

    public function setApiManager(ApiManager $apiManager)
    {
        $this->apiManager = $apiManager;
    }


    public function init(): void
    {

        $this->add([
            'name' => 'name',
            'type' => \Laminas\Form\Element\Text::class,
            'options' => [
                'label' => 'Name', // @translate
            ],
            'attributes' => [
                'disabled' => true,
            ],
        ]);

        $this->add([
            'name' => 'xpath',
            'type' => \Laminas\Form\Element\Textarea::class,
            'options' => [
                'label' => 'XPath Condition', // @translate
                'info' => 'XPath expression that must evaluate to "true" or "false", relative to the <oai:record> element, for instance "starts-with(oai:metadata/oai_dc:dc/dc:identifier, "https")"', // @translate
            ],
            'attributes' => [
                'data-field-data-key' => 'xpath',
                'class' => 'oaipmhharvester-monospace',
                'placeholder' => '"true"',
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
            'name' => 'truthy-value',
            'type' => \Laminas\Form\Element\Text::class,
            'options' => [
                'label' => 'value if true', // @translate
                'info' => 'Value to insert in the when the XPath evaluates to true.', // @translate
            ],
            'attributes' => [
                'required' => true,
                'data-field-data-key' => 'truthy-value',
                'placeholder' => "Value Of property", // @translate
            ],
        ]);

        $this->add([
            'name' => 'falsy-value',
            'type' => \Laminas\Form\Element\Text::class,
            'options' => [
                'label' => 'value if false (optional)', // @translate
                'info' => 'Value to insert in the when the XPath expression evaluates to false. If this is not filled the property is not created.', // @translate
            ],
            'attributes' => [
                'data-field-data-key' => 'falsy-value',
                'placeholder' => "Value Of property", // @translate
            ],
        ]);

    }
}
