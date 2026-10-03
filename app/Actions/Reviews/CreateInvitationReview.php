<?php

namespace App\Actions\Reviews;

use App\Enums\ReviewStatus;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buvęs klientas palieka atsiliepimą pagal teikėjo pakvietimo nuorodą (service_request_id = NULL).
 *
 * Būsena „pending": darbas vyko ne per platformą, todėl prieš paskelbiant atsiliepimą peržiūri administratorius
 * (Filament → Atsiliepimai). Teikėjas pranešimą NewReview gaus, kai atsiliepimas bus paskelbtas (PublishReview).
 */
class CreateInvitationReview
{
    public function handle(ProviderProfile $provider, User $author, int $rating, string $comment): Review
    {
        return DB::transaction(function () use ($provider, $author, $rating, $comment): Review {
            // Autoriaus eilutės užraktas: du vienu metu išsiųsti atsiliepimai eis po vieną, ir antrasis
            // pamatys pirmąjį (Policy taisyklė „vienas per 12 mėn." patikrinama dar kartą)
            User::query()->whereKey($author->id)->lockForUpdate()->first();

            $recent = $provider->reviews()
                ->where('author_id', $author->id)
                ->where('created_at', '>=', now()->subDays(Review::INVITATION_COOLDOWN_DAYS))
                ->exists();

            if ($recent) {
                throw ValidationException::withMessages(['review' => __('reviews.errors.recently_reviewed')]);
            }

            $review = new Review(['rating' => $rating, 'comment' => $comment]);
            $review->forceFill([
                'service_request_id' => null,
                'provider_profile_id' => $provider->id,
                'author_id' => $author->id,
                'status' => ReviewStatus::Pending,
                'published_at' => null,
            ])->save();

            return $review;
        });
    }
}
