<?php

namespace App\Services\Catalog;

use Illuminate\Support\Str;

/**
 * Puslapio SEO duomenys: <title>, meta description, canonical, robots.
 * Vue pusėje juos rodo SeoHead.vue, o pirmą kartą atidarant – app.blade.php (be JavaScript).
 */
final readonly class SeoMeta
{
    /** Ilgesnį aprašymą Google vis tiek nukerpa. */
    private const DESCRIPTION_LENGTH = 160;

    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public bool $indexable = true,
    ) {}

    /**
     * @return array{title: string, description: string, canonical: string, robots: string}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => Str::limit(Str::squish($this->description), self::DESCRIPTION_LENGTH, '…', preserveWords: true),
            'canonical' => $this->canonical,
            // noindex, follow – puslapio į paieškos rezultatus nededam, bet nuorodomis jame sekti galima
            'robots' => $this->indexable ? 'index, follow' : 'noindex, follow',
        ];
    }
}
