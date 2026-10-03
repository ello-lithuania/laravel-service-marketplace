<?php

namespace App\Filament\Widgets;

use App\Services\Admin\DashboardStats;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Platformos suvestinė admin skydelyje: vartotojai, registracijos, užklausos, pasiūlymai, konversija,
 * moderavimo eilė ir pajamos. Skaičiai – DashboardStats (cache 5 min.).
 *
 * Widget = Livewire komponentas Filament skydelyje. Klasės app/Filament/Widgets kataloge Filament randa pats
 * (AdminPanelProvider → discoverWidgets), o tvarką nurodo $sort.
 * https://filamentphp.com/docs/5.x/widgets/stats-overview
 */
class PlatformStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    // Be automatinio atnaujinimo kas 5 s (Filament numatytoji reikšmė): skaičiai vis tiek cache 5 min.
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Suvestinė';

    protected ?string $description = 'Skaičiai atnaujinami kas '.DashboardStats::STATS_TTL_MINUTES.' min.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = app(DashboardStats::class)->summary();
        $requests = $stats['requests'];

        return [
            Stat::make('Vartotojai', $this->number($stats['users']['total']))
                ->description(sprintf(
                    'Klientai %s · teikėjai %s · admin %s',
                    $this->number($stats['users']['client']),
                    $this->number($stats['users']['provider']),
                    $this->number($stats['users']['admin']),
                ))
                ->icon('heroicon-o-users'),

            Stat::make('Naujos registracijos (7 d.)', $this->number($stats['registrations']['last_7_days']))
                ->description('Per 30 d.: '.$this->number($stats['registrations']['last_30_days']))
                ->chart($this->chartPoints($stats['registrations']['daily']))
                ->color('success'),

            Stat::make('Atviros užklausos', $this->number($requests['open']))
                ->description(sprintf(
                    'Vykdoma %s · atlikta %s · pasibaigė %s',
                    $this->number($requests['in_progress']),
                    $this->number($requests['completed']),
                    $this->number($requests['expired']),
                ))
                ->icon('heroicon-o-clipboard-document-list'),

            Stat::make('Pasiūlymai (30 d.)', $this->number($stats['offers']['last_30_days']))
                ->description('Paskutinės 14 dienų')
                ->chart($this->chartPoints($stats['offers']['daily']))
                ->color('info'),

            Stat::make('Konversija', number_format($stats['conversion']['percent'], 1, ',', ' ').' %')
                ->description(sprintf(
                    'Priimtas pasiūlymas: %s iš %s užklausų (90 d.)',
                    $this->number($stats['conversion']['accepted']),
                    $this->number($stats['conversion']['published']),
                ))
                ->icon('heroicon-o-arrow-trending-up'),

            Stat::make('Laukia moderavimo', $this->number($stats['moderation']['total']))
                ->description(sprintf(
                    'Užklausos %s · skundai %s · atsiliepimai %s',
                    $this->number($stats['moderation']['requests']),
                    $this->number($stats['moderation']['complaints']),
                    $this->number($stats['moderation']['reviews']),
                ))
                ->color($stats['moderation']['total'] > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-shield-exclamation'),

            Stat::make('Pajamos (30 d.)', Money::format($stats['revenue']['last_30_days_cents']))
                ->description('Apmokėti mokėjimai: '.$this->number($stats['revenue']['payments']))
                ->icon('heroicon-o-banknotes'),
        ];
    }

    /**
     * Mini diagramos taškai: Filament tikisi float reikšmių.
     *
     * @param  list<int>  $counts
     * @return list<float>
     */
    private function chartPoints(array $counts): array
    {
        return array_map(fn (int $count): float => $count, $counts);
    }

    /**
     * 12345 → „12 345" (lietuviškas tūkstančių skirtukas – tarpas).
     */
    private function number(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }
}
