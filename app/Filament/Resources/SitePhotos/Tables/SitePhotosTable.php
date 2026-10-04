<?php

namespace App\Filament\Resources\SitePhotos\Tables;

use App\Models\SitePhoto;
use App\Services\Photos\PhotoCredit;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SitePhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eilutės sukurtos SitePhotoKey tvarka, todėl id tvarka = enum tvarka
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                // Stulpelis pats užkrauna media (with('media')) – be N+1
                SpatieMediaLibraryImageColumn::make('photo')
                    ->label('Nuotrauka')
                    ->collection('photo')
                    ->conversion('card')
                    ->imageHeight(64),
                // Enum su HasLabel – Filament parodo label() („Pradžios puslapio viršus"), ne reikšmę
                TextColumn::make('key')
                    ->label('Vieta'),
                TextColumn::make('alt')
                    ->label('Alternatyvusis tekstas')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('credit')
                    ->label('Autorius')
                    ->state(fn (SitePhoto $record): ?string => PhotoCredit::fromMedia($record->getFirstMedia('photo'))?->summary())
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(fn (SitePhoto $record): string => 'Svetainės nuotrauka: '.$record->key->label()),
            ]);
    }
}
