<?php

namespace App\Http\Controllers\Reviews;

use App\Actions\Reviews\ReplyToReview;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Account\Concerns\InteractsWithProviderProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\ReplyToReviewRequest;
use App\Http\Resources\Reviews\AccountReviewResource;
use App\Models\Review;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teikėjo „Atsiliepimai" (/paskyra/atsiliepimai): gauti atsiliepimai, atsakymai į juos ir pakvietimo nuoroda
 * buvusiems klientams.
 */
class ProviderReviewController extends Controller
{
    use InteractsWithProviderProfile;

    public function index(Request $request): Response
    {
        $profile = $this->currentProfile($request);

        // Paslėpti (administratoriaus) nerodomi; laukiantys moderavimo – rodomi su būsena
        $reviews = $profile->reviews()
            ->where('status', '!=', ReviewStatus::Hidden)
            ->with([
                'author' => fn (Relation $query) => $query->withoutGlobalScope(SoftDeletingScope::class),
                'serviceRequest:id,title',
            ])
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);

        $expiresAt = now()->addDays(Review::INVITATION_LINK_DAYS);

        return Inertia::render('account/reviews/Index', [
            'reviews' => AccountReviewResource::collection($reviews),
            'summary' => [
                'rating_avg' => (float) $profile->rating_avg,
                'reviews_count' => $profile->reviews_count,
            ],
            // Pasirašyta nuoroda: parašas apima slug'ą ir galiojimo laiką – suklastoti ar pratęsti jos negalima
            'invitation' => [
                'url' => URL::temporarySignedRoute('reviews.invitation.show', $expiresAt, ['providerProfile' => $profile]),
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    public function reply(ReplyToReviewRequest $request, Review $review, ReplyToReview $reply): RedirectResponse
    {
        $reply->handle($review, $request->string('reply')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('reviews.flash.replied')]);

        return back();
    }
}
