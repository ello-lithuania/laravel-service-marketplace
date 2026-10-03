<?php

namespace App\Filament\Resources\Payments\Widgets;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Money;
use DateTimeInterface;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Pajamų suvestinė mokėjimų sąraše: šio mėnesio ir 30 d. pajamos, laukiantys mokėjimai.
 * Filament widget – Livewire komponentas, todėl skaičiai atsinaujina be puslapio perkrovimo.
 * https://filamentphp.com/docs/5.x/widgets/stats-overview
 */
class PaymentStats extends StatsOverviewWidget
{
    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        // Mėnesio pradžia – Lietuvos laiku, DB – UTC
        $monthStart = now('Europe/Vilnius')->startOfMonth()->utc();

        $month = $this->revenueSince($monthStart);
        $last30 = $this->revenueSince(now()->subDays(30));
        $pending = Payment::query()->where('status', PaymentStatus::Pending)->count();

        return [
            Stat::make('Pajamos šį mėnesį', Money::format($month['sum']))
                ->description("Apmokėtų mokėjimų: {$month['count']}")
                ->color('success'),
            Stat::make('Pajamos per 30 dienų', Money::format($last30['sum']))
                ->description("Apmokėtų mokėjimų: {$last30['count']}"),
            Stat::make('Laukia apmokėjimo', (string) $pending)
                ->description('Pradėti, bet dar neapmokėti')
                ->color($pending > 0 ? 'warning' : 'gray'),
        ];
    }

    /**
     * @return array{sum: int, count: int}
     */
    private function revenueSince(DateTimeInterface $from): array
    {
        $row = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->where('paid_at', '>=', $from)
            ->toBase()
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as total, COUNT(*) as payments')
            ->first();

        return ['sum' => (int) ($row->total ?? 0), 'count' => (int) ($row->payments ?? 0)];
    }
}
