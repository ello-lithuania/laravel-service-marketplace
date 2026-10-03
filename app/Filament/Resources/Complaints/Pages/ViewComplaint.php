<?php

namespace App\Filament\Resources\Complaints\Pages;

use App\Filament\Resources\Complaints\Actions\ComplaintActions;
use App\Filament\Resources\Complaints\ComplaintResource;
use Filament\Resources\Pages\ViewRecord;

class ViewComplaint extends ViewRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ComplaintActions::takeInReview(),
            ComplaintActions::resolve(),
            ComplaintActions::reject(),
        ];
    }
}
