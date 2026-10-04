<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Po migrate:fresh media lentelė tuščia, bet ankstesnio seed'o failai ({media id}/…) liko diske. Jų nebenurodo jokia
 * eilutė, todėl ištrinam – kitaip kiekvienas seed'as pridėtų dar kelis šimtus MB, o naujas media įrašas tuo pačiu id
 * rašytų į katalogą su senais failais.
 *
 * Etapas 10: trinami tik skaitmeniniai katalogai, kurių id NĖRA media lentelėje (anksčiau – tik kai lentelė tuščia):
 * StockPhotoSeeder prisega kategorijų nuotraukas anksčiau už MediaGenerator, ir jų failų trinti negalima.
 */
final class OrphanedMediaFiles
{
    public static function clear(): void
    {
        $disk = Storage::disk((string) config('media-library.disk_name'));
        $existing = array_flip(array_map('strval', DB::table('media')->pluck('id')->all()));

        foreach ($disk->directories() as $directory) {
            if (ctype_digit($directory) && ! isset($existing[$directory])) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
