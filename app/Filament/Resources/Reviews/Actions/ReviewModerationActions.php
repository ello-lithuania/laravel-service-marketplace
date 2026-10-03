<?php

namespace App\Filament\Resources\Reviews\Actions;

use App\Actions\Reviews\HideReview;
use App\Actions\Reviews\PublishReview;
use App\Enums\ReviewStatus;
use App\Models\Review;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Atsiliepimų moderavimo veiksmai – sąraše, peržiūroje ir masiniai (pažymėtiems).
 * Patys pakeitimai – Action klasėse; reitingą perskaičiuoja ReviewObserver.
 *
 * Masinis veiksmas keičia kiekvieną įrašą per modelį (foreach → save()), o ne vienu
 * Review::query()->update(): masinis UPDATE Eloquent įvykių nekelia, ir reitingas nepersiskaičiuotų.
 */
final class ReviewModerationActions
{
    public static function publish(): Action
    {
        return Action::make('publish')
            ->label('Paskelbti')
            ->icon(Heroicon::OutlinedEye)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Paskelbti atsiliepimą?')
            ->modalDescription('Atsiliepimas bus rodomas teikėjo profilyje ir įskaičiuotas į reitingą.')
            ->visible(fn (Review $record): bool => $record->status !== ReviewStatus::Published)
            ->authorize('moderate')
            ->action(function (Review $record): void {
                app(PublishReview::class)->handle($record);

                Notification::make()->title('Atsiliepimas paskelbtas')->success()->send();
            });
    }

    public static function hide(): Action
    {
        return Action::make('hide')
            ->label('Paslėpti')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Paslėpti atsiliepimą?')
            ->modalDescription('Atsiliepimas nebus rodomas viešai ir nebus įskaičiuotas į reitingą.')
            ->visible(fn (Review $record): bool => $record->status !== ReviewStatus::Hidden)
            ->authorize('moderate')
            ->action(function (Review $record): void {
                app(HideReview::class)->handle($record);

                Notification::make()->title('Atsiliepimas paslėptas')->success()->send();
            });
    }

    public static function publishBulk(): BulkAction
    {
        return BulkAction::make('publishSelected')
            ->label('Paskelbti pažymėtus')
            ->icon(Heroicon::OutlinedEye)
            ->color('success')
            ->requiresConfirmation()
            ->action(function (Collection $records): void {
                foreach ($records as $review) {
                    if ($review instanceof Review) {
                        app(PublishReview::class)->handle($review);
                    }
                }

                Notification::make()->title('Paskelbta: '.$records->count())->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function hideBulk(): BulkAction
    {
        return BulkAction::make('hideSelected')
            ->label('Paslėpti pažymėtus')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (Collection $records): void {
                foreach ($records as $review) {
                    if ($review instanceof Review) {
                        app(HideReview::class)->handle($review);
                    }
                }

                Notification::make()->title('Paslėpta: '.$records->count())->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
