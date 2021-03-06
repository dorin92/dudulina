<?php
/**
 * Copyright (c) 2017 Constantin Galbenu <xprt64@gmail.com>
 */

namespace tests\Dudulina\Saga;

use Dudulina\Event;
use Dudulina\Event\EventWithMetaData;
use Dudulina\Event\MetaData;
use Dudulina\EventProcessing\ConcurentEventProcessingException;
use Dudulina\EventStore;
use Dudulina\EventStore\EventStreamGroupedByCommit;
use Dudulina\ProgressReporting\TaskProgressReporter;
use Dudulina\Saga\SagaEventTrackerRepository;
use Dudulina\Saga\SagaRunner;
use Dudulina\Saga\SagaRunner\EventProcessingHasStalled;
use Gica\Types\Guid;
use Psr\Log\LoggerInterface;

class SagaRunnerTest extends \PHPUnit_Framework_TestCase
{
    private function factoryMetadata(string $eventId)
    {
        return (new MetaData('', '', new \DateTimeImmutable('2017-01-01 00:00:00')))->withEventId($eventId);
    }

    public function test()
    {
        $eventStore = $this->getMockBuilder(EventStore::class)
            //->setMethods(['loadEventsByClassNames'])
            ->getMock();

        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();

        $eventStream = $this->getMockBuilder(EventStreamGroupedByCommit::class)->getMock();

        $eventId1 = Guid::generate();
        $eventId2 = Guid::generate();

        $eventStream->method('getIterator')
            ->willReturn(new \ArrayIterator([
                new EventWithMetaData(new Event1(), $this->factoryMetadata($eventId1)),
                new EventWithMetaData(new Event2(), $this->factoryMetadata($eventId2)),
            ]));

        $eventStore->expects($this->once())
            ->method('loadEventsByClassNames')
            ->willReturn($eventStream);

        $eventStream->method('fetchCommits')
            ->willReturn([
                new EventStore\EventsCommit(0,0, [new EventWithMetaData(new Event1(), $this->factoryMetadata($eventId1))]),
                new EventStore\EventsCommit(1,1, [new EventWithMetaData(new Event2(), $this->factoryMetadata($eventId2))]),
            ]);

        $saga = new MySaga();

        $repository = $this->getMockBuilder(SagaEventTrackerRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repository->method('isEventProcessingAlreadyStarted')
            ->with(get_class($saga))
            ->willReturnCallback(function (string $sagaId, string $eventId) use ($eventId1) {
                return $eventId == $eventId1;
            });

        $repository->method('isEventProcessingAlreadyEnded')
            ->with(get_class($saga))
            ->willReturnCallback(function (string $sagaId, string $eventId) use ($eventId1) {
                return $eventId == $eventId1;
            });

        $repository->expects($this->once())
            ->method('startProcessingEvent');

        $repository->expects($this->once())
            ->method('endProcessingEvent');

        /** @var SagaEventTrackerRepository $repository */
        /** @var LoggerInterface $logger */
        /** @var EventStore $eventStore */

        $sut = new SagaRunner(
            $eventStore,
            $logger,
            $repository
        );

        $sut->feedSagaWithEvents($saga);

        $this->assertSame(0, $saga->event1Called);
        $this->assertSame(1, $saga->event2Called);
    }

    public function test_ConcurentModificationException()
    {
        $eventId1 = "1";
        $eventId2 = "2";

        $eventStore = $this->getMockBuilder(EventStore::class)
            //->setMethods(['loadEventsByClassNames'])
            ->getMock();

        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();

        $eventStream = $this->getMockBuilder(EventStreamGroupedByCommit::class)->getMock();

        $eventStream->method('fetchCommits')
            ->willReturn(new \ArrayIterator([
                new EventStore\EventsCommit(0,0, [new EventWithMetaData(new Event1(), $this->factoryMetadata($eventId1))]),
                new EventStore\EventsCommit(1,1, [new EventWithMetaData(new Event2(), $this->factoryMetadata($eventId2))]),
            ]));

        $eventStore->expects($this->once())
            ->method('loadEventsByClassNames')
            ->willReturn($eventStream);

        $taskReporter = $this->getMockBuilder(TaskProgressReporter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $saga = new MySaga();

        $repository = $this->getMockBuilder(SagaEventTrackerRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repository->method('isEventProcessingAlreadyStarted')
            ->with(get_class($saga))
            ->willReturn(false);

        $repository->method('startProcessingEvent')
            ->willThrowException(new ConcurentEventProcessingException());

        $repository->expects($this->never())
            ->method('endProcessingEvent');

        /** @var SagaEventTrackerRepository $repository */
        /** @var LoggerInterface $logger */
        /** @var EventStore $eventStore */

        $sut = new SagaRunner(
            $eventStore,
            $logger,
            $repository
        );

        $sut->setTaskProgressReporter($taskReporter);

        $sut->feedSagaWithEvents($saga);

        $this->assertSame(0, $saga->event1Called);
        $this->assertSame(0, $saga->event2Called);
    }


    public function test_EventProcessingHasStalled()
    {
        $eventStore = $this->getMockBuilder(EventStore::class)
            //->setMethods(['loadEventsByClassNames'])
            ->getMock();

        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();

        $eventStream = $this->getMockBuilder(EventStreamGroupedByCommit::class)->getMock();

        $eventStream->method('fetchCommits')
            ->willReturn([
                new EventStore\EventsCommit(0,0, [new EventWithMetaData(new Event1(), $this->factoryMetadata("1"))]),
            ]);

        $eventStore->expects($this->once())
            ->method('loadEventsByClassNames')
            ->willReturn($eventStream);

        $saga = new MySaga();

        $repository = $this->getMockBuilder(SagaEventTrackerRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repository->method('isEventProcessingAlreadyStarted')
            ->with(get_class($saga))
            ->willReturn(true);

        $repository->method('isEventProcessingAlreadyEnded')
            ->with(get_class($saga))
            ->willReturn(false);

        $this->expectException(EventProcessingHasStalled::class);

        $repository
            ->expects($this->never())
            ->method('startProcessingEvent');

        $repository
            ->expects($this->never())
            ->method('endProcessingEvent');

        /** @var SagaEventTrackerRepository $repository */
        /** @var LoggerInterface $logger */
        /** @var EventStore $eventStore */

        $sut = new SagaRunner(
            $eventStore,
            $logger,
            $repository
        );

        $sut->feedSagaWithEvents($saga);

        $this->assertSame(0, $saga->event1Called);
    }

    public function test_afterSequence()
    {
        $eventStore = $this->getMockBuilder(EventStore::class)
            //->setMethods(['loadEventsByClassNames'])
            ->getMock();

        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();

        $eventStream = $this->getMockBuilder(EventStreamGroupedByCommit::class)->getMock();

        $eventStream
            ->expects($this->once())
            ->method('fetchCommits')
            ->willReturn([
            ]);

        $eventStream
            ->expects($this->once())
            ->method('afterSequence')
            ->with(4);

        $eventStore->expects($this->once())
            ->method('loadEventsByClassNames')
            ->willReturn($eventStream);

        $saga = new MySaga();

        $repository = $this->getMockBuilder(SagaEventTrackerRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repository
            ->expects($this->never())
            ->method('isEventProcessingAlreadyStarted');

        /** @var SagaEventTrackerRepository $repository */
        /** @var LoggerInterface $logger */
        /** @var EventStore $eventStore */

        $sut = new SagaRunner(
            $eventStore,
            $logger,
            $repository
        );

        $sut->feedSagaWithEvents($saga, 4);

        $this->assertSame(0, $saga->event1Called);
        $this->assertSame(0, $saga->event2Called);
    }
}

class MySaga
{
    public $event1Called = 0;
    public $event2Called = 0;

    public function processEvent1(Event1 $event)
    {
        $this->event1Called++;
    }

    public function processEvent2(Event2 $event)
    {
        $this->event2Called++;
    }
}

class Event1 implements Event
{

}


class Event2 implements Event
{

}