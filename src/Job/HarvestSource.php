<?php declare(strict_types=1);

namespace OaiPmhHarvester\Job;

use OaiPmhHarvester\OaiPmh\OaiRecord;
use OaiPmhHarvester\Api\Representation\SourceRepresentation;
use Omeka\Job\AbstractJob;

class HarvestSource extends AbstractJob
{
    const UPDATE_MODE_REPLACE_ALL_METADATA = 'replace_all_metadata';
    const UPDATE_MODE_REPLACE_ALL_METADATA_BUT_ARK = 'replace_all_metadata_but_ark';

    protected int $importedRecords = 0;

    public function perform()
    {
        $previous_libxml_error_status = libxml_use_internal_errors(true);
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');
        $client = $services->get('OaiPmhHarvester\Client');

        $logger->info('Job started');

        $sourceId = $this->getArg('source_id');
        $source = $api->read('oaipmhharvester_sources', $sourceId)->getContent();

        if ($this->getArg('delete_all_items', false)) {
            $this->deleteAllItems($source);
        }

        if ($this->shouldStop()) {
            $logger->info('Job stopped');
            libxml_use_internal_errors($previous_libxml_error_status);
            return;
        }

        $client->setMaxTries(3);
        $sets = $source->sets();
        if ($sets) {
            foreach ($sets as $set) {
                if ($this->shouldStop()) {
                    break;
                }

                $this->harvest($source, $set);
            }
        } else {
            $this->harvest($source);
        }

        if ($this->shouldStop()) {
            $logger->info('Job stopped');
            libxml_use_internal_errors($previous_libxml_error_status);
            return;
        }

        $logger->info(sprintf('Total records imported: %d', $this->importedRecords));
        $logger->info('Job ended normally');
        libxml_use_internal_errors($previous_libxml_error_status);
    }

    protected function deleteAllItems(SourceRepresentation $source)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');

        $response = $api->search('items', ['oaipmhharvester_source_id' => $source->id()], ['returnScalar' => 'id']);

        $logger->info(sprintf('Deleting %d items', $response->getTotalResults()));

        // Batch delete the resources in chunks.
        foreach (array_chunk($response->getContent(), 100) as $idsChunk) {
            if ($this->shouldStop()) {
                return;
            }
            $api->batchDelete('items', $idsChunk, [], ['continueOnError' => true]);
        }

        $response = $api->search('items', ['oaipmhharvester_source_id' => $source->id()], ['returnScalar' => 'id']);
        if ($response->getTotalResults() > 0) {
            throw new \Exception('Failed to delete some or all items');
        }

        $logger->info('Deletion finished');
    }

    protected function harvest(SourceRepresentation $source, string $set = null)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');
        $client = $services->get('OaiPmhHarvester\Client');
        $settings = $services->get('Omeka\Settings');

        $configuration = $source->configuration();
        $converter = $configuration->converter();

        $updateMode = $source->updateMode();
        $updateModes = [
            self::UPDATE_MODE_REPLACE_ALL_METADATA,
            self::UPDATE_MODE_REPLACE_ALL_METADATA_BUT_ARK,
        ];
        if ($updateMode !== '' && !in_array($updateMode, $updateModes)) {
            $logger->warn(sprintf('Unknown update mode: "%s". Disabling update', $updateMode));
            $updateMode = '';
        }

        $properties = $api->search('properties')->getContent();
        $terms = array_map(fn($property) => $property->term(), $properties);

        $from = $this->getArg('from');
        $until = $this->getArg('until');

        $resumptionToken = null;
        do {
            if ($this->shouldStop()) {
                break;
            }

            if ($resumptionToken) {
                $query = ['resumptionToken' => $resumptionToken];
            } else {
                $query = ['metadataPrefix' => $source->metadataPrefix()];
                if ($from)
                    $query['from'] = $from;
                if ($until)
                    $query['until'] = $until;
                if ($set !== null) {
                    $query['set'] = $set;
                }
            }

            $t0 = microtime(true);
            $document = $client->listRecords($source->baseUrl(), $query);
            $elapsed = microtime(true) - $t0;

            $resumptionToken = $document->getResumptionToken();
            $records = $document->getRecords();

            if ($set) {
                $logger->info(sprintf('Fetched %d records for set %s in %.3f s', count($records), $set, $elapsed));
            } else {
                $logger->info(sprintf('Fetched %d records in %.3f s', count($records), $elapsed));
            }

            $toCreate = [];
            foreach ($records as $record) {
                if ($this->shouldStop()) {
                    break;
                }

                if ($this->isDeletedRecord($record)) {
                    $logger->info(sprintf('Skipping deleted record %s', $identifier));
                    continue;
                }

                $identifier = $record->getIdentifier();

                $response = $api->search(
                    'oaipmhharvester_source_records',
                    ['source_id' => $source->id(), 'identifier' => $identifier],
                    ['returnScalar' => 'item']
                );
                $itemIds = $response->getContent();

                if ($updateMode === '' && $response->getTotalResults() > 0) {
                    $logger->info(sprintf('Skipping record %s because it already exists (items: %s)', $identifier, implode(',', $itemIds)));
                    continue;
                }

                if ($updateMode !== '' && $response->getTotalResults() > 1) {
                    $logger->warn(sprintf('Record %s corresponds to several items. Update is not possible (items: %s)', $identifier, implode(',', $itemIds)));
                    continue;
                }


                $generator = $converter->convert($record, $configuration->settings());
                while ($generator->valid()) {
                    $itemData = $generator->current();
                    if (!is_array($itemData)) {
                        $logger->err('Converter did not return an array');
                        $generator->send(null);
                        continue;
                    }

                    $itemId = array_shift($itemIds);
                    if ($itemId) {
                        $item = $api->read('items', $itemId)->getContent();

                        if ($updateMode === self::UPDATE_MODE_REPLACE_ALL_METADATA) {
                            $partialItemData = array_filter($itemData, fn($key) => in_array($key, $terms), ARRAY_FILTER_USE_KEY);
                            $api->update('items', $itemId, $partialItemData, [], ['isPartial' => true]);

                            $logger->info(sprintf('Imported record %s (updated item #%d)', $identifier, $item->id()));
                        } elseif ($updateMode === self::UPDATE_MODE_REPLACE_ALL_METADATA_BUT_ARK) {
                            $partialItemData = array_filter($itemData, fn($key) => in_array($key, $terms), ARRAY_FILTER_USE_KEY);

                            $identifierValues = $item->value('dcterms:identifier', ['type' => 'literal', 'all' => true]);
                            $arkIdentifierValues = array_filter($identifierValues, fn($v) => str_starts_with($v->value(), 'ark:/'));

                            if ($arkIdentifierValues) {
                                $arkIdentifierValuesMap = [];
                                foreach ($arkIdentifierValues as $arkIdentifierValue) {
                                    $arkIdentifierValuesMap[$arkIdentifierValue->value()] = $arkIdentifierValue;
                                }

                                // Remove already existing ark identifiers from incoming data
                                $identifierValuesData = array_filter(
                                    $partialItemData['dcterms:identifier'] ?? [],
                                    fn($valueData) => $valueData['type'] !== 'literal' || !array_key_exists($valueData['@value'], $arkIdentifierValuesMap)
                                );

                                $partialItemData['dcterms:identifier'] = array_merge(
                                    array_map(fn($value) => json_decode(json_encode($value), true), $arkIdentifierValues),
                                    $identifierValuesData
                                );
                            }

                            $api->update('items', $itemId, $partialItemData, [], ['isPartial' => true]);

                            $logger->info(sprintf('Imported record %s (updated item #%d)', $identifier, $item->id()));
                        } else {
                            throw new \Exception(sprintf('Invalid update mode: %s', $updateMode));
                        }
                    } else {
                        if (!isset($itemData['o:is_public'])) {
                            $itemData['o:is_public'] = !$settings->get('default_to_private', false);
                        }

                        $response = $api->create('items', $itemData, [], ['continueOnError' => true]);
                        $item = $response->getContent();
                        $itemId = $item->id();

                        $sourceRecordData = [
                            'o:item' => ['o:id' => $itemId],
                            'o:source' => ['o:id' => $source->id()],
                            'o:identifier' => $identifier,
                        ];
                        $api->create('oaipmhharvester_source_records', $sourceRecordData);

                        $logger->info(sprintf('Imported record %s (created item #%d)', $identifier, $item->id()));
                    }

                    $this->importedRecords++;

                    $generator->send($item->id());
                }
            }
        } while ($resumptionToken);
    }

    protected function isDeletedRecord(OaiRecord $record): bool
    {
        $status = $record->getDOMXPath()->evaluate('string(oai:header/@status)', $record->getDOMElement());

        return $status === 'deleted';
    }
}
