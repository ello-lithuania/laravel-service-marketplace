<?php

namespace App\Filament\Resources\Complaints\Pages;

use App\Enums\ComplaintStatus;
use App\Filament\Resources\Complaints\ComplaintResource;
use App\Models\Complaint;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListComplaints extends ListRecords
{
    protected static string $resource = ComplaintResource::class;

    /**
     * Skirtukai: neužbaigti (eilė) pirmi, tada mano nagrinėjami ir užbaigti.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $openStatuses = [ComplaintStatus::Open, ComplaintStatus::InReview];

        return [
            'queue' => Tab::make('Eilė')
                ->badge(Complaint::query()->whereIn('status', $openStatuses)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $openStatuses)),
            'mine' => Tab::make('Mano nagrinėjami')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', ComplaintStatus::InReview)
                    ->where('handled_by_id', auth()->id())),
            'closed' => Tab::make('Užbaigti')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [ComplaintStatus::Resolved, ComplaintStatus::Rejected])),
            'all' => Tab::make('Visi'),
        ];
    }
}
