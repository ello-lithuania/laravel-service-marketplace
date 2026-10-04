<?php

namespace App\Actions\Moderation;

use App\Actions\Offers\WithdrawOffer;
use App\Actions\ServiceRequests\CancelServiceRequest;
use App\Enums\OfferStatus;
use App\Enums\ProviderStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Administratorius užblokuoja vartotoją (Filament → Vartotojai → „Užblokuoti").
 *
 * Kas nutinka:
 *  1. users.banned_at ir ban_reason; remember_token pakeičiamas – „Prisiminti mane" slapukas nebegalioja;
 *  2. teikėjo profilis tampa suspended – dingsta iš katalogo, negauna užklausų ir negali siųsti pasiūlymų
 *     (visos šios vietos jau tikrina status = active);
 *  3. pasirinktinai: teikėjo laukiantys pasiūlymai atšaukiami (withdrawn, kreditai negrąžinami – docs/STATES.md 3 sk.),
 *     kliento laukiančios ir atviros užklausos atšaukiamos (teikėjams grąžinami kreditai);
 *  4. DB sesijos ištrinamos. Kitus sesijų tipus (Redis, failai) išmeta EnsureUserIsNotBanned middleware
 *     prie kito paspaudimo, o prisijungti iš naujo neleidžia AuthenticateUser.
 *
 * Pasiūlymai ir užklausos atšaukiami PO blokavimo transakcijos, kiekvienas savo transakcijoje per esamas Actions:
 * jos laikosi užraktų tvarkos (užklausa → pasiūlymai → teikėjai), o mes nerakinam teikėjo eilutės anksčiau už užklausą.
 *
 * Etapas 9c: pranešimo (ProviderStatusChanged) čia nesiunčiam – priežastis vidinė, o prisijungimo puslapis praneša
 * apie blokavimą. Atblokavus ir atkūrus profilį pranešimą siunčia UnbanUser.
 */
final class BanUser
{
    public function __construct(
        private readonly WithdrawOffer $withdrawOffer,
        private readonly CancelServiceRequest $cancelServiceRequest,
    ) {}

    /**
     * @return array{withdrawn_offers: int, cancelled_requests: int}
     */
    public function handle(
        User $user,
        User $admin,
        string $reason,
        bool $withdrawOffers = true,
        bool $cancelRequests = true,
    ): array {
        if ($user->isAdmin()) {
            throw new InvalidStateTransitionException(__('moderation.ban.cannot_ban_admin'));
        }

        DB::transaction(function () use ($user, $reason): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->banned_at !== null) {
                throw new InvalidStateTransitionException(__('moderation.ban.already_banned'));
            }

            // banned_at, ban_reason ir remember_token nėra Fillable – juos keičia tik sistema
            $user->forceFill([
                'banned_at' => now(),
                'ban_reason' => Str::limit(trim($reason), 255, ''),
                'remember_token' => Str::random(60),
            ])->save();

            // Per modelį (ne masinį update()), kad suveiktų model events, jei vėliau jų atsirastų
            $profile = ProviderProfile::query()->where('user_id', $user->id)->first();

            if ($profile !== null && $profile->status !== ProviderStatus::Suspended) {
                $profile->forceFill(['status' => ProviderStatus::Suspended])->save();
            }
        });

        $result = [
            'withdrawn_offers' => $withdrawOffers ? $this->withdrawPendingOffers($user) : 0,
            'cancelled_requests' => $cancelRequests ? $this->cancelOpenRequests($user, $admin) : 0,
        ];

        $this->deleteDatabaseSessions($user);

        return $result;
    }

    private function withdrawPendingOffers(User $user): int
    {
        $offers = Offer::query()
            ->whereHas('providerProfile', fn (Builder $query) => $query->where('user_id', $user->id))
            ->where('status', OfferStatus::Pending)
            ->whereHas('serviceRequest', fn (Builder $query) => $query->where('status', ServiceRequestStatus::Open))
            ->get();

        $count = 0;

        foreach ($offers as $offer) {
            try {
                $this->withdrawOffer->handle($offer);
                $count++;
            } catch (InvalidStateTransitionException) {
                // Būsena pasikeitė lygiagrečiai (pvz. klientas ką tik priėmė) – paliekam kaip yra
            }
        }

        return $count;
    }

    private function cancelOpenRequests(User $user, User $admin): int
    {
        $requests = ServiceRequest::query()
            ->where('client_id', $user->id)
            ->whereIn('status', [ServiceRequestStatus::Pending, ServiceRequestStatus::Open])
            ->get();

        $count = 0;

        foreach ($requests as $request) {
            try {
                $this->cancelServiceRequest->handle($request, $admin, __('moderation.ban.request_cancel_reason'));
                $count++;
            } catch (InvalidStateTransitionException) {
                // Lygiagrečiai pasikeitusi būsena – praleidžiam
            }
        }

        return $count;
    }

    /**
     * SESSION_DRIVER=database – sesijos lentelėje, todėl jas galima ištrinti iš karto.
     */
    private function deleteDatabaseSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }
}
