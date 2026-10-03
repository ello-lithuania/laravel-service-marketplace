<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use App\Models\ProviderProfile;
use Filament\Support\Contracts\HasLabel;

/**
 * Teikėjo profilio vedlio žingsniai (ne DB statusas – tik žingsnių sąrašas ir jų užbaigimo taisyklės).
 *
 * Ar žingsnis atliktas, nesaugom atskirame stulpelyje, o išvedam iš pačių duomenų:
 * profilis yra → duomenys užpildyti, yra kategorijų → kategorijos pasirinktos ir t.t.
 * Taip „progresas" niekada neišsiskiria su tikrais duomenimis.
 */
enum ProviderWizardStep: string implements HasLabel
{
    use HasFilamentLabel;

    case Details = 'details';
    case Categories = 'categories';
    case ServiceAreas = 'areas';
    case Prices = 'prices';

    public function label(): string
    {
        return match ($this) {
            self::Details => 'Duomenys',
            self::Categories => 'Kategorijos',
            self::ServiceAreas => 'Aptarnavimo zonos',
            self::Prices => 'Kainos',
        };
    }

    /**
     * Maršruto, kuriame rodomas žingsnis, pavadinimas (routes/account.php).
     */
    public function routeName(): string
    {
        return match ($this) {
            self::Details => 'provider.details.edit',
            self::Categories => 'provider.categories.edit',
            self::ServiceAreas => 'provider.areas.edit',
            self::Prices => 'provider.prices.edit',
        };
    }

    /**
     * Kainos neprivalomos: be jų profilis vis tiek gali būti aktyvus.
     */
    public function isRequired(): bool
    {
        return $this !== self::Prices;
    }

    public function isDone(?ProviderProfile $profile): bool
    {
        if ($profile === null) {
            return false;
        }

        return match ($this) {
            self::Details => true,
            self::Categories => $profile->categories()->exists(),
            self::ServiceAreas => $profile->serves_whole_country || $profile->serviceAreas()->exists(),
            self::Prices => $profile->categories()->wherePivotNotNull('price_from_cents')->exists(),
        };
    }

    public function next(): ?self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, true);

        return $cases[$index + 1] ?? null;
    }

    /**
     * Nuo kurio žingsnio tęsti: pirmas neatliktas privalomas, o jei visi atlikti – nuo pradžių
     * (tada vedlys tampa profilio redagavimo forma).
     */
    public static function resume(?ProviderProfile $profile): self
    {
        foreach (self::cases() as $step) {
            if ($step->isRequired() && ! $step->isDone($profile)) {
                return $step;
            }
        }

        return self::Details;
    }

    /**
     * Progreso juosta Vue puslapiui. Kol profilio nėra, kiti žingsniai neprieinami
     * (kategorijas ir zonas reikia kur nors išsaugoti – o tam reikia profilio).
     *
     * @return list<array{key: string, label: string, href: string, done: bool, required: bool, available: bool}>
     */
    public static function progress(?ProviderProfile $profile): array
    {
        return array_map(fn (self $step): array => [
            'key' => $step->value,
            'label' => $step->label(),
            'href' => route($step->routeName(), absolute: false),
            'done' => $step->isDone($profile),
            'required' => $step->isRequired(),
            'available' => $step === self::Details || $profile !== null,
        ], self::cases());
    }
}
