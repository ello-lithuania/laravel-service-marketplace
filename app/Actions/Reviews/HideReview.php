<?php

namespace App\Actions\Reviews;

use App\Enums\ReviewStatus;
use App\Models\Review;

/**
 * Administratorius paslepia atsiliepimą (pvz. pagal skundą). Įrašas lieka DB (istorijai), bet viešai nerodomas
 * ir nebeįskaičiuojamas į reitingą – perskaičiavimą paleidžia ReviewObserver.
 */
class HideReview
{
    public function handle(Review $review): Review
    {
        if ($review->status !== ReviewStatus::Hidden) {
            $review->forceFill(['status' => ReviewStatus::Hidden])->save();
        }

        return $review;
    }
}
