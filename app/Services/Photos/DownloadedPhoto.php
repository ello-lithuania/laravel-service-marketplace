<?php

namespace App\Services\Photos;

/**
 * Atsisiųstas ir patikrintas paveikslėlis (turinys – tikras JPEG, PNG arba WEBP).
 */
final readonly class DownloadedPhoto
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public int $width,
        public int $height,
    ) {}

    public function extension(): string
    {
        return match ($this->mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
