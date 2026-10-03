<?php

namespace App\Http\Requests\Reviews;

use App\Models\ProviderProfile;
use App\Models\Review;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Atsiliepimas pagal teikėjo pakvietimo nuorodą. Parašą (signed URL) patikrina „signed" middleware maršrute.
 */
class StoreInvitationReviewRequest extends ReviewRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('createFromInvitation', [Review::class, $this->provider()]);
    }

    public function provider(): ProviderProfile
    {
        /** @var ProviderProfile */
        return $this->route('providerProfile');
    }
}
