<?php
/******************************************************************************
 * Copyright (c) 2016 Constantin Galbenu <gica.galbenu@gmail.com>             *
 ******************************************************************************/

namespace Dudulina\Event;


use Dudulina\Command\CommandMetadata;

class MetaData
{

    /**
     * @var \DateTimeImmutable
     */
    private $dateCreated;

    private $aggregateId;
    private $authenticatedUserId;

    /* @var string */
    private $aggregateClass;

    /** @var CommandMetadata|null */
    private $commandMetadata;

    /** @var int */
    private $sequence = null;

    /** @var int */
    private $index = null;
    /**
     * @var string|null
     */
    private $eventId;

    public function __construct(
        $aggregateId,
        string $aggregateClass,
        \DateTimeImmutable $dateCreated,
        $authenticatedUserId = null,
        CommandMetadata $commandMetadata = null
    )
    {
        $this->dateCreated = $this->addTimeZone($dateCreated);
        $this->aggregateId = $aggregateId;
        $this->authenticatedUserId = $authenticatedUserId;
        $this->aggregateClass = $aggregateClass;
        $this->commandMetadata = $commandMetadata;
    }

    public function getDateCreated(): \DateTimeImmutable
    {
        return $this->dateCreated;
    }

    public function getAggregateId()
    {
        return $this->aggregateId;
    }

    public function getAggregateClass()
    {
        return $this->aggregateClass;
    }

    public function getAuthenticatedUserId()
    {
        return $this->authenticatedUserId;
    }

    public function getCommandMetadata():?CommandMetadata
    {
        return $this->commandMetadata;
    }

    private function addTimeZone(\DateTimeImmutable $dateCreated): \DateTimeImmutable
    {
        return $dateCreated->getTimezone() ? $dateCreated :
            ($dateCreated->setTimezone(new \DateTimeZone('Europe/Bucharest')) ?: $dateCreated);
    }

    public function withSequenceAndIndex(int $sequence, int $index): self
    {
        $other = clone $this;
        $other->sequence = $sequence;
        $other->index = $index;
        return $other;
    }

    public function withEventId(string $eventId): self
    {
        $other = clone $this;
        $other->eventId = $eventId;
        return $other;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function getIndex(): ?int
    {
        return $this->index;
    }

    public function getEventId(): ?string
    {
        return $this->eventId;
    }
}