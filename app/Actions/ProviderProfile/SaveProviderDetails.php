<?php

namespace App\Actions\ProviderProfile;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Vedlio 1 žingsnis: sukuria profilį (pirmą kartą) arba atnaujina jo duomenis.
 * Telefonas saugomas users lentelėje (tai žmogaus kontaktas), kiti laukai – provider_profiles.
 */
class SaveProviderDetails
{
    public function __construct(
        private GenerateProviderSlug $generateSlug,
        private ActivateCompletedProfile $activate,
    ) {}

    /**
     * @param  array<string, mixed>  $data  UpdateProviderDetailsRequest::validated()
     */
    public function handle(User $user, array $data): ProviderProfile
    {
        // Abu įrašai (vartotojas ir profilis) turi pasikeisti kartu arba nepasikeisti visai
        return DB::transaction(function () use ($user, $data): ProviderProfile {
            $user->update(['phone' => $data['phone']]);

            $attributes = Arr::except($data, ['phone']);

            // Fizinis asmuo įmonės kodo neturi – neleidžiam likti senam kodui, pakeitus tipą
            if ($attributes['type'] === ProviderType::Individual->value) {
                $attributes['company_code'] = null;
            }

            $profile = $user->providerProfile;

            if ($profile !== null) {
                $profile->update($attributes);
                $this->activate->handle($profile);

                return $profile;
            }

            $profile = $user->providerProfile()->make($attributes);
            $profile->slug = $this->generateSlug->handle((string) $attributes['display_name']);
            // Naujas profilis visada nebaigtas; aktyvus taps, kai bus užpildyti privalomi žingsniai
            $profile->forceFill(['status' => ProviderStatus::Pending])->save();

            $user->setRelation('providerProfile', $profile);

            return $profile;
        });
    }
}
