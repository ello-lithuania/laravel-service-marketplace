<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Notifications\ReviewReplied;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Teikėjas viešai atsako į atsiliepimą – vieną kartą (provider_reply, provider_replied_at).
 */
class ReplyToReview
{
    public function handle(Review $review, string $reply): Review
    {
        DB::transaction(function () use ($review, $reply): void {
            $locked = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            if ($locked->provider_reply !== null) {
                throw ValidationException::withMessages(['reply' => __('reviews.errors.reply_not_allowed')]);
            }

            $review->forceFill(['provider_reply' => $reply, 'provider_replied_at' => now()])->save();
        });

        // Ištrintos paskyros autoriui (ryšys null) nepranešam
        $review->loadMissing('author')->author?->notify(new ReviewReplied($review));

        return $review;
    }
}
