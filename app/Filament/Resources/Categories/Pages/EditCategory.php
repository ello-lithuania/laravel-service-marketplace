<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Trynimo mygtuko nėra: kategorijas naudoja užklausos ir teikėjai (FK restrict), todėl jas išjungiam.
 */
class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    public function getTitle(): string
    {
        return 'Redaguoti kategoriją';
    }
}
