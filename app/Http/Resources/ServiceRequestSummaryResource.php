<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Užklausa sąrašuose („Mano užklausos", teikėjo srautas). API Resource = kokius laukus siunčiam į Vue:
 * tik reikalingus, niekada viso modelio (adresas ar kliento duomenys čia nepatenka).
 * https://laravel.com/docs/13.x/eloquent-resources
 *
 * @mixin ServiceRequest
 */
class ServiceRequestSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => Str::limit($this->description, 180),
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'offer_cost_credits' => $this->category->offer_cost_credits,
            ]),
            'city' => $this->whenLoaded('city', fn () => ['id' => $this->city->id, 'name' => $this->city->name]),
            'budget_min_cents' => $this->budget_min_cents,
            'budget_max_cents' => $this->budget_max_cents,
            'start_preference' => $this->start_preference->label(),
            'start_date' => $this->start_date?->toDateString(),
            'offers_count' => $this->offers_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
