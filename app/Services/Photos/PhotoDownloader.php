<?php

namespace App\Services\Photos;

use App\Services\Photos\Exceptions\InvalidPhoto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Atsisiunčia paveikslėlį ir patikrina, ar jis tinka. Failu iš interneto aklai nepasitikim:
 *
 * - dydis ≤ config('photos.max_bytes'): pirma pagal Content-Length antraštę, paskui skaitant (antraštė gali meluoti);
 * - turinys – tikras JPEG, PNG arba WEBP (getimagesizefromstring + finfo), o ne tai, ką sako URL ar Content-Type;
 * - matmenys ne mažesni už PhotoSpec minimumą;
 * - tik http(s) ir ne vidinio tinklo adresai (apsauga, jei API grąžintų, pvz., http://127.0.0.1/…).
 */
final class PhotoDownloader
{
    /** @var array<int, string> getimagesize() tipas => MIME */
    private const TYPES = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    private const CHUNK = 65_536;

    public function __construct(
        private readonly string $userAgent,
        private readonly int $maxBytes = 10 * 1024 * 1024,
        private readonly int $timeout = 30,
    ) {}

    /**
     * @throws InvalidPhoto
     * @throws ConnectionException
     */
    public function download(string $url, PhotoSpec $spec): DownloadedPhoto
    {
        $this->guardUrl($url);

        // stream – Guzzle neskaito viso atsakymo į atmintį iš karto, todėl 50 MB failą nutraukiam po 10 MB
        $response = Http::withUserAgent($this->userAgent)
            ->connectTimeout(10)
            ->timeout($this->timeout)
            ->withOptions(['stream' => true])
            ->get($url);

        if ($response->failed()) {
            throw new InvalidPhoto("serveris atsakė HTTP {$response->status()}");
        }

        $length = $response->header('Content-Length');

        if ($length !== '' && (int) $length > $this->maxBytes) {
            throw new InvalidPhoto(sprintf('per didelis failas (%.1f MB)', (int) $length / 1_048_576));
        }

        $body = $response->toPsrResponse()->getBody();
        $bytes = '';

        try {
            // Tikras srautas (stream) neperskaitomas iš naujo, o atmintyje laikomas (pvz. Http::fake) – gali būti jau
            // perskaitytas, todėl jį „atsukam" į pradžią
            if ($body->isSeekable()) {
                $body->rewind();
            }

            while (! $body->eof() && strlen($bytes) <= $this->maxBytes) {
                $bytes .= $body->read(self::CHUNK);
            }
        } catch (RuntimeException $e) {
            // Ryšys nutrūko skaitant – Guzzle meta RuntimeException, ne ConnectionException
            throw new InvalidPhoto('atsisiuntimas nutrūko: '.$e->getMessage(), previous: $e);
        }

        if (strlen($bytes) > $this->maxBytes) {
            throw new InvalidPhoto(sprintf('per didelis failas (> %d MB)', intdiv($this->maxBytes, 1_048_576)));
        }

        return $this->validate($bytes, $spec);
    }

    /**
     * @throws InvalidPhoto
     */
    public function validate(string $bytes, PhotoSpec $spec): DownloadedPhoto
    {
        $info = $bytes === '' ? false : @getimagesizefromstring($bytes);

        if ($info === false || ! isset(self::TYPES[$info[2]])) {
            throw new InvalidPhoto('ne JPEG, PNG ar WEBP paveikslėlis');
        }

        $mime = self::TYPES[$info[2]];

        // Antra patikra kitu būdu (failo „parašas"): getimagesize pakanka pradžios baitų, finfo tikrina savo taisyklėmis
        if ((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $mime) {
            throw new InvalidPhoto('failo turinys neatitinka paveikslėlio tipo');
        }

        [$width, $height] = [$info[0], $info[1]];

        if ($width < $spec->minWidth || $height < $spec->minHeight) {
            throw new InvalidPhoto("per maža nuotrauka ({$width}×{$height}, reikia bent {$spec->minWidth}×{$spec->minHeight})");
        }

        return new DownloadedPhoto($bytes, $mime, $width, $height);
    }

    /**
     * @throws InvalidPhoto
     */
    private function guardUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            throw new InvalidPhoto('netinkamas adresas');
        }

        // IP adresas vietoj domeno – leidžiam tik viešus (ne 10.x, 192.168.x, 127.x ir pan.)
        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidPhoto('vidinio tinklo adresas');
        }
    }
}
