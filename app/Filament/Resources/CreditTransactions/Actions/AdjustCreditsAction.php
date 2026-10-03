<?php

namespace App\Filament\Resources\CreditTransactions\Actions;

use App\Actions\Credits\AdjustCredits;
use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\ProviderProfile;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * „Koreguoti kreditus": administratorius prideda (+) arba atima (−) teikėjo kreditų su priežastimi.
 * Logika – AdjustCredits (per CreditLedger), čia tik forma ir pranešimai.
 */
final class AdjustCreditsAction
{
    public static function make(): Action
    {
        return Action::make('adjustCredits')
            ->label('Koreguoti kreditus')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->visible(fn (): bool => auth()->user()?->can('adjust', CreditTransaction::class) ?? false)
            ->schema([
                // Teikėjų tūkstančiai – ieškom serveryje, o ne užkraunam visų į sąrašą
                Select::make('provider_profile_id')
                    ->label('Teikėjas')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => ProviderProfile::query()
                        ->where('display_name', 'like', '%'.$search.'%')
                        ->orderBy('display_name')
                        ->limit(20)
                        ->pluck('display_name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => ProviderProfile::query()->whereKey($value)->value('display_name'))
                    ->required(),
                TextInput::make('amount')
                    ->label('Kiekis')
                    ->helperText('Teigiamas – pridėti, neigiamas – atimti (pvz. -5). Balansas negali tapti neigiamas.')
                    ->integer()
                    ->required()
                    ->notIn(['0'])
                    ->minValue(-10000)
                    ->maxValue(10000),
                Textarea::make('reason')
                    ->label('Priežastis')
                    ->helperText('Matys teikėjas savo kreditų istorijoje.')
                    ->required()
                    ->maxLength(200)
                    ->rows(2),
            ])
            ->requiresConfirmation()
            ->modalHeading('Koreguoti teikėjo kreditus')
            ->modalDescription('Bus įrašyta nauja kreditų operacija. Senų įrašų keisti negalima – tik pridėti naują.')
            ->modalSubmitActionLabel('Koreguoti')
            ->action(function (array $data): void {
                /** @var User $admin */
                $admin = auth()->user();
                $provider = ProviderProfile::query()->whereKey($data['provider_profile_id'])->firstOrFail();

                try {
                    $transaction = app(AdjustCredits::class)->handle($provider, (int) $data['amount'], (string) $data['reason'], $admin);
                } catch (InsufficientCreditsException $e) {
                    Notification::make()
                        ->title("Nepakanka kreditų: teikėjas turi {$e->balance}, o atimti norite {$e->required}.")
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("Kreditai pakoreguoti. Naujas balansas: {$transaction->balance_after}")
                    ->success()
                    ->send();
            });
    }
}
