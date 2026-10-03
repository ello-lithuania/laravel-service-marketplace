<?php

namespace App\Filament\Resources\Subscriptions\Actions;

use App\Actions\Subscriptions\CancelSubscription;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Administratorius atšaukia prenumeratą: aktyvi galios iki apmokėto laikotarpio pabaigos, nesumokėta baigiama iškart.
 */
final class CancelSubscriptionAction
{
    public static function make(): Action
    {
        return Action::make('cancel')
            ->label('Atšaukti')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Atšaukti prenumeratą?')
            ->modalDescription('Aktyvi prenumerata galios iki apmokėto laikotarpio pabaigos ir nebebus pratęsiama. Nesumokėta baigsis iškart.')
            ->modalSubmitActionLabel('Atšaukti prenumeratą')
            ->visible(fn (Subscription $record): bool => in_array($record->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true))
            ->authorize('cancel')
            ->action(function (Subscription $record): void {
                try {
                    app(CancelSubscription::class)->handle($record);
                } catch (InvalidStateTransitionException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Prenumerata atšaukta')->success()->send();
            });
    }
}
