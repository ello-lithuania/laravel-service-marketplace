<?php

namespace App\Filament\Resources\ProviderProfiles\Pages;

use App\Enums\ProviderStatus;
use App\Filament\Resources\ProviderProfiles\ProviderProfileResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProviderProfiles extends ListRecords
{
    protected static string $resource = ProviderProfileResource::class;

    /**
     * Pirmas skirtukas – darbo eilė: aktyvūs, bet dar nepatikrinti teikėjai.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'unverified' => Tab::make('Nepatikrinti')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', ProviderStatus::Active)
                    ->whereNull('verified_at')),
            'active' => Tab::make('Aktyvūs')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ProviderStatus::Active)),
            'pending' => Tab::make('Nebaigti')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ProviderStatus::Pending)),
            'hidden' => Tab::make('Paslėpti ir užblokuoti')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [ProviderStatus::Hidden, ProviderStatus::Suspended])),
            'all' => Tab::make('Visi'),
        ];
    }
}
