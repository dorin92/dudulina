<?php
/******************************************************************************
 * Copyright (c) 2016 Constantin Galbenu <gica.galbenu@gmail.com>             *
 ******************************************************************************/

namespace Gica\Cqrs\Command\CommandDispatcher;


use Gica\Cqrs\Command\Exception\TooManyCommandExecutionRetries;
use Gica\Cqrs\EventStore\Exception\ConcurrentModificationException;

class ConcurrentProofFunctionCaller
{
    public function executeFunction($pureFunction, $maxRetries, array $arguments = [])
    {
        $retries = -1;
        do {
            try {

                /**
                 * The real function call
                 */
                return call_user_func($pureFunction, $arguments);

            } catch (ConcurrentModificationException $e) {

                $retries++;
                if ($retries >= $maxRetries) {
                    throw new TooManyCommandExecutionRetries(sprintf("TooManyCommandExecutionRetries: %d (%s)", $retries, $e->getMessage()));
                }

                continue;//retry
            }

        } while (true);
    }
}