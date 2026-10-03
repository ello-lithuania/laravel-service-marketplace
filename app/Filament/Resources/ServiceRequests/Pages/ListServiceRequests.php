<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Enums\ServiceRequestStatus;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListServiceRequests extends ListRecords
{
    protected static string $resource = ServiceRequestResource::class;

    /**
     * Skirtukai virš lentelės: moderavimo eilė pirmoje vietoje.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Laukia patvirtinimo')
                ->badge(ServiceRequest::query()->where('status', ServiceRequestStatus::Pending)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceRequestStatus::Pending)),
            'open' => Tab::make('Atviros')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceRequestStatus::Open)),
            'all' => Tab::make('Visos'),
        ];
    }
}
