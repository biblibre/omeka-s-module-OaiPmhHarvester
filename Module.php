<?php declare(strict_types=1);

namespace OaiPmhHarvester;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;
use Omeka\Stdlib\Message;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $services): void
    {
        $this->setServiceLocator($services);
        $connection = $services->get('Omeka\Connection');
        $plugins = $services->get('ControllerPluginManager');
        $messenger = $plugins->get('messenger');

        $this->execSqlFromFile(__DIR__ . '/data/install/schema.sql');

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        if (!is_dir($basePath) || !is_readable($basePath) || !is_writeable($basePath)) {
            $message = new Message(
                'The directory "%s" is not writeable, so the oai-pmh xml responses won’t be storable.', // @translate
                $basePath
            );
            $messenger->addWarning($message);
        }
        $dir = $basePath . '/oai-pmh-harvest';
        if (!file_exists($dir)) {
            mkdir($dir);
        }

        $connection->insert('oaipmhharvester_configuration', [
            'name' => 'Built-in mappings for oai_dc (not configurable)',
            'converter_name' => 'oai_dc',
            'settings' => '{}',
        ]);

        $connection->insert('oaipmhharvester_configuration', [
            'name' => 'Built-in mappings for oai_dcterms (not configurable)',
            'converter_name' => 'oai_dcterms',
            'settings' => '{}',
        ]);

        $connection->insert('oaipmhharvester_configuration', [
            'name' => 'Built-in mappings for mets (not configurable)',
            'converter_name' => 'mets',
            'settings' => '{}',
        ]);

        $dcProperties = [
            'contributor', 'coverage', 'creator', 'date', 'description', 'format', 'identifier', 'language',
            'publisher', 'relation', 'rights', 'source', 'subject', 'title', 'type'
        ];
        $dcMappings = array_map(function ($name) {
            return ['name' => 'xpath', 'xpath' => ".//dc:$name", 'property' => "dcterms:$name", 'type' => 'literal'];
        }, $dcProperties);

        $connection->insert('oaipmhharvester_configuration', [
            'name' => 'oai_dc',
            'converter_name' => 'xpath',
            'settings' => json_encode([
                'namespaces' => [
                    'dc' => 'http://purl.org/dc/elements/1.1/',
                ],
                'mappings' => $dcMappings,
            ]),
        ]);

        $dctermsProperties = [
            'abstract', 'accessRights', 'accrualMethod', 'accrualPeriodicity', 'accrualPolicy', 'alternative',
            'audience', 'available', 'bibliographicCitation', 'conformsTo', 'contributor', 'coverage', 'created',
            'creator', 'date', 'dateAccepted', 'dateCopyrighted', 'dateSubmitted', 'description', 'educationLevel',
            'extent', 'format', 'hasFormat', 'hasPart', 'hasVersion', 'identifier', 'instructionalMethod', 'isFormatOf',
            'isPartOf', 'isReferencedBy', 'isReplacedBy', 'isRequiredBy', 'issued', 'isVersionOf', 'language',
            'license', 'mediator', 'medium', 'modified', 'provenance', 'publisher', 'references', 'relation',
            'replaces', 'requires', 'rights', 'rightsHolder', 'source', 'spatial', 'subject', 'tableOfContents',
            'temporal', 'title', 'type', 'valid'
        ];
        $dctermsMappings = array_map(function ($name) {
            return ['name' => 'xpath', 'xpath' => ".//dcterms:$name", 'property' => "dcterms:$name", 'type' => 'literal'];
        }, $dctermsProperties);

        $connection->insert('oaipmhharvester_configuration', [
            'name' => 'oai_dcterms',
            'converter_name' => 'xpath',
            'settings' => json_encode([
                'namespaces' => [
                    'dcterms' => 'http://purl.org/dc/terms/',
                ],
                'mappings' => $dctermsMappings,
            ]),
        ]);
    }

    public function uninstall(ServiceLocatorInterface $services): void
    {
        $this->setServiceLocator($services);
        $this->execSqlFromFile(__DIR__ . '/data/install/uninstall.sql');
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $services): void
    {
        $this->setServiceLocator($services);
        require_once __DIR__ . '/data/scripts/upgrade.php';
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Manage the deletion of an item.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.delete.pre',
            [$this, 'handleBeforeDelete'],
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.advanced_search',
            [$this, 'onItemViewAdvancedSearch']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.search.filters',
            [$this, 'onItemViewSearchFilters']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.details',
            [$this, 'onItemViewShowDetails']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.sidebar',
            [$this, 'onItemViewShowSidebar']
        );

        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.search.query',
            [$this, 'onItemApiSearchQuery']
        );

        $sharedEventManager->attach(
            'Omeka\Api\Adapter\JobAdapter',
            'api.search.query',
            [$this, 'onJobApiSearchQuery']
        );
    }

    /**
     * Execute a sql from a file.
     *
     * @param string $filepath
     * @return mixed
     */
    protected function execSqlFromFile($filepath)
    {
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $sql = file_get_contents($filepath);
        $sqls = array_filter(array_map('trim', explode(";\n", $sql)));
        foreach ($sqls as $sql) {
            $result = $connection->executeStatement($sql);
        }
        return $result;
    }

    public function handleBeforeDelete(Event $event): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Api\Request $request
         */
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $request = $event->getParam('request');
        $resourceId = $request->getId();
        $resourceName = $request->getResource();
        try {
            $api
                ->delete(
                    'oaipmhharvester_entities',
                    [
                        'entityId' => $resourceId,
                        'entityName' => $resourceName,
                    ],
                    [],
                    [
                        // The flush is automatically done on main resource
                        // execution, or skipped when failing.
                        'flushEntityManager' => false,
                    ]
                );
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
        }
    }

    public function onItemViewAdvancedSearch(Event $event)
    {
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        $partials = $event->getParam('partials');

        $partials[] = 'oai-pmh-harvester/common/advanced-search/source';

       if($acl->userIsAllowed()){
            $event->setParam('partials', $partials);
        };
    }

    public function onItemViewSearchFilters(Event $event)
    {
        $view = $event->getTarget();
        $query = $event->getParam('query');
        $filters = $event->getParam('filters');

        $ids = $query['oaipmhharvester_source_id'] ?? [];
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $ids = array_filter($ids);
        if ($ids) {
            try {
                $api = $this->getServiceLocator()->get('Omeka\ApiManager');
                $values = [];
                $sources = $api->search('oaipmhharvester_sources', ['id' => $ids])->getContent();
                $names = array_map(fn($source) => $source->name(), $sources);
                $filters[$view->translate('OAI-PMH Source')] = $names;
            } catch (\Exception $e) {
            }
        }

        $event->setParam('filters', $filters);
    }

    public function onItemViewShowDetails(Event $event)
    {
        $view = $event->getTarget();
        $item = $event->getParam('entity');

        try {
            $sourceRecord = $view->api()->searchOne('oaipmhharvester_source_records', ['item_id' => $item->id()])->getContent();

            if ($sourceRecord) {
                echo $view->partial('oai-pmh-harvester/common/item-details', ['item' => $item, 'sourceRecord' => $sourceRecord]);
            }
        } catch (\Exception $e) {
        }
    }

    public function onItemViewShowSidebar(Event $event)
    {
        $view = $event->getTarget();
        $item = $view->item;

        try {
            $sourceRecord = $view->api()->searchOne('oaipmhharvester_source_records', ['item_id' => $item->id()])->getContent();

            if ($sourceRecord) {
                echo $view->partial('oai-pmh-harvester/common/item-details', ['item' => $item, 'sourceRecord' => $sourceRecord]);
            }
        } catch (\Exception $e) {
        }
    }

    public function onItemApiSearchQuery(Event $event)
    {
        $adapter = $event->getTarget();
        $qb = $event->getParam('queryBuilder');
        $request = $event->getParam('request');

        $ids = $request->getValue('oaipmhharvester_source_id', []);
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $ids = array_filter($ids);
        if ($ids) {
            try {
                $subQb = $adapter->getEntityManager()->createQueryBuilder();
                $subQb->select('r')
                    ->from('OaiPmhHarvester\Entity\SourceRecord', 'r')
                    ->where($subQb->expr()->in('r.source', $ids))
                    ->andWhere('r.item = omeka_root');

                $qb->andWhere($qb->expr()->exists($subQb->getDQL()));
            } catch (\Exception $e) {
            }
        }
    }

    public function onJobApiSearchQuery(Event $event)
    {
        $adapter = $event->getTarget();
        $qb = $event->getParam('queryBuilder');
        $request = $event->getParam('request');

        $ids = $request->getValue('oaipmhharvester_source_id', []);
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $ids = array_filter($ids);
        if ($ids) {
            $subQb = $adapter->getEntityManager()->createQueryBuilder();
            $subQb->select('j')
                  ->from('OaiPmhHarvester\Entity\Source', 's')
                  ->innerJoin('s.jobs', 'j')
                  ->where($subQb->expr()->in('s.id', $ids))
                  ->andWhere('j = omeka_root');
            $qb->andWhere($qb->expr()->exists($subQb->getDQL()));
        }
    }
}
