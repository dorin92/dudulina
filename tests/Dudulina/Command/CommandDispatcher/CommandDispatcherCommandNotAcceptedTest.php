<?php
/******************************************************************************
 * Copyright (c) 2016 Constantin Galbenu <gica.galbenu@gmail.com>             *
 ******************************************************************************/

namespace tests\Dudulina\Command\CommandDispatcher\CommandDispatcherCommandNotAcceptedTest;


use Dudulina\Aggregate\AggregateRepository;
use Dudulina\Command;
use Dudulina\Command\CommandApplier;
use Dudulina\Command\CommandDispatcher;
use Dudulina\Command\CommandDispatcher\AuthenticatedIdentityReaderService;
use Dudulina\Command\CommandDispatcher\ConcurrentProofFunctionCaller;
use Dudulina\Command\CommandDispatcher\DefaultCommandDispatcher;
use Dudulina\Command\CommandSubscriber;
use Dudulina\Command\CommandValidator;
use Dudulina\Command\MetadataFactory\DefaultMetadataWrapper;
use Dudulina\Event\EventDispatcher\EventDispatcherBySubscriber;
use Dudulina\Event\EventsApplier\EventsApplierOnAggregate;
use Dudulina\Event\MetadataFactory\DefaultMetadataFactory;
use Dudulina\EventStore\InMemory\InMemoryEventStore;
use Dudulina\FutureEventsStore;

class CommandDispatcherCommandNotAcceptedTest extends \PHPUnit_Framework_TestCase
{

    const AGGREGATE_ID = 123;

    /** @var Command */
    private $command;

    protected function setUp()
    {
        $this->command = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->command->expects($this->any())
            ->method('getAggregateId')
            ->willReturn(self::AGGREGATE_ID);
    }

    public function test_dispatchCommand()
    {
        $aggregateId = self::AGGREGATE_ID;
        $aggregateClass = Aggregate1::class;

        $commandSubscriber = $this->mockCommandSubscriber();

        $eventDispatcher = $this->mockEventDispatcher();

        $eventStore = new InMemoryEventStore();

        $eventsApplierOnAggregate = new EventsApplierOnAggregate();

        $commandApplier = new CommandApplier();

        $aggregateRepository = new AggregateRepository($eventStore, $eventsApplierOnAggregate);

        $concurrentProofFunctionCaller = new ConcurrentProofFunctionCaller;

        /** @var \Dudulina\Command\CommandDispatcher\AuthenticatedIdentityReaderService $authenticatedIdentity */
        $authenticatedIdentity = $this->getMockBuilder(AuthenticatedIdentityReaderService::class)
            ->getMock();

        /** @var FutureEventsStore $futureEventsStore */
        $futureEventsStore = $this->getMockBuilder(FutureEventsStore::class)
            ->getMock();

        /** @var CommandValidator $commandValidator */
        $commandValidator = $this->getMockBuilder(CommandValidator::class)
            ->disableOriginalConstructor()
            ->getMock();

        Aggregate1::$expectedCommand = $this->command;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Command not accepted");

        $commandDispatcher =  new DefaultCommandDispatcher(
            $commandSubscriber,
            $eventDispatcher,
            $commandApplier,
            $aggregateRepository,
            $concurrentProofFunctionCaller,
            $eventsApplierOnAggregate,
            new DefaultMetadataFactory(),
            new DefaultMetadataWrapper()
        );

        $commandDispatcher->dispatchCommand($this->command);
    }

    private function mockCommandSubscriber(): CommandSubscriber
    {
        $commandSubscriber = $this->getMockBuilder(CommandSubscriber::class)
            ->getMock();

        $commandSubscriber->expects($this->any())
            ->method('getHandlerForCommand')
            ->with($this->equalTo($this->command))
            ->willReturn(new Command\ValueObject\CommandHandlerDescriptor(
                Aggregate1::class,
                'handleCommand1'
            ));

        /** @var CommandSubscriber $commandSubscriber */
        return $commandSubscriber;
    }

    private function mockEventDispatcher(): EventDispatcherBySubscriber
    {
        $eventDispatcher = $this->getMockBuilder(EventDispatcherBySubscriber::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventDispatcher->expects($this->never())
            ->method('dispatchEvent');

        /** @var EventDispatcherBySubscriber $eventDispatcher */
        return $eventDispatcher;
    }
}

class Command1 implements \Dudulina\Command
{
    /**
     * @var
     */
    private $aggregateId;

    public function __construct(
        $aggregateId
    )
    {
        $this->aggregateId = $aggregateId;
    }

    public function getAggregateId()
    {
        return $this->aggregateId;
    }
}

class Aggregate1
{

    /**
     * @var Command
     */
    public static $expectedCommand;

    public function handleCommand1($command1)
    {
        if ($command1 != self::$expectedCommand) {
            throw new \Exception("Command not expected");
        }

        throw new \Exception("Command not accepted");

        /** @noinspection PhpUnreachableStatementInspection */
        yield "event";
    }
}