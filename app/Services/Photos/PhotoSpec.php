<?php

namespace App\Services\Photos;

/**
 * Kokios nuotraukos reikia: mažiausi matmenys, iki kiek sumažinti bibliotekoje, ar vengti žmonių.
 */
final readonly class PhotoSpec
{
    public const TARGETS = ['categories', 'site', 'portfolio'];

    public function __construct(
        public int $minWidth,
        public int $minHeight,
        public int $maxDimension,
        public bool $avoidPeople,
        public int $perPage = 15,
    ) {}

    /**
     * Nustatymai iš config/photos.php. Portfolio – be žmonių (docs/SEEDING.md 6 sk.) ir daugiau rezultatų vienai
     * paieškai, nes vienai sričiai reikia kelių nuotraukų.
     */
    public static function for(string $target): self
    {
        $min = (array) config("photos.min_size.{$target}", [800, 600]);

        return new self(
            minWidth: (int) ($min[0] ?? 800),
            minHeight: (int) ($min[1] ?? 600),
            maxDimension: (int) config("photos.max_dimension.{$target}", 2000),
            avoidPeople: $target === 'portfolio',
            perPage: $target === 'portfolio' ? 30 : 15,
        );
    }

    public function withoutPeople(): self
    {
        return new self($this->minWidth, $this->minHeight, $this->maxDimension, true, $this->perPage);
    }
}
