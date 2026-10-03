<?php

namespace App\Filament\Resources\ProviderProfiles\Actions;

use App\Actions\Moderation\ChangeProviderStatus;
use App\Actions\Moderation\SetProviderVerification;
use App\Enums\ProviderStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Filament\Resources\ProviderProfiles\ProviderProfileResource;
use App\Models\ProviderProfile;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

/**
 * Teikėjo profilio moderavimo veiksmai. Leidžiamus perėjimus žino ChangeProviderStatus::allows() –
 * pagal jį rodomi tik galimi mygtukai, o Action dar kartą patikrina užrakinusi eilutę.
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
            ->modalDescription('Teikėjo profilyje ir kataloge bus rodomas ženklelis „Patikrintas".')
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
            ->modalDescription('Profilis vėl bus rodomas kataloge, o teikėjas gaus naujas užklausas.');
    }

    public static function hide(): Action
    {
        return self::statusAction('hide', ProviderStatus::Hidden)
            ->label('Paslėpti')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('warning')
            ->modalDescription('Profilis dings iš katalogo ir paieškos, teikėjas negaus naujų užklausų.');
    }

    public static function suspend(): Action
    {
        return self::statusAction('suspend', ProviderStatus::Suspended)
            ->label('Užblokuoti profilį')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->modalDescription('Profilis dings iš katalogo, teikėjas negalės siųsti pasiūlymų. Prisijungti jis galės – '
                .'visai paskyrai užblokuoti naudokite „Vartotojai" → „Užblokuoti".');
    }

    private static function statusAction(string $name, ProviderStatus $to): Action
    {
        return Action::make($name)
            ->requiresConfirmation()
            ->visible(fn (ProviderProfile $record): bool => ChangeProviderStatus::allows($record->status, $to))
            ->authorize('moderate')
            ->action(function (ProviderProfile $record) use ($to): void {
                try {
                    app(ChangeProviderStatus::class)->handle($record, $to);
                } catch (InvalidStateTransitionException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Cache::forget(ProviderProfileResource::UNVERIFIED_BADGE_CACHE_KEY);
                Notification::make()->title('Būsena pakeista: '.$to->label())->success()->send();
            });
    }
}
