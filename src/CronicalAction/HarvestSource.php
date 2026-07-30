<?php

namespace OaiPmhHarvester\CronicalAction;

use Cronical\Api\Representation\ScheduledActionRepresentation;
use Cronical\Api\Representation\ScheduledActionRunRepresentation;
use Cronical\Action\AbstractJobDispatchAction;
use DateTime;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\View\Renderer\PhpRenderer;
use OaiPmhHarvester\Entity\Source;

class HarvestSource extends AbstractJobDispatchAction
{
    public function getLabel(): string
    {
        return 'Harvest source'; // @translate
    }

    public function getGroupLabel(): string
    {
        return 'OAI-PMH Harvester'; // @translate
    }

    public function getDescription(): string
    {
        return 'Harvest an OAI-PMH source'; // @translate
    }

    public function perform(ScheduledActionRunRepresentation $scheduledActionRun): void
    {
        $jobClass = $this->getJobClass($scheduledActionRun);
        $jobArgs = $this->getJobArgs($scheduledActionRun);

        $job = $this->getJobDispatcher()->dispatch($jobClass, $jobArgs);

        $em = $this->getEntityManager();

        $scheduledActionRunEntity = $em->find('Cronical\Entity\ScheduledActionRun', $scheduledActionRun->id());
        $scheduledActionRunEntity->setJob($job);

        $sourceId = $scheduledActionRun->setting('source');
        $sourceEntity = $em->find('OaiPmhHarvester\Entity\Source', $sourceId);
        $sourceEntity->getJobs()->add($job);

        $em->flush();
    }

    public function getJobClass(ScheduledActionRunRepresentation $scheduledActionRun): string
    {
        return \OaiPmhHarvester\Job\HarvestSource::class;
    }

    protected function getJobArgs(ScheduledActionRunRepresentation $scheduledActionRun): array
    {
        $sourceId = $scheduledActionRun->setting('source');
        $from = $scheduledActionRun->setting('from', '');

        if ($from === 'last_harvest') {
            $em = $this->getEntityManager();
            $source = $em->find(Source::class, $sourceId);
            $lastJob = $source->getJobs()->last();
            if ($lastJob) {
                $from = $lastJob->getStarted()->format('Y-m-d');
            } else {
                $from = '';
            }
        } elseif ($from === 'last_n_days') {
            $days = intval($scheduledActionRun->setting('from_days', '0'));
            $date = new DateTime(sprintf('now - %d days', $days));
            $from = $date->format('Y-m-d');
        }

        return [
            'source_id' => $sourceId,
            'owner_id' => $scheduledActionRun->setting('owner_id'),
            'from' => $from,
        ];
    }

    public function formAddElements(Form $form, ScheduledActionRepresentation $scheduledAction): void
    {
        $em = $this->getEntityManager();
        $sources = $em->getRepository(Source::class)->findAll();
        $sourcesValueOptions = [];
        foreach ($sources as $source) {
            $harvestSourcesValueOptions[$source->getId()] = $source->getName();
        }

        $form->get('o:settings')->add([
            'name' => 'source',
            'type' => 'Laminas\Form\Element\Select',
            'options' => [
                'label' => 'Source', // @translate
                'value_options' => $harvestSourcesValueOptions,
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        $form->get('o:settings')->add([
            'name' => 'from',
            'type' => 'Laminas\Form\Element\Select',
            'options' => [
                'label' => 'Selective harvesting', // @translate
                'value_options' => [
                    '' => 'No (harvest all records)', // @translate
                    'last_harvest' => 'Harvest records added or modified since last harvest', // @translate
                    'last_n_days' => 'Harvest records added or modified since <n> days', // @translate
                ],
            ],
        ]);

        $form->get('o:settings')->add([
            'name' => 'from_days',
            'type' => 'Laminas\Form\Element\Text',
            'options' => [
                'label' => 'Selective harvesting (days)', // @translate
                'info' => 'If selective harvesting is set to "Harvest records added or modified since <n> days", this parameter controls the number of days.', // @translate
            ],
        ]);

        $form->get('o:settings')->add([
            'name' => 'owner_id',
            'type' => 'Omeka\Form\Element\UserSelect',
            'options' => [
                'label' => 'Owner', // @translate
                'info' => 'Owner of resources created. If left empty, the scheduled action owner will be the default owner of resources created.', // @translate
                'empty_option' => '',
            ],
        ]);
    }

    public function formAddInputFilters(InputFilterInterface $inputFilter, ScheduledActionRepresentation $scheduledAction): void
    {
        $inputFilter->get('o:settings')->add([
            'name' => 'from',
            'required' => false,
        ]);
        $inputFilter->get('o:settings')->add([
            'name' => 'owner_id',
            'required' => false,
        ]);
    }

    public function onViewShow(PhpRenderer $view, ScheduledActionRepresentation $scheduledAction): void
    {
        echo $view->partial('oai-pmh-harvester/common/cronical-action/harvest-source/show', ['scheduledAction' => $scheduledAction]);
    }

    public function onViewDetails(PhpRenderer $view, ScheduledActionRepresentation $scheduledAction): void
    {
        echo $view->partial('oai-pmh-harvester/common/cronical-action/harvest-source/details', ['scheduledAction' => $scheduledAction]);
    }
}
