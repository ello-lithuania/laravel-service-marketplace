<?php

namespace App\Filament\Resources\SitePhotos\Tables;

use App\Enums\SitePhotoKey;
use App\Models\SitePhoto;
use App\Services\Photos\PhotoCredit;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SitePhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => self::inEnumOrder($query))
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

    /**
     * Tvarka – kaip SitePhotoKey enum'e, ne pagal id: eilutės kuriamos tada, kai jų prireikia, bet kuria tvarka.
     * CASE veikia ir SQLite, ir MySQL. „key" MySQL'e – rezervuotas žodis, todėl kabutės `key`: jas supranta abi DB
     * (SQLite – dėl suderinamumo su MySQL). Tekstas – tik literalai (Larastan: orderByRaw priima literal-string).
     *
     * @param  Builder<SitePhoto>  $query
     * @return Builder<SitePhoto>
     */
    private static function inEnumOrder(Builder $query): Builder
    {
        $sql = 'CASE `key`';
        $bindings = [];

        foreach (SitePhotoKey::cases() as $index => $key) {
            $sql .= ' WHEN ? THEN ?';
            array_push($bindings, $key->value, $index);
        }

        return $query->orderByRaw($sql.' END', $bindings);
    }
}
