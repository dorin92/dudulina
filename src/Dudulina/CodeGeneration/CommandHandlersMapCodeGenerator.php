<?php
/******************************************************************************
 * Copyright (c) 2017 Constantin Galbenu <gica.galbenu@gmail.com>             *
 ******************************************************************************/

namespace Dudulina\CodeGeneration;

use Gica\CodeAnalysis\MethodListenerDiscovery;
use Gica\CodeAnalysis\MethodListenerDiscovery\ListenerClassValidator\AnyPhpClassIsAccepted;
use Gica\CodeAnalysis\MethodListenerDiscovery\MapGrouper\GrouperByEvent;
use Dudulina\CodeGeneration\Traits\GroupedByEventTrait;
use Dudulina\Command\CodeAnalysis\AggregateCommandHandlerDetector;

class CommandHandlersMapCodeGenerator
{
    use GroupedByEventTrait;

    protected function log($outputFilePath)
    {
        $this->logger->info("Commands map wrote to: $outputFilePath");
    }

    private function validateMap(array $map)
    {
        foreach ($map as $command => $commandHandlers) {
            if (count($commandHandlers) > 1) {
                throw new \Exception(
                    sprintf("multiple handlers exists for command %s", $command));
            }
        }
    }

    protected function discover(\Iterator $files)
    {
        $discoverer = new MethodListenerDiscovery(
            new AggregateCommandHandlerDetector(),
            new AnyPhpClassIsAccepted);

        $map = $discoverer->discoverListeners($files);

        $this->validateMap($this->groupMap($map));

        return $map;
    }

    private function groupMap(array $map)
    {
        return (new GrouperByEvent())->groupMap($map);
    }
}