<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Skundas jau išnagrinėtas (arba jį ką tik paėmė kitas administratorius) – būsena pasikeitė tarp puslapio
 * atidarymo ir paspaudimo. Filament veiksmai ją pagauna ir parodo pranešimą, o ne 500 klaidą.
 */
class ComplaintAlreadyHandledException extends RuntimeException
{
    public static function make(): self
    {
        return new self(__('complaints.errors.already_handled'));
    }
}
