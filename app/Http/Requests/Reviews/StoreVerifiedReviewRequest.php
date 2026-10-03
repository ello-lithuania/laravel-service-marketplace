<?php

namespace App\Http\Requests\Reviews;

use App\Models\Review;
use App\Models\ServiceRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Patvirtintas atsiliepimas po atlikto darbo: /uzklausos/{slug}/atsiliepimas.
 */
class StoreVerifiedReviewRequest extends ReviewRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('createVerified', [Review::class, $this->serviceRequest()]);
    }

    public function serviceRequest(): ServiceRequest
    {
        /** @var ServiceRequest */
        return $this->route('serviceRequest');
    }
}
