<?php

namespace App\Filament\Resources\ProviderProfiles\Pages;

use App\Enums\ProviderStatus;
use App\Filament\Resources\ProviderProfiles\Actions\ProviderModerationActions;
use App\Filament\Resources\ProviderProfiles\ProviderProfileResource;
use App\Models\ProviderProfile;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewProviderProfile extends ViewRecord
{
    protected static string $resource = ProviderProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Viešas profilis naujame skirtuke – tik aktyviam (kitaip svetainė grąžina 404)
            Action::make('public')
                ->label('Viešas profilis')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (ProviderProfile $record): string => route('providers.show', ['providerProfile' => $record->slug]))
                ->openUrlInNewTab()
                ->visible(fn (ProviderProfile $record): bool => $record->status === ProviderStatus::Active),
            ProviderModerationActions::verify(),
            ProviderModerationActions::unverify(),
            ProviderModerationActions::activate(),
            ProviderModerationActions::hide(),
            ProviderModerationActions::suspend(),
        ];
    }
}
