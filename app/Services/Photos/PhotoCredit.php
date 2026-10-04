<?php

namespace App\Services\Photos;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Nuotraukos autorius ir licencija – media custom_properties.credit (docs/DB_SCHEMA.md → media).
 *
 * Duomenys ateina iš išorinių API (Pexels, Openverse), todėl konstruktorius juos „išvalo": tekstai apkarpomi,
 * o nuorodos priimamos tik http(s) – kitaip „javascript:…" nuoroda autorių puslapyje taptų XSS spraga.
 */
final readonly class PhotoCredit
{
    public ?string $author;

    public ?string $authorUrl;

    public string $source;

    public ?string $sourceUrl;

    public string $license;

    public ?string $licenseUrl;

    public ?string $title;

    public function __construct(
        ?string $author,
        ?string $authorUrl,
        string $source,
        ?string $sourceUrl,
        string $license,
        ?string $licenseUrl,
        ?string $title = null,
    ) {
        $this->author = self::text($author, 120);
        $this->authorUrl = self::url($authorUrl);
        $this->source = self::text($source, 60) ?? 'Nežinomas šaltinis';
        $this->sourceUrl = self::url($sourceUrl);
        $this->license = self::text($license, 60) ?? 'Nežinoma licencija';
        $this->licenseUrl = self::url($licenseUrl);
        $this->title = self::text($title, 160);
    }

    /**
     * Iš media custom_properties.credit ar credits.json. null – įrašas sugadintas (nėra šaltinio ar licencijos).
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $string = fn (string $key): ?string => isset($data[$key]) && is_string($data[$key]) ? $data[$key] : null;

        if ($string('source') === null || $string('license') === null) {
            return null;
        }

        return new self(
            author: $string('author'),
            authorUrl: $string('author_url'),
            source: (string) $string('source'),
            sourceUrl: $string('source_url'),
            license: (string) $string('license'),
            licenseUrl: $string('license_url'),
            title: $string('title'),
        );
    }

    /**
     * Iš media įrašo (custom_properties.credit). null – savo nuotrauka (be autoriaus) arba media nėra.
     */
    public static function fromMedia(?Media $media): ?self
    {
        $raw = $media?->getCustomProperty('credit');

        return is_array($raw) ? self::fromArray($raw) : null;
    }

    /**
     * @return array{author: string|null, author_url: string|null, source: string, source_url: string|null, license: string, license_url: string|null, title: string|null}
     */
    public function toArray(): array
    {
        return [
            'author' => $this->author,
            'author_url' => $this->authorUrl,
            'source' => $this->source,
            'source_url' => $this->sourceUrl,
            'license' => $this->license,
            'license_url' => $this->licenseUrl,
            'title' => $this->title,
        ];
    }

    /**
     * Viena eilutė admin panelei: „Jonas Jonaitis · Pexels · Pexels License".
     */
    public function summary(): string
    {
        return implode(' · ', array_filter([$this->author ?? 'Autorius nenurodytas', $this->source, $this->license]));
    }

    private static function text(?string $value, int $limit): ?string
    {
        $value = Str::squish(strip_tags((string) $value));

        return $value === '' ? null : Str::limit($value, $limit, '…');
    }

    private static function url(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || strlen($value) > 500 || ! preg_match('#^https?://#i', $value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_URL) === false ? null : $value;
    }
}
