<?php

namespace App\Models;

use Database\Factories\CreditPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Perkamas kreditų paketas. Kaina – sveikais centais.
 */
#[Fillable(['name', 'credits', 'bonus_credits', 'price_cents', 'is_active', 'sort_order'])]
class CreditPackage extends Model
{
    /** @use HasFactory<CreditPackageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'bonus_credits' => 'integer',
            'price_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return MorphMany<Payment, $this> */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'purchasable');
    }

    public function totalCredits(): int
    {
        return $this->credits + $this->bonus_credits;
    }
}
