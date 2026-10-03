<?php

namespace App\Http\Resources\Catalog;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Viešas atsiliepimas teikėjo profilyje. Autorius rodomas tik kaip „Jonas P." (User::public_name).
 *
 * @mixin Review
 *
 * @property Review $resource
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $review = $this->resource;

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
            // Autorius užkraunamas su withTrashed(): ištrynus paskyrą atsiliepimas lieka (BDAR – vardas anonimizuojamas)
            'author_name' => $review->author->public_name,
            // Darbas atliktas per platformą (yra užklausa) – „Patvirtintas atsiliepimas"
            'is_verified' => $review->isVerified(),
            'published_at' => $review->published_at?->toIso8601String(),
            'provider_reply' => $review->provider_reply,
            'provider_replied_at' => $review->provider_replied_at?->toIso8601String(),
        ];
    }
}
