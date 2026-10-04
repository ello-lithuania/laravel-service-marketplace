<?php

namespace App\Filament\Resources\Users\Actions;

use App\Actions\Moderation\BanUser;
use App\Actions\Moderation\UnbanUser;
use App\Actions\Privacy\AnonymizeUser;
use App\Enums\ProviderStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Vartotojo blokavimas ir atblokavimas – naudojama ir sąraše, ir peržiūroje.
 * Logika – Actions (BanUser, UnbanUser), teisės – UserPolicy (authorize('ban') / authorize('unban')):
 * neturint teisės, Filament mygtuko nerodo.
 * https://filamentphp.com/docs/5.x/actions/overview
 */
final class UserModerationActions
{
    public static function ban(): Action
    {
        return Action::make('ban')
            ->label('Užblokuoti')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->modalHeading('Užblokuoti vartotoją')
            ->modalDescription('Vartotojas bus atjungtas ir nebegalės prisijungti. Teikėjo profilis dings iš katalogo.')
            ->modalSubmitActionLabel('Užblokuoti')
            ->schema([
                Textarea::make('reason')
                    ->label('Priežastis (matys tik administratoriai)')
                    ->required()
                    ->maxLength(255)
                    ->rows(3),
                Toggle::make('withdraw_offers')
                    ->label('Atšaukti laukiančius pasiūlymus (kreditai negrąžinami)')
                    ->default(true)
                    ->visible(fn (User $record): bool => $record->isProvider()),
                Toggle::make('cancel_requests')
                    ->label('Atšaukti laukiančias ir atviras užklausas (teikėjams grąžinami kreditai)')
                    ->default(true)
                    ->visible(fn (User $record): bool => $record->isClient()),
            ])
            ->authorize('ban')
            ->action(function (User $record, array $data): void {
                /** @var User $admin */
                $admin = auth()->user();

                try {
                    $result = app(BanUser::class)->handle(
                        $record,
                        $admin,
                        (string) $data['reason'],
                        withdrawOffers: (bool) ($data['withdraw_offers'] ?? false),
                        cancelRequests: (bool) ($data['cancel_requests'] ?? false),
                    );
                } catch (InvalidStateTransitionException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Vartotojas užblokuotas')
                    ->body(sprintf(
                        'Atšaukta pasiūlymų: %d, užklausų: %d.',
                        $result['withdrawn_offers'],
                        $result['cancelled_requests'],
                    ))
                    ->success()
                    ->send();
            });
    }

    /**
     * BDAR: paskyros anonimizavimas administratoriaus iniciatyva (žmogus paprašė el. paštu). Ta pati logika kaip
     * savitarnoje (AnonymizeUser), todėl rezultatas vienodas.
     */
    public static function anonymize(): Action
    {
        return Action::make('anonymize')
            ->label('Ištrinti duomenis (BDAR)')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Anonimizuoti paskyrą?')
            ->modalDescription('Vardas, el. paštas, telefonas, nuotraukos ir pranešimai bus negrįžtamai pašalinti, atviros '
                .'užklausos ir pasiūlymai – atšaukti. Užklausos, atsiliepimai ir mokėjimai liks su „Ištrintas vartotojas".')
            ->modalSubmitActionLabel('Anonimizuoti')
            ->authorize('anonymize')
            ->action(function (User $record): void {
                app(AnonymizeUser::class)->handle($record);

                Notification::make()->title('Paskyra anonimizuota')->success()->send();
            });
    }

    public static function unban(): Action
    {
        return Action::make('unban')
            ->label('Atblokuoti')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Atblokuoti vartotoją?')
            ->modalSubmitActionLabel('Atblokuoti')
            ->schema([
                Toggle::make('restore_profile')
                    ->label('Atkurti teikėjo profilį (vėl rodomas kataloge)')
                    // Etapas 9c: UnbanUser siunčia ProviderStatusChanged
                    ->helperText('Teikėjas gaus pranešimą el. paštu ir svetainėje.')
                    ->default(true)
                    ->visible(fn (User $record): bool => $record->providerProfile?->status === ProviderStatus::Suspended),
            ])
            ->authorize('unban')
            ->action(function (User $record, array $data): void {
                app(UnbanUser::class)->handle($record, restoreProfile: (bool) ($data['restore_profile'] ?? false));

                Notification::make()->title('Vartotojas atblokuotas')->success()->send();
            });
    }
}
