<?php
/**
 * Copyright (c) 2017 Constantin Galbenu <xprt64@gmail.com>
 */

namespace Gica\Cqrs\Saga;


use Gica\CodeAnalysis\MethodListenerDiscovery;
use Gica\CodeAnalysis\MethodListenerDiscovery\ListenerClassValidator\AnyPhpClassIsAccepted;
use Gica\CodeAnalysis\MethodListenerDiscovery\ListenerMethod;
use Gica\Cqrs\Command\CodeAnalysis\WriteSideEventHandlerDetector;
use Gica\Cqrs\Event\EventWithMetaData;
use Gica\Cqrs\EventProcessing\ConcurentEventProcessingException;
use Gica\Cqrs\EventStore;
use Gica\Cqrs\ProgressReporting\TaskProgressCalculator;
use Gica\Cqrs\ProgressReporting\TaskProgressReporter;
use Gica\Cqrs\Saga\SagaRunner\EventProcessingHasStalled;
use Psr\Log\LoggerInterface;

/**
 * This class can be run in background to feed a saga with events
 */
class SagaRunner
{

    /**
     * @var EventStore
     */
    private $eventStore;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var SagaEventTrackerRepository
     */
    private $sagaRepository;
    /**
     * @var TaskProgressReporter|null
     */
    private $taskProgressReporter;

    public function __construct(
        EventStore $eventStore,
        LoggerInterface $logger,
        SagaEventTrackerRepository $sagaRepository
    )
    {
        $this->eventStore = $eventStore;
        $this->logger = $logger;
        $this->sagaRepository = $sagaRepository;
    }

    public function setTaskProgressReporter(?TaskProgressReporter $taskProgressReporter)
    {
        $this->taskProgressReporter = $taskProgressReporter;
    }

    public function feedSagaWithEvents($saga, ?int $afterSequence = null)
    {
        $discoverer = new MethodListenerDiscovery(
            new WriteSideEventHandlerDetector(),
            new AnyPhpClassIsAccepted()
        );

        $allMethods = $discoverer->findListenerMethodsInClass(get_class($saga));

        $eventClasses = $this->getEventClassesFromMethods($allMethods);

        $this->logger->info(print_r($eventClasses, true));
        $this->logger->info("loading events...");

        $allEvents = $this->eventStore->loadEventsByClassNames($eventClasses);

        if (null !== $afterSequence) {
            $allEvents->afterSequence($afterSequence);
        }

        $this->logger->info("processing events...");

        $taskProgress = null;

        if ($this->taskProgressReporter) {
            $taskProgress = new TaskProgressCalculator($allEvents->countCommits());
        }

        foreach ($allEvents->fetchCommits() as $eventsCommit) {

            $eventsCommit = $eventsCommit->filterEventsByClass($eventClasses);

            foreach ($eventsCommit->getEventsWithMetadata() as $eventWithMetadata) {
                $this->processEvent($saga, $eventWithMetadata, $allMethods);
            }

            if ($this->taskProgressReporter) {
                $taskProgress->increment();
                $this->taskProgressReporter->reportProgressUpdate($taskProgress->getStep(), $taskProgress->getTotalSteps(), $taskProgress->calculateSpeed(), $taskProgress->calculateEta());
            }
        }
    }

    /**
     * @param ListenerMethod[] $methods
     * @return array
     */
    private function getEventClassesFromMethods($methods)
    {
        $eventClasses = [];
        foreach ($methods as $listenerMethod) {
            $eventClasses[] = $listenerMethod->getEventClassName();
        }

        return $eventClasses;
    }

    /**
     * @param string $eventClass
     * @param ListenerMethod[] $allMethods
     * @return ListenerMethod[]
     */
    private function findMethodsByEventClass(string $eventClass, $allMethods)
    {
        $result = [];

        foreach ($allMethods as $listenerMethod) {
            if ($listenerMethod->getEventClassName() == $eventClass) {
                $result[] = $listenerMethod;
            }
        }

        return $result;
    }

    private function processEvent($saga, EventWithMetaData $eventWithMetadata, $allMethods): void
    {
        /** @var EventWithMetaData $eventWithMetadata */
        $methods = $this->findMethodsByEventClass(get_class($eventWithMetadata->getEvent()), $allMethods);
        $metaData = $eventWithMetadata->getMetaData();

        $sagaId = get_class($saga);

        foreach ($methods as $method) {

            try {
                if ($this->sagaRepository->isEventProcessingAlreadyStarted($sagaId, $metaData->getEventId())) {
                    if (!$this->sagaRepository->isEventProcessingAlreadyEnded($sagaId, $metaData->getEventId())) {
                        throw new EventProcessingHasStalled($eventWithMetadata);
                    }
                } else {
                    $this->sagaRepository->startProcessingEvent($sagaId, $metaData->getEventId());
                    call_user_func([$saga, $method->getMethodName()], $eventWithMetadata->getEvent(), $eventWithMetadata->getMetaData());
                    $this->sagaRepository->endProcessingEvent($sagaId, $metaData->getEventId());
                }
            } catch (ConcurentEventProcessingException $exception) {
                $this->logger->info("concurent event processing exception, skipping...");
                continue;
            }
        }
    }
}