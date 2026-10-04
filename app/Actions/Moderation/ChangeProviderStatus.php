<?php

namespace App\Actions\Moderation;

use App\Enums\ProviderStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\ProviderStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Administratoriaus atliekami teikėjo profilio būsenos perėjimai (docs/DB_SCHEMA.md → provider_profiles → „Būsenos").
 *
 * pending → active daro sistema (ActivateCompletedProfile), kai teikėjas užbaigia vedlį. Administratorius:
 *  - paslepia (hidden) arba užblokuoja (suspended) aktyvų profilį;
 *  - užblokuoja ir nebaigtą (pending) profilį, pvz. šlamšto paskyrą;
 *  - grąžina į active, bet tik jei užpildyti privalomi žingsniai ir paskyra neužblokuota –
 *    kitaip aktyvus profilis be kategorijų ar užblokuoto žmogaus profilis atsidurtų kataloge.
 *
 * Etapas 9c: pakeitus būseną teikėjas gauna ProviderStatusChanged (su neprivaloma priežastimi) – po transakcijos,
 * kad laiškas neišeitų, jei pakeitimas atšauktas.
 */
final class ChangeProviderStatus
{
    /** Kiek simbolių priežasties dedam į laišką ir pranešimą (Filament formoje – tas pats maxLength). */
    public const REASON_MAX_LENGTH = 500;

    /**
     * Leidžiami perėjimai: iš → [į].
     *
     * @var array<string, list<ProviderStatus>>
     */
    private const TRANSITIONS = [
        'pending' => [ProviderStatus::Suspended],
        'active' => [ProviderStatus::Hidden, ProviderStatus::Suspended],
        'hidden' => [ProviderStatus::Active, ProviderStatus::Suspended],
        'suspended' => [ProviderStatus::Active, ProviderStatus::Hidden],
    ];

    public static function allows(ProviderStatus $from, ProviderStatus $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value], true);
    }

    public function handle(ProviderProfile $profile, ProviderStatus $to, ?string $reason = null): ProviderProfile
    {
        DB::transaction(function () use ($profile, $to): void {
            $locked = ProviderProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            if (! self::allows($locked->status, $to)) {
                throw new InvalidStateTransitionException(__('moderation.provider_status.not_allowed', [
                    'from' => $locked->status->label(),
                    'to' => $to->label(),
                ]));
            }

            if ($to === ProviderStatus::Active) {
                $this->ensureCanBeActive($locked);
            }

            $profile->forceFill(['status' => $to])->save();
        });

        // Savininkas užkraunamas iš naujo, o ne imamas iš $profile->user: Filament sąraše tas ryšys užkrautas tik su
        // keliais stulpeliais (be email_verified_at), o via() pagal jį sprendžia, ar siųsti laišką.
        // Anonimizuotas (soft deleted) vartotojas nerandamas – pranešti nėra kam. Užblokuotai paskyrai nepranešam:
        // prisijungti ji negali, o apie blokavimą žmogus sužino prisijungimo puslapyje
        $user = User::query()->find($profile->user_id);

        if ($user !== null && $user->banned_at === null) {
            $user->notify(new ProviderStatusChanged($profile, $to, self::cleanReason($reason)));
        }

        return $profile;
    }

    /**
     * Tuščia priežastis = jokios priežasties (laiške eilutės „Priežastis:" nebus).
     */
    private static function cleanReason(?string $reason): ?string
    {
        $reason = Str::limit(trim((string) $reason), self::REASON_MAX_LENGTH, '');

        return $reason === '' ? null : $reason;
    }

    private function ensureCanBeActive(ProviderProfile $profile): void
    {
        if (! $profile->hasRequiredSteps()) {
            throw new InvalidStateTransitionException(__('moderation.provider_status.incomplete'));
        }

        $bannedAt = User::query()->withTrashed()->whereKey($profile->user_id)->value('banned_at');

        if ($bannedAt !== null) {
            throw new InvalidStateTransitionException(__('moderation.provider_status.user_banned'));
        }
    }
}
