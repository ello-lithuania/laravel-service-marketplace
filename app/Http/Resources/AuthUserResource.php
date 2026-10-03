<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prisijungusio vartotojo duomenys, kurie siunčiami į KIEKVIENĄ Inertia puslapį (auth.user).
 *
 * Kodėl ne visas User modelis: Inertia props matomi naršyklėje (puslapio HTML'e ir JSON'e), todėl
 * siunčiam tik tai, ko reikia UI. Taip neišduodam vidinių laukų (ban_reason, notification_settings…)
 * ir nepučiam kiekvieno atsakymo. https://laravel.com/docs/13.x/eloquent-resources
 *
 * @property User $resource
 */
class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $profile = $user->isProvider() ? $user->providerProfile : null;

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'role' => $user->role->value,
            'avatar' => $user->avatarUrl(),
            // Teikėjo santrauka meniu ir skydeliui; profilio dar gali nebūti (vedlys nepradėtas)
            'provider_profile' => $profile === null ? null : [
                'id' => $profile->id,
                'slug' => $profile->slug,
                'status' => $profile->status->value,
                'credits_balance' => $profile->credits_balance,
            ],
        ];
    }
}
