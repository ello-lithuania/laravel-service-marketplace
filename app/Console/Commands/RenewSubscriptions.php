<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\CreateRenewalPayment;
use App\Actions\Subscriptions\EndSubscriptionPeriod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Notifications\SubscriptionExpiring;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Kasdienis prenumeratų tvarkymas (Scheduler, routes/console.php). Rankiniu būdu: php artisan subscriptions:renew
 *
 *   1. Laikotarpis baigėsi → past_due (laukiam apmokėjimo malonės laikotarpį) arba expired (EndSubscriptionPeriod).
 *   2. Automatiškai pratęsiamoms, kurios baigiasi per artimiausias N dienų, sukuriamas pratęsimo mokėjimas ir
 *      teikėjui išsiunčiama nuoroda jam apmokėti (SubscriptionExpiring).
 *
 * Kodėl ne automatinis kortelės nuskaitymas: Paysera (mūsų sąrankoje) pasikartojančių mokėjimų nedaro. Stripe
 * atveju tai darytų pats Stripe, o mes tik gautume webhook'ą (žr. docs/drafts/etapas-7.md).
 * Komandą galima paleisti kelis kartus – ji idempotentiška (antras pratęsimo mokėjimas nekuriamas).
 */
#[Signature('subscriptions:renew')]
#[Description('Pažymi pasibaigusias prenumeratas ir sukuria pratęsimo mokėjimus su priminimais')]
class RenewSubscriptions extends Command
{
    public function handle(EndSubscriptionPeriod $endPeriod, CreateRenewalPayment $createRenewal): int
    {
        $now = now();
        $counts = ['past_due' => 0, 'expired' => 0, 'reminders' => 0];

        // 1. Pasibaigę laikotarpiai. Indeksas (status, ends_at)
        Subscription::query()
            ->live()
            ->where('ends_at', '<=', $now)
            ->with(['plan', 'providerProfile.user'])
            ->chunkById(200, function (Collection $subscriptions) use ($endPeriod, $createRenewal, &$counts): void {
                foreach ($subscriptions as $subscription) {
                    /** @var Subscription $subscription */
                    $status = $endPeriod->handle($subscription);

                    if ($status === SubscriptionStatus::Expired) {
                        $counts['expired']++;
                    }

                    if ($status === SubscriptionStatus::PastDue) {
                        $counts['past_due']++;
                        $counts['reminders'] += (int) $this->remind($subscription, $createRenewal, pastDue: true);
                    }
                }
            });

        // 2. Artėjanti pabaiga – pratęsimo mokėjimas ir priminimas (vieną kartą: kitą dieną mokėjimas jau bus)
        Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->where('auto_renew', true)
            ->where('starts_at', '<=', $now)
            ->whereBetween('ends_at', [$now, $now->addDays((int) config('payments.subscriptions.renewal_notice_days'))])
            ->with(['plan', 'providerProfile.user'])
            ->chunkById(200, function (Collection $subscriptions) use ($createRenewal, &$counts): void {
                foreach ($subscriptions as $subscription) {
                    /** @var Subscription $subscription */
                    $counts['reminders'] += (int) $this->remind($subscription, $createRenewal, pastDue: false);
                }
            });

        $this->info("Nesumokėtos: {$counts['past_due']}, pasibaigusios: {$counts['expired']}, išsiųsta priminimų: {$counts['reminders']}");

        return self::SUCCESS;
    }

    /**
     * Sukuria pratęsimo mokėjimą (jei jo dar nėra) ir praneša teikėjui. Įprastas priminimas siunčiamas tik
     * sukūrus naują mokėjimą; „nesumokėta" priminimas – ir esamam laukiančiam mokėjimui.
     */
    private function remind(Subscription $subscription, CreateRenewalPayment $createRenewal, bool $pastDue): bool
    {
        $payment = $createRenewal->handle($subscription);

        if ($payment === null && $pastDue) {
            $payment = Payment::query()
                ->where('subscription_id', $subscription->id)
                ->where('status', PaymentStatus::Pending)
                ->latest('id')
                ->first();
        }

        $user = $subscription->providerProfile?->user;

        if ($payment === null || $user === null) {
            return false;
        }

        $user->notify(new SubscriptionExpiring($subscription, $payment, $pastDue));

        return true;
    }
}
