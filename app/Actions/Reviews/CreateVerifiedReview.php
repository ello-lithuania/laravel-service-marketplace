<?php

namespace App\Actions\Reviews;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\NewReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Klientas įvertina per platformą atliktą darbą – patvirtintas atsiliepimas (service_request_id užpildytas).
 * Paskelbiamas iš karto: darbas tikrai vyko, o netinkamas turinys šalinamas per skundus.
 *
 * Reitingo (rating_avg, reviews_count) čia neskaičiuojam – tai daro ReviewObserver → RecalculateProviderRating.
 */
class CreateVerifiedReview
{
    public function handle(ServiceRequest $serviceRequest, User $author, int $rating, string $comment): Review
    {
        $review = DB::transaction(function () use ($serviceRequest, $author, $rating, $comment): Review {
            // Užrakinam užklausą: du vienu metu išsiųsti atsiliepimai (dvigubas paspaudimas) eis po vieną.
            // Papildomas saugiklis – UNIQUE(service_request_id) DB lygiu.
            $locked = ServiceRequest::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->review()->exists()) {
                throw ValidationException::withMessages(['review' => __('reviews.errors.already_reviewed')]);
            }

            $review = new Review(['rating' => $rating, 'comment' => $comment]);
            $review->forceFill([
                'service_request_id' => $locked->id,
                'provider_profile_id' => $locked->acceptedOffer()->value('provider_profile_id'),
                'author_id' => $author->id,
                'status' => ReviewStatus::Published,
                'published_at' => now(),
            ])->save();

            return $review;
        });

        // Pranešimas – po transakcijos (kad nepraneštume apie neįvykusį dalyką)
        $review->loadMissing('providerProfile.user')->providerProfile->user->notify(new NewReview($review));

        return $review;
    }
}
