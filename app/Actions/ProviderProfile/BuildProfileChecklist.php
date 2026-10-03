<?php

namespace App\Actions\ProviderProfile;

use App\Enums\ProviderWizardStep;
use App\Models\ProviderProfile;

/**
 * Profilio pilnumas teikėjo skydeliui („Mano paskyra"): vedlio žingsniai + logotipas + atlikti darbai.
 * Logotipas ir darbai neprivalomi, bet pilnesnis profilis klientams atrodo patikimiau.
 */
class BuildProfileChecklist
{
    /**
     * @return array{percent: int, items: list<array{label: string, done: bool, required: bool, href: string|null}>}
     */
    public function handle(?ProviderProfile $profile): array
    {
        $items = array_map(fn (array $step): array => [
            'label' => $step['label'],
            'done' => $step['done'],
            'required' => $step['required'],
            'href' => $step['available'] ? $step['href'] : null,
        ], ProviderWizardStep::progress($profile));

        $items[] = [
            'label' => 'Logotipas',
            'done' => $profile !== null && $profile->hasMedia('logo'),
            'required' => false,
            'href' => $profile !== null ? route('provider.images.edit', absolute: false) : null,
        ];

        $items[] = [
            'label' => 'Atlikti darbai',
            'done' => $profile !== null && $profile->portfolioItems()->exists(),
            'required' => false,
            'href' => $profile !== null ? route('portfolio.index', absolute: false) : null,
        ];

        $done = count(array_filter($items, fn (array $item): bool => $item['done']));

        return [
            'percent' => intdiv($done * 100, count($items)),
            'items' => $items,
        ];
    }
}
