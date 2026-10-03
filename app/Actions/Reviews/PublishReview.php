<?php

namespace App\Actions\Reviews;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Notifications\NewReview;

/**
 * Administratorius paskelbia atsiliepimą (pending/hidden → published).
 * Pirmą kartą paskelbus (published_at dar nebuvo) teikėjas gauna NewReview – pvz. pakvietimo atsiliepimui,
 * kuris laukė moderavimo. Reitingą perskaičiuoja ReviewObserver (pasikeitė status).
 */
class PublishReview
{
    public function handle(Review $review): Review
    {
        if ($review->status === ReviewStatus::Published) {
            return $review;
        }

        $firstTime = $review->published_at === null;

        $review->forceFill([
            'status' => ReviewStatus::Published,
            'published_at' => $review->published_at ?? now(),
        ])->save();

        if ($firstTime) {
            $review->loadMissing('providerProfile.user')->providerProfile->user->notify(new NewReview($review));
        }

        return $review;
    }
}
