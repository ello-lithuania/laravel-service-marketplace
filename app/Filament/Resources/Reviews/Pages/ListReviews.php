<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Enums\ReviewStatus;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\Review;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    /**
     * Skirtukai: moderavimo eilė (pakvietimo atsiliepimai) pirmoje vietoje.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Laukia moderavimo')
                ->badge(Review::query()->where('status', ReviewStatus::Pending)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReviewStatus::Pending)),
            'reported' => Tab::make('Su skundais')
                ->modifyQueryUsing(fn (Builder $query) => $query->has('complaints')),
            'all' => Tab::make('Visi'),
        ];
    }
}
