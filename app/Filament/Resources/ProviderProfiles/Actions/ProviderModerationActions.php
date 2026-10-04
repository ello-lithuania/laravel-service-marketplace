<?php

namespace App\Filament\Resources\ProviderProfiles\Actions;

use App\Actions\Moderation\ChangeProviderStatus;
use App\Actions\Moderation\SetProviderVerification;
use App\Enums\ProviderStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Filament\Resources\ProviderProfiles\ProviderProfileResource;
use App\Models\ProviderProfile;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

/**
 * Teikėjo profilio moderavimo veiksmai. Leidžiamus perėjimus žino ChangeProviderStatus::allows() –
 * pagal jį rodomi tik galimi mygtukai, o Action dar kartą patikrina užrakinusi eilutę.
 *
 * Etapas 9c: po kiekvieno veiksmo (išskyrus „Nuimti Patikrintas") teikėjas gauna pranešimą – jį siunčia Actions.
 * Paslepiant ir blokuojant galima įrašyti priežastį, kurią teikėjas matys laiške ir varpelyje.
 */
final class ProviderModerationActions
{
    public static function verify(): Action
    {
        return Action::make('verify')
            ->label('Pažymėti „Patikrintas"')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Teikėjo profilyje ir kataloge bus rodomas ženklelis „Patikrintas". Teikėjas gaus pranešimą.')
            ->visible(fn (ProviderProfile $record): bool => $record->verified_at === null)
            ->authorize('moderate')
            ->action(function (ProviderProfile $record): void {
                app(SetProviderVerification::class)->handle($record, verified: true);
                Cache::forget(ProviderProfileResource::UNVERIFIED_BADGE_CACHE_KEY);

                Notification::make()->title('Teikėjas pažymėtas kaip patikrintas')->success()->send();
            });
    }

    public static function unverify(): Action
    {
        return Action::make('unverify')
            ->label('Nuimti „Patikrintas"')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (ProviderProfile $record): bool => $record->verified_at !== null)
            ->authorize('moderate')
            ->action(function (ProviderProfile $record): void {
                app(SetProviderVerification::class)->handle($record, verified: false);
                Cache::forget(ProviderProfileResource::UNVERIFIED_BADGE_CACHE_KEY);

                Notification::make()->title('Ženklelis „Patikrintas" nuimtas')->success()->send();
            });
    }

    public static function activate(): Action
    {
        return self::statusAction('activate', ProviderStatus::Active)
            ->label('Aktyvuoti')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->modalDescription('Profilis vėl bus rodomas kataloge, o teikėjas gaus naujas užklausas ir pranešimą apie tai.');
    }

    public static function hide(): Action
    {
        return self::statusAction('hide', ProviderStatus::Hidden, withReason: true)
            ->label('Paslėpti')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('warning')
            ->modalDescription('Profilis dings iš katalogo ir paieškos, teikėjas negaus naujų užklausų. Teikėjas gaus pranešimą.');
    }

    public static function suspend(): Action
    {
        return self::statusAction('suspend', ProviderStatus::Suspended, withReason: true)
            ->label('Užblokuoti profilį')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->modalDescription('Profilis dings iš katalogo, teikėjas negalės siųsti pasiūlymų. Prisijungti jis galės – '
                .'visai paskyrai užblokuoti naudokite „Vartotojai" → „Užblokuoti". Teikėjas gaus pranešimą.');
    }

    private static function statusAction(string $name, ProviderStatus $to, bool $withReason = false): Action
    {
        return Action::make($name)
            ->requiresConfirmation()
            // Etapas 9c: neprivaloma priežastis teikėjui (ne vidinė pastaba – ją mato pats teikėjas)
            ->schema($withReason ? [
                Textarea::make('reason')
                    ->label('Priežastis teikėjui (neprivaloma)')
                    ->helperText('Teikėjas ją matys el. laiške ir pranešimuose svetainėje.')
                    ->maxLength(ChangeProviderStatus::REASON_MAX_LENGTH)
                    ->rows(3),
            ] : [])
            ->visible(fn (ProviderProfile $record): bool => ChangeProviderStatus::allows($record->status, $to))
            ->authorize('moderate')
            ->action(function (ProviderProfile $record, array $data) use ($to): void {
                try {
                    app(ChangeProviderStatus::class)->handle($record, $to, is_string($data['reason'] ?? null) ? $data['reason'] : null);
                } catch (InvalidStateTransitionException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Cache::forget(ProviderProfileResource::UNVERIFIED_BADGE_CACHE_KEY);
                Notification::make()->title('Būsena pakeista: '.$to->label())->success()->send();
            });
    }
}
