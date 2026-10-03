<?php

namespace App\Services\Catalog;

use App\Enums\ProviderSort;

/**
 * Teikėjų sąrašo filtrai – jau patikrinti ir paversti tipais (DTO, „duomenų perdavimo objektas").
 * Sukuriamas CatalogFilterRequest::filters() metode; controller'is ir ProviderListQuery dirba tik su juo.
 */
final readonly class ProviderFilters
{
    public function __construct(
        public ?CachedCity $city = null,
        public bool $verifiedOnly = false,
        public ?float $minRating = null,
        public ProviderSort $sort = ProviderSort::Rating,
        public ?SearchTerms $search = null,
    ) {}

    public function withCity(?CachedCity $city): self
    {
        return new self($city, $this->verifiedOnly, $this->minRating, $this->sort, $this->search);
    }

    /**
     * Ar pasirinkta kas nors, kas keičia sąrašo turinį ar tvarką (be miesto).
     * Tokie puslapiai SEO požiūriu yra to paties puslapio variantai – jų canonical rodo į pagrindinį.
     */
    public function isRefined(ProviderSort $defaultSort): bool
    {
        return $this->verifiedOnly || $this->minRating !== null || $this->sort !== $defaultSort;
    }

    /**
     * Dabartinės reikšmės UI formai, URL parametrų vardais.
     *
     * @return array{q: string|null, miestas: string|null, patikrinti: bool, reitingas: float|null, rikiuoti: string}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->search?->input,
            'miestas' => $this->city?->slug,
            'patikrinti' => $this->verifiedOnly,
            'reitingas' => $this->minRating,
            'rikiuoti' => $this->sort->value,
        ];
    }
}
