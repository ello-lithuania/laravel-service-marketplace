<?php

namespace App\Http\Resources\Reviews;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Atsiliepimas paskyroje: kliento užklausos puslapyje (jo paties atsiliepimas) ir teikėjo „Atsiliepimai"
 * puslapyje (su būsena – „Laukia moderavimo" matoma tik čia, ne viešai).
 * Autoriaus vardas – „Vardas P."; author ir serviceRequest turi būti užkrauti.
 *
 * @property Review $resource
 */
class AccountReviewResource extends JsonResource
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
            'status' => ['value' => $review->status->value, 'label' => $review->status->label()],
            'is_verified' => $review->isVerified(),
            'author_name' => $review->author->public_name ?? '–',
            'service_request_title' => $review->serviceRequest?->title,
            'created_at' => $review->created_at?->toIso8601String(),
            'published_at' => $review->published_at?->toIso8601String(),
            'provider_reply' => $review->provider_reply,
            'provider_replied_at' => $review->provider_replied_at?->toIso8601String(),
            // Policy tikrina tik jau turimus laukus (provider_profile_id, status), todėl papildomų užklausų nėra
            'can' => ['reply' => $request->user()?->can('reply', $review) ?? false],
        ];
    }
}
