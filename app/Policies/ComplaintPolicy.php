<?php

namespace App\Policies;

use App\Enums\ReviewStatus;
use App\Models\Message;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Skundai (Etapas 6): kas gali pranešti apie turinį ir kas skundus nagrinėja.
 *
 * Pranešti galima tik apie tai, ką pats matai (kitaip – 404, kad neatskleistume, jog toks įrašas yra),
 * ir ne apie savo turinį (403 su priežastimi).
 */
class ComplaintPolicy
{
    // --- Administratorius (Filament ComplaintResource) ---

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Imti nagrinėti, išspręsti, atmesti.
     */
    public function handle(User $user): bool
    {
        return $user->isAdmin();
    }

    // --- Vartotojas ---

    public function create(User $user, Model $reportable): Response
    {
        if (! $this->canSee($user, $reportable)) {
            return Response::denyAsNotFound();
        }

        if ($user->isAdmin()) {
            return Response::deny(__('complaints.errors.admin'));
        }

        if ($user->banned_at !== null) {
            return Response::deny(__('complaints.errors.banned'));
        }

        return $this->isOwnContent($user, $reportable)
            ? Response::deny(__('complaints.errors.own_content'))
            : Response::allow();
    }

    /**
     * Ar vartotojas mato šį turinį – naudojam tų modelių Policies „view" taisykles.
     */
    private function canSee(User $user, Model $reportable): bool
    {
        return match (true) {
            $reportable instanceof ServiceRequest,
            $reportable instanceof Offer,
            $reportable instanceof Message,
            $reportable instanceof ProviderProfile => Gate::forUser($user)->allows('view', $reportable),
            // Viešai rodomas (paskelbtas) atsiliepimas – visiems; teikėjas mato ir laukiančius apie save
            $reportable instanceof Review => $reportable->status === ReviewStatus::Published
                || ($user->isProvider() && $user->providerProfile?->id === $reportable->provider_profile_id),
            default => false,
        };
    }

    private function isOwnContent(User $user, Model $reportable): bool
    {
        return match (true) {
            $reportable instanceof ServiceRequest => $reportable->client_id === $user->id,
            $reportable instanceof Offer => $user->providerProfile?->id === $reportable->provider_profile_id,
            $reportable instanceof Review => $reportable->author_id === $user->id,
            $reportable instanceof Message => $reportable->sender_id === $user->id,
            $reportable instanceof ProviderProfile => $reportable->user_id === $user->id,
            default => false,
        };
    }
}
