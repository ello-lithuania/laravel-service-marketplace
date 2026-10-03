<?php

namespace App\Services\Invoices;

use App\Enums\ProviderType;
use App\Models\ProviderProfile;
use App\Models\User;

/**
 * Sąskaitos faktūros rekvizitų „nuotrauka" (snapshot) apmokėjimo momentu → payments.billing_details.
 *
 * Kodėl saugom, o ne imam iš profilio kaskart generuojant PDF: išrašytos sąskaitos keisti negalima. Jei teikėjas
 * po metų pakeis įmonės pavadinimą ar adresą, sena sąskaita turi likti tokia, kokia buvo išrašyta.
 */
class BillingDetails
{
    /**
     * @return array{seller: array<string, string|null>, buyer: array<string, string|null>, vat_payer: bool, vat_rate: int}
     */
    public function snapshot(User $user): array
    {
        // withTrashed – sąskaita išrašoma ir „ištrinto" (soft delete) profilio teikėjui
        $profile = ProviderProfile::withTrashed()->with('city')->where('user_id', $user->id)->first();
        $isCompany = $profile !== null && $profile->type === ProviderType::Company;

        return [
            'seller' => [
                'name' => self::string(config('invoices.seller.name')) ?? (string) config('app.name'),
                'company_code' => self::string(config('invoices.seller.company_code')),
                'vat_code' => self::string(config('invoices.seller.vat_code')),
                'address' => self::string(config('invoices.seller.address')),
                'email' => self::string(config('invoices.seller.email')),
                'bank_account' => self::string(config('invoices.seller.bank_account')),
            ],
            'buyer' => [
                'name' => $isCompany ? $profile->display_name : $user->name,
                'company_code' => $isCompany ? $profile->company_code : null,
                'vat_code' => $profile?->vat_code,
                'address' => $profile?->city?->name,
                'email' => $user->email,
            ],
            'vat_payer' => (bool) config('invoices.vat_payer'),
            'vat_rate' => (int) config('invoices.vat_rate'),
        ];
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
