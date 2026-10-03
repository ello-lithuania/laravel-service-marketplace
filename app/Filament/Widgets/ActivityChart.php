<?php

namespace App\Filament\Widgets;

use App\Services\Admin\DashboardStats;
use Filament\Widgets\ChartWidget;

/**
 * Užklausos ir pasiūlymai per dieną (linijinė diagrama, Chart.js). Laikotarpis – filtras 7 / 30 / 90 d.
 * Duomenys – DashboardStats::activity() (cache 10 min.).
 * https://filamentphp.com/docs/5.x/widgets/charts
 */
class ActivityChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Užklausos ir pasiūlymai per dieną';

    protected ?string $maxHeight = '280px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '7' => '7 dienos',
            '30' => '30 dienų',
            '90' => '90 dienų',
        ];
    }

    protected function getData(): array
    {
        // Filtras ateina iš naršyklės – leidžiam tik žinomas reikšmes
        $days = in_array($this->filter, ['7', '30', '90'], true) ? (int) $this->filter : 30;
        $activity = app(DashboardStats::class)->activity($days);

        return [
            'datasets' => [
                [
                    'label' => 'Užklausos',
                    'data' => $activity['requests'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                ],
                [
                    'label' => 'Pasiūlymai',
                    'data' => $activity['offers'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $activity['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
