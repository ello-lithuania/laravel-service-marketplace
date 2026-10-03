<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Teikėjo kreditų balanso neužtenka operacijai (pvz. pasiūlymui).
 * Domeno išimtis, nepriklausoma nuo HTTP: ją gaudo Action ir paverčia lietuvišku validacijos pranešimu.
 */
class InsufficientCreditsException extends RuntimeException
{
    public function __construct(public readonly int $balance, public readonly int $required)
    {
        parent::__construct("Nepakanka kreditų: balansas {$balance}, reikia {$required}.");
    }
}
