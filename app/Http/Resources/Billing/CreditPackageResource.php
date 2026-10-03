<?php

namespace App\Http\Resources\Billing;

use App\Models\CreditPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kreditų paketas kainų ir kreditų puslapiams.
 *
 * @property CreditPackage $resource
 */
class CreditPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $package = $this->resource;
        $total = $package->totalCredits();

        return [
            'id' => $package->id,
            'name' => $package->name,
            'credits' => $package->credits,
            'bonus_credits' => $package->bonus_credits,
            'total_credits' => $total,
            'price_cents' => $package->price_cents,
            // Vieno kredito kaina centais (su dovanų kreditais) – kad būtų matyti, kuris paketas pigesnis
            'price_per_credit_cents' => $total > 0 ? (int) round($package->price_cents / $total) : null,
        ];
    }
}
