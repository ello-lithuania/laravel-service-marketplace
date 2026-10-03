// Etapas 6: failų (priedų, nuotraukų) pagalbinės funkcijos.
// Ribos turi sutapti su serverio taisyklėmis (App\Concerns\ImageValidationRules, AttachmentValidationRules) –
// čia jos tik tam, kad vartotojas klaidą pamatytų iš karto, o ne po įkėlimo. Galutinai tikrina serveris.

export const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
export const MAX_PDF_BYTES = 10 * 1024 * 1024;
export const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/** 1536000 → „1,5 MB" */
export function formatFileSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / 1024 / 1024).toLocaleString('lt-LT', { maximumFractionDigits: 1 })} MB`;
}

/**
 * Priedo patikra naršyklėje: grąžina klaidos tekstą arba null.
 */
export function attachmentError(file: File): string | null {
    if (file.type === 'application/pdf') {
        return file.size > MAX_PDF_BYTES
            ? `„${file.name}“ per didelis – PDF iki 10 MB.`
            : null;
    }

    if (!IMAGE_TYPES.includes(file.type)) {
        return `„${file.name}“ – tinka tik nuotraukos (JPG, PNG, WEBP) arba PDF.`;
    }

    return file.size > MAX_IMAGE_BYTES
        ? `„${file.name}“ per didelė – nuotrauka iki 5 MB.`
        : null;
}
