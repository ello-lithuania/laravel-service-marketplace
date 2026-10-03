<?php

namespace App\Actions\Moderation;

use App\Enums\ProviderStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Administratoriaus atliekami teikėjo profilio būsenos perėjimai (docs/DB_SCHEMA.md → provider_profiles → „Būsenos").
 *
 * pending → active daro sistema (ActivateCompletedProfile), kai teikėjas užbaigia vedlį. Administratorius:
 *  - paslepia (hidden) arba užblokuoja (suspended) aktyvų profilį;
 *  - užblokuoja ir nebaigtą (pending) profilį, pvz. šlamšto paskyrą;
 *  - grąžina į active, bet tik jei užpildyti privalomi žingsniai ir paskyra neužblokuota –
 *    kitaip aktyvus profilis be kategorijų ar užblokuoto žmogaus profilis atsidurtų kataloge.
 */
final class ChangeProviderStatus
{
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

    public function handle(ProviderProfile $profile, ProviderStatus $to): ProviderProfile
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

        return $profile;
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
