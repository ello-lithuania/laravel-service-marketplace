<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Filament\Resources\ServiceRequests\Actions\ModerationActions;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceRequest extends ViewRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ModerationActions::approve(),
            ModerationActions::reject(),
            ModerationActions::cancel(),
        ];
    }
}
