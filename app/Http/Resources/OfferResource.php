<?php

namespace App\Http\Resources;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Pasiūlymas. Sąraše klientui – tik ištrauka: visą žinutę jis mato atidaręs pasiūlymą (tada nustatomas viewed_at,
 * nuo kurio priklauso kreditų grąžinimas – docs/STATES.md 3 sk.).
 *
 * @mixin Offer
 */
class OfferResource extends JsonResource
{
    private bool $fullMessage = false;

    public function withFullMessage(bool $full = true): static
    {
        $this->fullMessage = $full;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'message' => $this->fullMessage ? $this->message : Str::limit($this->message, 140),
            'price_cents' => $this->price_cents,
            'price_type' => ['value' => $this->price_type->value, 'label' => $this->price_type->label()],
            'duration_text' => $this->duration_text,
            'start_date' => $this->start_date?->toDateString(),
            'credits_spent' => $this->credits_spent,
            'viewed_at' => $this->viewed_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'provider' => $this->whenLoaded('providerProfile', fn () => [
                'id' => $this->providerProfile->id,
                'display_name' => $this->providerProfile->display_name,
                'slug' => $this->providerProfile->slug,
                'headline' => $this->providerProfile->headline,
                'rating_avg' => (float) $this->providerProfile->rating_avg,
                'reviews_count' => $this->providerProfile->reviews_count,
                'completed_jobs_count' => $this->providerProfile->completed_jobs_count,
                'is_verified' => $this->providerProfile->isVerified(),
                'city' => $this->providerProfile->relationLoaded('city') ? $this->providerProfile->city->name : null,
            ]),
            'service_request' => $this->whenLoaded('serviceRequest', fn () => [
                'id' => $this->serviceRequest->id,
                'slug' => $this->serviceRequest->slug,
                'title' => $this->serviceRequest->title,
                'status' => ['value' => $this->serviceRequest->status->value, 'label' => $this->serviceRequest->status->label()],
            ]),
        ];
    }
}
