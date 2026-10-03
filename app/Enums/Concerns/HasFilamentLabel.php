<?php

namespace App\Enums\Concerns;

/**
 * Leidžia Filament admin panelei rodyti lietuvišką enum'o pavadinimą (label()).
 */
trait HasFilamentLabel
{
    public function getLabel(): string
    {
        return $this->label();
    }
}
