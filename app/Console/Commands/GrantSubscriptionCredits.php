<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\GrantSubscriptionCredits as GrantCredits;
use App\Models\Subscription;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Prenumeratų kreditai už prasidėjusius laikotarpius (Scheduler kas valandą). Rankiniu būdu:
 * php artisan subscriptions:grant-credits
 *
 * Atrenka prenumeratas, kurių kitas nesuteiktas laikotarpis jau prasidėjo ir yra apmokėtas, o pats suteikimas
 * (GrantSubscriptionCredits) idempotentiškas – komandą paleidus du kartus kreditai dvigubai neužskaitomi.
 */
#[Signature('subscriptions:grant-credits')]
#[Description('Suteikia prenumeratų kreditus už prasidėjusius apmokėtus laikotarpius')]
class GrantSubscriptionCredits extends Command
{
    public function handle(GrantCredits $grant): int
    {
        $now = now();
        $periods = 0;

        Subscription::query()
            ->live()
            ->where(fn (Builder $query) => $query
                // Dar nė karto nesuteikta ir jau prasidėjo
                ->where(fn (Builder $inner) => $inner->whereNull('credits_granted_until')->where('starts_at', '<=', $now))
                // Arba kitas laikotarpis jau prasidėjo ir yra apmokėtas
                ->orWhere(fn (Builder $inner) => $inner
                    ->whereColumn('credits_granted_until', '<', 'ends_at')
                    ->where('credits_granted_until', '<=', $now)))
            ->chunkById(200, function (Collection $subscriptions) use ($grant, &$periods): void {
                foreach ($subscriptions as $subscription) {
                    /** @var Subscription $subscription */
                    $periods += $grant->handle($subscription);
                }
            });

        $this->info("Suteikta kreditų už laikotarpių: {$periods}");

        return self::SUCCESS;
    }
}
