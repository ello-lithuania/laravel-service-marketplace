<?php

namespace App\Http\Controllers\Reviews;

use App\Actions\Reviews\CreateInvitationReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\StoreInvitationReviewRequest;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Atsiliepimas pagal teikėjo pakvietimo nuorodą (/atsiliepimas/{slug}?expires=…&signature=…).
 *
 * Nuoroda pasirašyta (URL::temporarySignedRoute): jos parašas apima teikėjo slug'ą ir galiojimo laiką, todėl
 * pakeitus bet kurią dalį ar praėjus terminui „signed" middleware grąžina 403. Neprisijungusį „auth" nukreipia
 * prisijungti ir po to grąžina į tą pačią (su parašu) nuorodą. https://laravel.com/docs/13.x/urls#signed-urls
 */
class ReviewInvitationController extends Controller
{
    public function show(Request $request, ProviderProfile $providerProfile): Response
    {
        $permission = Gate::inspect('createFromInvitation', [Review::class, $providerProfile]);
        $expires = $request->integer('expires');

        return Inertia::render('reviews/Invitation', [
            'provider' => [
                'display_name' => $providerProfile->display_name,
                'slug' => $providerProfile->slug,
                'headline' => $providerProfile->headline,
                'logo_url' => $providerProfile->logoUrl(),
                'rating_avg' => (float) $providerProfile->rating_avg,
                'reviews_count' => $providerProfile->reviews_count,
            ],
            'can' => [
                'create' => $permission->allowed(),
                'reason' => $permission->denied() ? $permission->message() : null,
            ],
            'expiresAt' => $expires > 0 ? Carbon::createFromTimestamp($expires)->toIso8601String() : null,
        ]);
    }

    public function store(StoreInvitationReviewRequest $request, ProviderProfile $providerProfile, CreateInvitationReview $create): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $create->handle($providerProfile, $user, $request->rating(), $request->comment());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('reviews.flash.invitation_created')]);

        return to_route('providers.show', $providerProfile);
    }
}
