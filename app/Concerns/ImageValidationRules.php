<?php

namespace App\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Bendros nuotraukų įkėlimo taisyklės: avataras, logotipas, viršelis, portfolio.
 * https://laravel.com/docs/13.x/validation#validating-files
 */
trait ImageValidationRules
{
    /** Didžiausias failo dydis kilobaitais (5 MB). */
    protected const MAX_IMAGE_KILOBYTES = 5 * 1024;

    /**
     * - File::image() + types(): tikrinamas tikras failo turinys (MIME), ne tik plėtinys –
     *   „virusas.php", pervadintas į „foto.jpg", nepraeis. SVG atmetamas: jame gali būti JavaScript.
     * - max: dydis kilobaitais. Svarbu: PHP pats atmeta failus, didesnius už upload_max_filesize (php.ini).
     * - dimensions: per maža nuotrauka atrodys prastai, o milžiniška (pvz. 30 000 px) apdorojant
     *   gali suvalgyti visą serverio atmintį.
     *
     * @return list<File>
     */
    protected function imageRules(): array
    {
        return [
            File::image()
                ->types(['jpg', 'jpeg', 'png', 'webp'])
                ->max(self::MAX_IMAGE_KILOBYTES)
                ->dimensions(Rule::dimensions()->minWidth(200)->minHeight(200)->maxWidth(8000)->maxHeight(8000)),
        ];
    }

    /**
     * Aiškesni pranešimai nei standartiniai. „uploaded" klaida dažniausiai reiškia, kad failą
     * atmetė pats PHP (didesnis už upload_max_filesize), todėl apie dydį pasakom aiškiai.
     *
     * @return array<string, string>
     */
    protected function imageMessages(string $field): array
    {
        $megabytes = intdiv(self::MAX_IMAGE_KILOBYTES, 1024);

        return [
            $field.'.uploaded' => "Nuotraukos įkelti nepavyko. Patikrinkite, ar failas ne didesnis nei {$megabytes} MB.",
            $field.'.max' => "Nuotrauka per didelė – daugiausia {$megabytes} MB.",
            $field.'.mimes' => 'Tinka tik JPG, PNG arba WEBP nuotraukos.',
            $field.'.image' => 'Tinka tik JPG, PNG arba WEBP nuotraukos.',
            $field.'.dimensions' => 'Nuotrauka turi būti ne mažesnė nei 200×200 ir ne didesnė nei 8000×8000 taškų.',
        ];
    }
}
