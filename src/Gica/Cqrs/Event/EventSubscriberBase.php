<?php
/******************************************************************************
 * Copyright (c) 2016 Constantin Galbenu <gica.galbenu@gmail.com>             *
 ******************************************************************************/

namespace Gica\Cqrs\Event;


use Gica\Dependency\AbstractFactory;

abstract class EventSubscriberBase implements \Gica\Cqrs\Event\EventSubscriber
{
    abstract protected function getMap():array;

    /** @var AbstractFactory */
    private $abstractFactory;

    public function __construct(
        AbstractFactory $abstractFactory
    )
    {
        $this->abstractFactory = $abstractFactory;
    }

    /**
     * @param array $listenerDescriptor
     * @return callable
     */
    private function createListenerByMethod(array $listenerDescriptor)
    {
        return [$this->abstractFactory->createObject($listenerDescriptor[0]), $listenerDescriptor[1]];
    }

    /**
     * @param array $listenersDescriptor
     * @return callable[]
     */
    private function createListenersByMethod(array $listenersDescriptor)
    {
        $result = [];

        foreach ($listenersDescriptor as $listenerDescriptor) {
            $result[] = $this->createListenerByMethod($listenerDescriptor);
        }

        return $result;
    }

    /**
     * @param \Gica\Cqrs\Event $event
     * @return callable[]
     */
    public function getListenersForEvent(\Gica\Cqrs\Event $event)
    {
        $eventClass = get_class($event);

        if (isset($this->getMap()[$eventClass])) {
            return $this->createListenersByMethod($this->getMap()[$eventClass]);
        }

        return [];
    }
}