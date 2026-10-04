<?php

namespace App\Actions\Photos;

use App\Services\Photos\LibraryPhoto;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Prisega nuotrauką iš vietinės bibliotekos (storage/app/stock-photos) prie modelio – kategorijos ar svetainės
 * vietos – su autoriaus duomenimis (custom_properties.credit) ir stock_id.
 *
 * preservingOriginal(): medialibrary failą nukopijuoja, o ne perkelia – bibliotekos originalas lieka kitam kartui
 * (po migrate:fresh --seed). Kolekcijos singleFile, todėl sena nuotrauka pakeičiama automatiškai.
 */
final class AttachLibraryPhoto
{
    /**
     * @param  array<string, mixed>  $attributes  papildomi media stulpeliai: seed'e – uuid, nes modelių įvykiai
     *                                            išjungti (WithoutModelEvents), o uuid medialibrary priskiria įvykyje
     */
    public function handle(HasMedia $model, string $collection, LibraryPhoto $photo, array $attributes = []): Media
    {
        return $model->addMedia($photo->path)
            ->preservingOriginal()
            ->usingName(pathinfo($photo->fileName, PATHINFO_FILENAME))
            ->usingFileName($photo->fileName)
            ->withCustomProperties($photo->customProperties())
            ->setOrder(1)
            ->withAttributes($attributes)
            ->toMediaCollection($collection);
    }
}
