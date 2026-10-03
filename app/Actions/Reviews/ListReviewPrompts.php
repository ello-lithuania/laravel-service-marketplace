<?php

namespace App\Actions\Reviews;

use App\Enums\ServiceRequestStatus;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Kliento „Mano paskyra" priminimas: atlikti darbai, kuriuos dar galima įvertinti
 * (užbaigti per paskutines Review::VERIFIED_WINDOW_DAYS d. ir be atsiliepimo).
 */
class ListReviewPrompts
{
    public const LIMIT = 5;

    /**
     * @return list<array{slug: string, title: string, provider: string|null, completed_at: string|null}>
     */
    public function handle(User $client): array
    {
        return array_values($client->serviceRequests()
            ->where('status', ServiceRequestStatus::Completed)
            ->where('completed_at', '>=', now()->subDays(Review::VERIFIED_WINDOW_DAYS))
            // NOT EXISTS su indeksu reviews UNIQUE(service_request_id)
            ->whereDoesntHave('review')
            ->with('acceptedOffer.providerProfile:id,display_name')
            ->latest('completed_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ServiceRequest $request): array => [
                'slug' => $request->slug,
                'title' => $request->title,
                'provider' => $request->acceptedOffer?->providerProfile->display_name,
                'completed_at' => $request->completed_at?->toIso8601String(),
            ])
            ->all());
    }
}
