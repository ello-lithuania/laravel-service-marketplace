<?php

namespace Tests\Support;

use App\Services\Photos\DownloadedPhoto;
use App\Services\Photos\FetchedPhoto;
use App\Services\Photos\LibraryPhoto;
use App\Services\Photos\PhotoCredit;
use App\Services\Photos\StockPhoto;
use App\Services\Photos\StockPhotoLibrary;

/**
 * Etapo 10 testų pagalbininkai: tikras mažas JPEG (GD), netikri Pexels ir Openverse atsakymai (tokio pat formato
 * kaip tikri API) ir nuotraukos vietinėje bibliotekoje (diskas „stock-photos" – testuose Storage::fake).
 */
final class StockPhotos
{
    /**
     * Tikras JPEG: komanda tikrina turinį (getimagesize + finfo), todėl „netikri baitai" netiktų.
     */
    public static function jpeg(int $width = 1400, int $height = 900, int $red = 200): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, (int) imagecolorallocate($image, $red, 120, 40));
        ob_start();
        imagejpeg($image, null, 80);

        return (string) ob_get_clean();
    }

    /**
     * Vienas Pexels „photos" elementas (https://www.pexels.com/api/documentation/#photos-overview).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function pexelsPhoto(int $id, array $overrides = []): array
    {
        $base = "https://images.pexels.com/photos/{$id}/pexels-photo-{$id}.jpeg";

        return array_replace([
            'id' => $id,
            'width' => 4000,
            'height' => 2667,
            'url' => "https://www.pexels.com/photo/renovated-room-{$id}/",
            'photographer' => "Fotografas {$id}",
            'photographer_url' => "https://www.pexels.com/@fotografas-{$id}",
            'photographer_id' => 1000 + $id,
            'avg_color' => '#978E82',
            'src' => [
                'original' => $base,
                'large2x' => "{$base}?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940",
                'large' => "{$base}?auto=compress&cs=tinysrgb&h=650&w=940",
                'medium' => "{$base}?auto=compress&cs=tinysrgb&h=350",
                'small' => "{$base}?auto=compress&cs=tinysrgb&h=130",
                'portrait' => "{$base}?auto=compress&cs=tinysrgb&fit=crop&h=1200&w=800",
                'landscape' => "{$base}?auto=compress&cs=tinysrgb&fit=crop&h=627&w=1200",
                'tiny' => "{$base}?auto=compress&cs=tinysrgb&dpr=1&fit=crop&h=200&w=280",
            ],
            'liked' => false,
            'alt' => "Renovated room {$id}",
        ], $overrides);
    }

    /**
     * @param  list<array<string, mixed>>  $photos
     * @return array<string, mixed>
     */
    public static function pexelsSearch(array $photos): array
    {
        return [
            'total_results' => count($photos),
            'page' => 1,
            'per_page' => 15,
            'photos' => $photos,
            'next_page' => null,
        ];
    }

    /**
     * Vienas Openverse „results" elementas (https://api.openverse.org/v1/#tag/images/operation/images_search).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function openverseResult(string $id, array $overrides = []): array
    {
        return array_replace([
            'id' => $id,
            'title' => "Garden {$id}",
            'indexed_on' => '2024-01-01T00:00:00Z',
            'foreign_landing_url' => "https://www.flickr.com/photos/autorius/{$id}",
            'url' => "https://live.staticflickr.com/65535/{$id}_b.jpg",
            'creator' => 'Autorius Flickr',
            'creator_url' => 'https://www.flickr.com/photos/autorius',
            'license' => 'by',
            'license_version' => '2.0',
            'license_url' => 'https://creativecommons.org/licenses/by/2.0/',
            'provider' => 'flickr',
            'source' => 'flickr',
            'category' => 'photograph',
            'filesize' => null,
            'filetype' => 'jpg',
            'tags' => [['name' => 'garden'], ['name' => 'lawn']],
            'attribution' => "\"Garden {$id}\" by Autorius Flickr is licensed under CC BY 2.0.",
            'mature' => false,
            'height' => 1365,
            'width' => 2048,
            'thumbnail' => "https://api.openverse.org/v1/images/{$id}/thumb/",
        ], $overrides);
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    public static function openverseSearch(array $results): array
    {
        return [
            'result_count' => count($results),
            'page_count' => 1,
            'page_size' => 20,
            'page' => 1,
            'results' => $results,
        ];
    }

    /**
     * Nuotrauka bibliotekoje – tarsi photos:download ją būtų atsisiuntęs anksčiau.
     */
    public static function inLibrary(string $directory, string $name, string $stockId, ?PhotoCredit $credit = null, int $width = 1400, int $height = 900): LibraryPhoto
    {
        [$provider, $id] = explode(':', $stockId, 2);

        $stock = new StockPhoto(
            provider: $provider,
            id: $id,
            downloadUrl: 'https://example.test/'.$id.'.jpg',
            width: $width,
            height: $height,
            description: '',
            credit: $credit ?? new PhotoCredit('Bibliotekos autorius', 'https://example.test/autorius', 'Pexels', 'https://example.test/'.$id, 'Pexels License', 'https://www.pexels.com/license/', 'Nuotrauka '.$id),
        );

        return app(StockPhotoLibrary::class)->store(
            $directory,
            $name,
            new FetchedPhoto($stock, new DownloadedPhoto(self::jpeg($width, $height, crc32($stockId) % 255), 'image/jpeg', $width, $height)),
            2000,
        );
    }
}
