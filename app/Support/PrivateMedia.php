<?php

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Privataus failo (žinutės priedo, užklausos nuotraukos) duomenys Inertia puslapiui.
 * URL veda per PrivateMediaController (/failai/{id}), kuris prieš atiduodamas failą patikrina Policy.
 * Kol miniatiūra dar daroma eilėje, rodom originalą (kaip PortfolioItem::imageUrls()).
 */
final class PrivateMedia
{
    /**
     * @return array{id: int, name: string, mime: string, size: int, is_image: bool, thumb_url: string|null, url: string}
     */
    public static function toArray(Media $media, string $thumb = 'thumb', ?string $large = null): array
    {
        $isImage = str_starts_with($media->mime_type, 'image/');

        return [
            'id' => $media->id,
            'name' => $media->file_name,
            'mime' => $media->mime_type,
            'size' => $media->size,
            'is_image' => $isImage,
            'thumb_url' => $isImage ? self::url($media, $thumb) : null,
            'url' => $large !== null && $isImage ? self::url($media, $large) : self::url($media),
        ];
    }

    private static function url(Media $media, ?string $conversion = null): string
    {
        $conversion = $conversion !== null && $media->hasGeneratedConversion($conversion) ? $conversion : null;

        return route('media.show', ['media' => $media->id, 'conversion' => $conversion]);
    }
}
