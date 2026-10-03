<?php

namespace App\Services\Catalog;

use Illuminate\Support\Str;

/**
 * Puslapio SEO duomenys: <title>, meta description, canonical, robots ir (Etapas 8) schema.org JSON-LD.
 * Vue pusėje juos rodo SeoHead.vue, o pirmą kartą atidarant – app.blade.php (be JavaScript).
 */
final readonly class SeoMeta
{
    /** Ilgesnį aprašymą Google vis tiek nukerpa. */
    private const DESCRIPTION_LENGTH = 160;

    /**
     * @param  list<array<string, mixed>>  $structuredData  schema.org objektai (Etapas 8, StructuredData)
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public bool $indexable = true,
        public array $structuredData = [],
    ) {}

    /**
     * @return array{title: string, description: string, canonical: string, robots: string, json_ld: string|null}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => Str::limit(Str::squish($this->description), self::DESCRIPTION_LENGTH, '…', preserveWords: true),
            'canonical' => $this->canonical,
            // noindex, follow – puslapio į paieškos rezultatus nededam, bet nuorodomis jame sekti galima
            'robots' => $this->indexable ? 'index, follow' : 'noindex, follow',
            'json_ld' => $this->jsonLd(),
        ];
    }

    /**
     * Etapas 8: visi puslapio schema.org objektai viename JSON-LD (@graph). Užkoduojama serveryje, kad app.blade.php
     * ir SeoHead.vue išvestų lygiai tą patį tekstą. JSON_HEX_TAG: „<" ir „>" tampa \u003C ir \u003E, todėl tekste
     * esantis „</script>" negali nutraukti <script> žymos (XSS apsauga).
     */
    private function jsonLd(): ?string
    {
        if ($this->structuredData === []) {
            return null;
        }

        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $this->structuredData],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );
    }
}
