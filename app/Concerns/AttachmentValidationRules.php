<?php

namespace App\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

/**
 * Žinučių priedų taisyklės (Etapas 6): nuotraukos – tos pačios griežtos taisyklės kaip Etape 3
 * (ImageValidationRules), PDF – iki 10 MB.
 *
 * Kodėl taisyklės parenkamos kiekvienam failui atskirai: „dimensions" taisyklė PDF'ui visada nepavyktų
 * (jis neturi pločio ir aukščio), o „image" atmestų PDF. Tipą nustatom pagal failo TURINĮ (getMimeType()
 * skaito pirmus baitus), ne pagal plėtinį – pervadintas „virusas.exe" → „sutartis.pdf" nepraeis.
 * https://laravel.com/docs/13.x/validation#validating-files
 */
trait AttachmentValidationRules
{
    use ImageValidationRules;

    /** Didžiausias PDF dydis kilobaitais (10 MB). */
    protected const MAX_PDF_KILOBYTES = 10 * 1024;

    /**
     * @return list<File>
     */
    protected function attachmentRules(mixed $file): array
    {
        if ($file instanceof UploadedFile && $file->getMimeType() === 'application/pdf') {
            return [File::types(['pdf'])->max(self::MAX_PDF_KILOBYTES)];
        }

        // Visa kita tikrinama kaip nuotrauka: ne nuotrauka (ir ne PDF) – atmetama su aiškiu pranešimu
        return $this->imageRules();
    }

    /**
     * @return array<string, string>
     */
    protected function attachmentMessages(string $field): array
    {
        $pdfMegabytes = intdiv(self::MAX_PDF_KILOBYTES, 1024);
        $typesMessage = 'Tinka tik nuotraukos (JPG, PNG, WEBP) arba PDF dokumentai.';

        return [
            ...$this->imageMessages($field),
            $field.'.mimes' => $typesMessage,
            $field.'.image' => $typesMessage,
            $field.'.file' => $typesMessage,
            $field.'.max' => "Failas per didelis: nuotrauka – iki 5 MB, PDF – iki {$pdfMegabytes} MB.",
        ];
    }
}
