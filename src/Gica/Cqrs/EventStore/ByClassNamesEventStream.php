<?php


namespace Gica\Cqrs\EventStore;


interface ByClassNamesEventStream extends EventStream
{
    public function limitCommits(int $limit);

    public function afterSequence(int $sequenceNumber);

    public function beforeSequence(int $sequenceNumber);

    public function countCommits():int;

    /**
     * @return EventsCommit[]|\ArrayIterator
     */
    public function fetchCommits();
}