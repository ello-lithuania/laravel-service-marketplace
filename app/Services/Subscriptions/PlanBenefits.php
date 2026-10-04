<?php

namespace App\Services\Subscriptions;

use App\Models\ProviderProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Container\Attributes\Scoped;

/**
 * Etapas 9c: vienintelė vieta, kuri atsako „kokią naudą teikėjui DABAR duoda prenumerata" –
 * kategorijų ribą (vedlys) ir ženklelį „PRO" (katalogas, profilis).
 *
 * - Dabar galiojanti prenumerata – ryšys ProviderProfile::currentSubscriptions() (scope Subscription::current(),
 *   tas pats kaip Subscription::isCurrent()). Sąrašuose jis užkraunamas iš anksto (eager loading), todėl čia
 *   papildomų užklausų nėra; vienam profiliui Laravel jį užkrauna pats.
 * - Planų tik keli, todėl jų privalumai (features JSON) perskaitomi PHP'e, viena užklausa per HTTP užklausą,
 *   ir tik tada, kai tikrai reikia (bent vienas teikėjas turi galiojančią prenumeratą). Taip išvengiam JSON
 *   funkcijų SQL'e: MySQL (JSON_EXTRACT, ->>) ir SQLite (json_extract) jas rašo skirtingai.
 * - #[Scoped] – vienas objektas per HTTP užklausą ar eilės darbą (kaip CatalogCache).
 *
 * Kai prenumerata baigiasi, nieko neištrinam: teikėjas tiesiog vėl gauna nemokamą ribą, o ženklelis dingsta
 * (jis skaičiuojamas, o ne saugomas). Kategorijas, viršijančias ribą, jis pasilieka – žr. SyncProviderCategories.
 */
#[Scoped]
final class PlanBenefits
{
    /** @var array<int, array{name: string, features: PlanFeatures, is_active: bool}>|null */
    private ?array $plans = null;

    /**
     * Riba teikėjui be prenumeratos (config/marketplace.php). Bent 1 – be kategorijos profilis neaktyvuojamas.
     */
    public function freeMaxCategories(): int
    {
        return max(1, (int) config('marketplace.free_max_categories'));
    }

    /**
     * Dabar galiojanti prenumerata (paprastai viena; jei kažkodėl kelios – vėliausiai besibaigianti).
     */
    public function currentSubscription(ProviderProfile $profile): ?Subscription
    {
        return $profile->currentSubscriptions->sortByDesc('ends_at')->first();
    }

    /**
     * Dabartinio plano pavadinimas; null – teikėjas be prenumeratos.
     */
    public function currentPlanName(ProviderProfile $profile): ?string
    {
        $subscription = $this->currentSubscription($profile);

        return $subscription === null ? null : $this->plan($subscription->subscription_plan_id)['name'] ?? null;
    }

    /**
     * Kiek kategorijų teikėjas gali turėti. Mokamas planas niekada neduoda mažiau nei nemokama riba.
     */
    public function maxCategories(ProviderProfile $profile): int
    {
        $limit = $this->freeMaxCategories();

        foreach ($profile->currentSubscriptions as $subscription) {
            $limit = max($limit, $this->features($subscription)->maxCategories ?? 0);
        }

        return $limit;
    }

    /**
     * Ženklelis „PRO": bent viena dabar galiojanti prenumerata, kurios plane badge = true.
     */
    public function hasBadge(ProviderProfile $profile): bool
    {
        return $profile->currentSubscriptions
            ->contains(fn (Subscription $subscription): bool => $this->features($subscription)?->badge === true);
    }

    /**
     * Ar parduodamas planas su didesne kategorijų riba – tada vedlyje rodom nuorodą į /kainos.
     */
    public function canRaiseCategoryLimit(int $currentLimit): bool
    {
        foreach ($this->plans() as $plan) {
            if ($plan['is_active'] && ($plan['features']->maxCategories ?? 0) > $currentLimit) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pamiršta perskaitytus planus (SubscriptionPlan išsaugojus ar ištrynus – žr. modelio booted()).
     */
    public function forget(): void
    {
        $this->plans = null;
    }

    private function features(Subscription $subscription): ?PlanFeatures
    {
        return $this->plan($subscription->subscription_plan_id)['features'] ?? null;
    }

    /**
     * @return array{name: string, features: PlanFeatures, is_active: bool}|null
     */
    private function plan(int $planId): ?array
    {
        return $this->plans()[$planId] ?? null;
    }

    /**
     * Visi planai (ir nebeparduodami: jau nupirkta prenumerata savo naudą duoda iki pabaigos).
     *
     * @return array<int, array{name: string, features: PlanFeatures, is_active: bool}>
     */
    private function plans(): array
    {
        return $this->plans ??= SubscriptionPlan::query()
            ->get(['id', 'name', 'features', 'is_active'])
            ->mapWithKeys(fn (SubscriptionPlan $plan): array => [$plan->id => [
                'name' => $plan->name,
                'features' => $plan->planFeatures(),
                'is_active' => $plan->is_active,
            ]])
            ->all();
    }
}
