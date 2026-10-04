<?php

namespace App\Services\Photos;

/**
 * Rasta ir atsisiųsta nuotrauka: paieškos rezultatas (autorius, licencija) + failo turinys.
 */
final readonly class FetchedPhoto
{
    public function __construct(
        public StockPhoto $stock,
        public DownloadedPhoto $file,
    ) {}
}
