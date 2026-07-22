<?php

namespace OaiPmhHarvester\Converter;

use DOMElement;
use DOMXPath;
use DOMNodeList;
use Generator;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Log\LoggerInterface;
use Laminas\View\HelperPluginManager;
use Laminas\View\Renderer\PhpRenderer;
use OaiPmhHarvester\OaiPmh\OaiRecord;
use OaiPmhHarvester\Form\Element\Fields;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\PropertyRepresentation;
use Omeka\Form\Element\ArrayTextarea;
use boolean;

class XPathConverter implements ConfigurableConverterInterface
{
    protected ApiManager $apiManager;
    protected HelperPluginManager $viewHelperManager;
    protected LoggerInterface $logger;

    public function __construct(ApiManager $apiManager, HelperPluginManager $viewHelperManager, LoggerInterface $logger)
    {
        $this->apiManager = $apiManager;
        $this->viewHelperManager = $viewHelperManager;
        $this->logger = $logger;
    }

    public function getLabel(): string
    {
        return 'XPath converter'; // @translate
    }

    protected function isValidCustomVocab(string $value, int $vocab_id): bool {
        $vocab = $this->apiManager->read('custom_vocabs', $vocab_id, [], [])->getContent();
        $terms = $vocab->terms();
        $valid = in_array($value, $terms); 
        if (!$valid) {
            $this->logger->err(sprintf('Invalid value "%s" for Custom Vocab: %s', $value, $vocab->label()));
        }
        return $valid;
    }

    /**
     * This function evaluates an xpath expression correctly and makes the differenciation 
     * between an eroneous call and an XPath expression returning false.
     */
    protected function evalXpath(DOMXPath $xpath, DOMElement $element, string $expr): mixed {
        libxml_clear_errors();
        $xpath_result = $xpath->evaluate($expr, $element);
        $errors = libxml_get_errors();
        if (!$xpath_result && $errors) {
            $errors_string = implode('\n\t', array_map(fn($it) => sprintf("LibXml error code %d : %s", $it->code, trim($it->message)), $errors));
            $this->logger->err(sprintf("Errors while exectuing XPath expression %s : \n %s", $expr, $errors_string));
            return null;
        }
        return $xpath_result;
    }

    /**
     * @return an array that can be passed directly as 2nd parameter of
     *         \Omeka\Api\Manager::batchCreate
     */
    public function convert(OaiRecord $record, array $settings = []): Generator
    {
        $xpath = $record->getDOMXPath();
        $element = $record->getDOMElement();

        $namespaces = $settings['namespaces'] ?? [];
        foreach ($namespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        $itemData = [];

        $mappings = $settings['mappings'] ?? [];
        foreach ($mappings as $mapping) {
            $type = $mapping['type'] ?? 'literal';
            $property = $this->getPropertyByTerm($mapping['property']);
            if (!$property) {
                $this->logger->err(sprintf('Unknown property: %s', $mapping['property']));
                continue;
            }

            $value = '';
            $xpath_result = $this->evalXpath($xpath, $element, $mapping['xpath']);
            if ($xpath_result === null) { 
                $this->logger->err(sprintf("Error while executing xpath for mapping for %s.", $mapping['property']));
                continue;
            }
            if (!$xpath_result instanceof DOMNodeList) {
            }
            switch ($mapping['name']) {
                case 'xpath': 
                    $replacements = $this->stringToKeyValues($mapping['replacements'] ?? '');
                    if ($xpath_result instanceof DOMNodeList) {
                        foreach ($xpath_result as $node) {
                            $value = trim($node->textContent);
                            if ($value === '') {
                                break;
                            }
                            if (array_key_exists($value, $replacements)) {
                                $value = $replacements[$value];
                            }

                            $lang = null;
                            if ($node instanceof DOMElement) {
                                $lang = trim($node->getAttribute('xml:lang'));
                            }
                            $this->addValueToItem($itemData, $property, $type, $value, $lang);
                        }
                    } else {
                        if ($xpath_result === '') {
                            break;
                        }
                        if (array_key_exists($xpath_result, $replacements)) {
                            $value = $replacements[$value];
                        } else {
                            $value = $xpath_result;
                        }
                        $this->addValueToItem($itemData, $property, $type, $value);
                    }
                    break;
                case 'literal-value-xpath-condition':
                    $value = $xpath_result ? $mapping['truthy-value'] : $mapping['falsy-value'];
                    $this->addValueToItem($itemData, $property, $type, $value);
                    break;
                default:
                    $this->logger->err(sprintf("Unknown mapping: %s", $mapping['name']));
                    break;
            }
        }

        $itemId = yield $itemData;
    }

    /**
     * @param array<string,mixed> $itemData
     */
    protected function addValueToItem(array &$itemData, PropertyRepresentation $property, string $type, string $value, string $lang = null): void {
        $valueData = [
            'property_id' => $property->id(),
            'is_public' => true,
            'type' => $type,
        ];

        if ($type === 'uri') {
            $valueData['@id'] = $value;
        } elseif ($type === 'literal') {
            $valueData['@value'] = $value;
        } elseif (str_starts_with($type, 'customvocab')) {
            if (!$this->isValidCustomVocab($value, explode(':', $type)[1])) {
                return;
            }
            $valueData['@value'] = $value;
        } 
        $itemData[$property->term()] ??= [];
        $itemData[$property->term()][] = $valueData;
    }

    public function addConfigurationFormElements(Form $form): void
    {
        $configuration = $form->getOption('configuration');

        $url = $this->viewHelperManager->get('Url');

        $form->get('o:settings')->add([
            'name' => 'namespaces',
            'type' => ArrayTextarea::class,
            'options' => [
                'label' => 'XML namespaces', // @translate
                'info' => 'XML namespaces used in the XPath mappings should be registered here, one per line, in the format: <code>prefix = namespace-uri</code>.<br>For instance: <code>oai_dc = http://www.openarchives.org/OAI/2.0/oai_dc/</code>.<br>The <code>oai</code> namespace is always registered and does not need to be listed here.', // @translate
                'escape_info' => false,
                'as_key_value' => true,
            ],
            'attributes' => [
                'placeholder' => "oai_dc = http://www.openarchives.org/OAI/2.0/oai_dc/\ndc = http://purl.org/dc/elements/1.1/",
                'rows' => 5,
            ],
        ]);

        $form->get('o:settings')->add([
            'name' => 'mappings',
            'type' => Fields::class,
            'options' => [
                'label' => 'Mappings', // @translate
                'empty_option' => 'Add a mapping', // @translate
                'value_options' => [
                    [
                        'value' => 'xpath',
                        'label' => 'Query a value from an XPath expression', // @translate
                        'attributes' => [
                            'data-repeatable' => '1',
                        ],
                    ],
                    [
                        'value' => 'literal-value-xpath-condition',
                        'label' => 'Create a value with an XPath condition', // @translate
                        'attributes' => [
                            'data-repeatable' => '2',
                        ],
                    ],
                ],
                'field_list_url' => $url('admin/oaipmhharvester/mappings', ['action' => 'field-list'], ['query' => ['configuration_id' => $configuration->id()]]),
                'field_row_url' => $url('admin/oaipmhharvester/mappings', ['action' => 'field-row'], ['query' => ['configuration_id' => $configuration->id()]]),
                'field_edit_sidebar_url' => $url('admin/oaipmhharvester/mappings', ['action' => 'field-edit-sidebar'], ['query' => ['configuration_id' => $configuration->id()]]),
            ],
        ]);
    }

    public function addConfigurationFormInputFilters(Form $form, InputFilterInterface $inputFilter): void
    {
    }

    public function getConfigurationDetails(PhpRenderer $renderer, array $settings): string
    {
        return $renderer->partial('oai-pmh-harvester/common/xpath-converter-details', ['settings' => $settings]);
    }

    public function getConfigurationDetailsFull(PhpRenderer $renderer, array $settings): string
    {
        return $renderer->partial('oai-pmh-harvester/common/xpath-converter-details-full', ['settings' => $settings]);
    }

    protected function getPropertyByTerm (string $term): ?PropertyRepresentation
    {
        $properties = $this->apiManager->search('properties', ['term' => $term])->getContent();

        return $properties ? $properties[0] : null;
    }

    protected function stringToKeyValues(string $string): array
    {
        $result = [];
        foreach ($this->stringToList($string) as $keyValue) {
            [$key, $value] = array_map('trim', explode('=', $keyValue, 2));
            $result[$key] = $value;
        }

        return $result;
    }

    protected function stringToList(string $string): array
    {
        return array_filter(array_map('trim', explode("\n", $this->fixEndOfLine($string))), 'strlen');
    }

    protected function fixEndOfLine(string $string): string
    {
        return str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], $string);
    }
}
