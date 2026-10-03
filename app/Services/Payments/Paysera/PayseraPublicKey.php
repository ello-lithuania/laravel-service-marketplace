<?php

namespace App\Services\Payments\Paysera;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Log;

/**
 * Paysera viešasis raktas ss2 parašui tikrinti.
 *
 * Iš kur imamas (pirmas rastas):
 *   1. failas config('payments.paysera.public_key_path') – „php artisan payments:paysera-key" jį parsiunčia iš anksto;
 *   2. cache (parą) – kad callback'ai nesiųstų HTTP užklausos kiekvieną kartą;
 *   3. parsiunčiamas iš config('payments.paysera.public_key_url').
 * Raktas viešas (ne paslaptis), todėl jį saugoti faile ar cache saugu.
 */
class PayseraPublicKey
{
    private const CACHE_KEY = 'payments.paysera.public_key';

    public function __construct(
        private readonly Cache $cache,
        private readonly Http $http,
        private readonly string $url,
        private readonly ?string $path,
    ) {}

    public function get(): ?string
    {
        if ($this->path !== null && is_file($this->path)) {
            $pem = file_get_contents($this->path);

            return $pem !== false && self::isValid($pem) ? $pem : null;
        }

        $cached = $this->cache->get(self::CACHE_KEY);

        if (is_string($cached)) {
            return $cached;
        }

        $pem = $this->download();

        if ($pem !== null) {
            $this->cache->put(self::CACHE_KEY, $pem, now()->addDay());
        }

        return $pem;
    }

    /**
     * Parsiunčia raktą ir įrašo į failą (artisan komandai). Grąžina, ar pavyko.
     */
    public function store(): bool
    {
        $pem = $this->download();

        if ($pem === null || $this->path === null) {
            return false;
        }

        if (! is_dir(dirname($this->path))) {
            mkdir(dirname($this->path), 0755, true);
        }

        return file_put_contents($this->path, $pem) !== false;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    private function download(): ?string
    {
        try {
            $response = $this->http->timeout(5)->get($this->url);
        } catch (ConnectionException $e) {
            Log::warning('Nepavyko parsisiųsti Paysera viešojo rakto.', ['url' => $this->url, 'error' => $e->getMessage()]);

            return null;
        }

        return $response->successful() && self::isValid($response->body()) ? $response->body() : null;
    }

    /**
     * Ar tekstas – OpenSSL suprantamas viešasis raktas ar sertifikatas.
     */
    public static function isValid(string $pem): bool
    {
        return openssl_pkey_get_public($pem) !== false;
    }
}
