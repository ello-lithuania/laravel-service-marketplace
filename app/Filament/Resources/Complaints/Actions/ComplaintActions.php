<?php

namespace App\Filament\Resources\Complaints\Actions;

use App\Actions\Complaints\CloseComplaint;
use App\Actions\Complaints\TakeComplaintInReview;
use App\Enums\ComplaintStatus;
use App\Exceptions\ComplaintAlreadyHandledException;
use App\Models\Complaint;
use App\Models\Message;
use App\Models\Review;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Skundo nagrinėjimo veiksmai (sąraše ir peržiūroje). Logika – TakeComplaintInReview ir CloseComplaint.
 */
final class ComplaintActions
{
    public static function takeInReview(): Action
    {
        return Action::make('takeInReview')
            ->label('Imti nagrinėti')
            ->icon(Heroicon::OutlinedHandRaised)
            ->color('warning')
            ->visible(fn (Complaint $record): bool => $record->status === ComplaintStatus::Open)
            ->authorize('handle')
            ->action(self::guarded(function (Complaint $record): void {
                app(TakeComplaintInReview::class)->handle($record, self::admin());

                Notification::make()->title('Skundas paimtas nagrinėti')->success()->send();
            }));
    }

    public static function resolve(): Action
    {
        return Action::make('resolve')
            ->label('Išspręsti')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading('Pažeidimas pasitvirtino')
            ->modalSubmitActionLabel('Išspręsti')
            ->schema([
                Textarea::make('note')
                    ->label('Sprendimas (jį matys pranešėjas)')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
                // Paslėpti galima tik atsiliepimą ar žinutę (kitam turiniui – savi įrankiai)
                Toggle::make('hide_content')
                    ->label('Paslėpti skundžiamą turinį')
                    ->default(true)
                    ->visible(fn (Complaint $record): bool => $record->reportable instanceof Review || $record->reportable instanceof Message),
            ])
            ->visible(fn (Complaint $record): bool => $record->status->isOpen())
            ->authorize('handle')
            ->action(self::guarded(function (Complaint $record, array $data): void {
                app(CloseComplaint::class)->handle(
                    $record,
                    self::admin(),
                    ComplaintStatus::Resolved,
                    (string) $data['note'],
                    (bool) ($data['hide_content'] ?? false),
                );

                Notification::make()->title('Skundas išspręstas, pranešėjas informuotas')->success()->send();
            }));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Atmesti')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->modalHeading('Pažeidimo nėra')
            ->modalSubmitActionLabel('Atmesti')
            ->schema([
                Textarea::make('note')
                    ->label('Paaiškinimas (jį matys pranešėjas)')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->visible(fn (Complaint $record): bool => $record->status->isOpen())
            ->authorize('handle')
            ->action(self::guarded(function (Complaint $record, array $data): void {
                app(CloseComplaint::class)->handle($record, self::admin(), ComplaintStatus::Rejected, (string) $data['note']);

                Notification::make()->title('Skundas atmestas, pranešėjas informuotas')->success()->send();
            }));
    }

    private static function admin(): User
    {
        /** @var User */
        return auth()->user();
    }

    /**
     * Jei kitas administratorius jau paėmė ar užbaigė skundą – pranešimas, o ne 500 klaida.
     */
    private static function guarded(Closure $callback): Closure
    {
        return function (Complaint $record, array $data = []) use ($callback): void {
            try {
                $callback($record, $data);
            } catch (ComplaintAlreadyHandledException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();
            }
        };
    }
}
