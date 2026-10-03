<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Privatūs failai (Etapas 6): žinučių priedai ir užklausų nuotraukos gyvena „local" diske
 * (storage/app/private), kurio naršyklė tiesiogiai nepasiekia. Failą atiduodam tik tam, kas mato jo
 * savininką: žinutę – pokalbio dalyviai (MessagePolicy::view), užklausą – klientas ir tinkami teikėjai
 * (ServiceRequestPolicy::view). Taip nuotrauka iš kliento namų nepasiekiama atspėjus URL.
 *
 * WordPress analogas – failų apsauga per PHP „proxy" vietoj tiesioginės nuorodos į wp-content/uploads.
 * https://laravel.com/docs/13.x/filesystem#file-downloads
 */
class PrivateMediaController extends Controller
{
    /** Kolekcijos, kurias atiduoda šis controller'is. Kitos (avataras, portfolio) – vieši failai. */
    private const COLLECTIONS = ['attachments', 'photos'];

    public function __invoke(Media $media, ?string $conversion = null): StreamedResponse
    {
        abort_unless(in_array($media->collection_name, self::COLLECTIONS, true), 404);

        // Ištrinto (ar administratoriaus paslėpto) savininko failas nebeatiduodamas
        $owner = $media->model;
        abort_if($owner === null, 404);

        Gate::authorize('view', $owner);

        // Miniatiūros dar nėra (daroma eilėje) – atiduodam originalą
        $conversion = $conversion !== null && $media->hasGeneratedConversion($conversion) ? $conversion : '';
        $disk = $conversion === '' ? $media->disk : $media->conversions_disk;
        $isImage = str_starts_with($media->mime_type, 'image/');

        return Storage::disk($disk)->response(
            $media->getPathRelativeToRoot($conversion),
            $media->file_name,
            [
                // Tik naršyklės (ne bendrinamo proxy) cache: failas privatus
                'Cache-Control' => 'private, max-age=3600',
                // Naršyklė neturi „spėti" tipo (pvz. HTML failą, apsimetantį nuotrauka, vykdyti kaip puslapį)
                'X-Content-Type-Options' => 'nosniff',
            ],
            // Nuotraukos rodomos naršyklėje, PDF – atsisiunčiami
            $isImage ? 'inline' : 'attachment',
        );
    }
}
