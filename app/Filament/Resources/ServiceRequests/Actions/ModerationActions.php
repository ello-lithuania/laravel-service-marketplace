<?php

namespace App\Filament\Resources\ServiceRequests\Actions;

use App\Actions\ServiceRequests\CancelServiceRequest;
use App\Actions\ServiceRequests\PublishServiceRequest;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\ServiceRequest;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Moderavimo veiksmai (Filament Actions) – naudojami ir sąraše, ir peržiūros puslapyje.
 * Patys perėjimai vyksta Action klasėse (PublishServiceRequest, CancelServiceRequest), čia tik UI.
 * https://filamentphp.com/docs/5.x/actions/overview
 */
final class ModerationActions
{
    /**
     * pending → open. Klientas gauna pranešimą, tinkami teikėjai – NewMatchingRequest.
     */
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Patvirtinti')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Patvirtinti užklausą?')
            ->modalDescription('Užklausa bus paskelbta 30 dienų, o tinkami teikėjai gaus pranešimą.')
            ->modalSubmitActionLabel('Patvirtinti ir paskelbti')
            ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::Pending)
            ->authorize('publish')
            ->action(self::guarded(function (ServiceRequest $record): void {
                app(PublishServiceRequest::class)->handle($record, byAdmin: true);

                Notification::make()->title('Užklausa paskelbta')->success()->send();
            }));
    }

    /**
     * pending → cancelled su priežastimi (klientas ją gauna pranešime).
     */
    public static function reject(): Action
    {
        return self::cancelAction('reject')
            ->label('Atmesti')
            ->icon(Heroicon::OutlinedXCircle)
            ->modalHeading('Atmesti užklausą')
            ->modalSubmitActionLabel('Atmesti')
            ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::Pending);
    }

    /**
     * open / in_progress → cancelled (pvz. pažeidžia taisykles). Atviros užklausos teikėjams grąžinami kreditai.
     */
    public static function cancel(): Action
    {
        return self::cancelAction('cancel')
            ->label('Atšaukti')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->modalHeading('Atšaukti užklausą')
            ->modalDescription('Atviros užklausos teikėjams bus grąžinti kreditai už laukiančius pasiūlymus.')
            ->modalSubmitActionLabel('Atšaukti užklausą')
            ->visible(fn (ServiceRequest $record): bool => in_array(
                $record->status,
                [ServiceRequestStatus::Open, ServiceRequestStatus::InProgress],
                true,
            ));
    }

    private static function cancelAction(string $name): Action
    {
        return Action::make($name)
            ->color('danger')
            ->schema([
                Textarea::make('reason')
                    ->label('Priežastis (ją matys klientas)')
                    ->required()
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->authorize('cancel')
            ->action(self::guarded(function (ServiceRequest $record, array $data): void {
                /** @var User $admin */
                $admin = auth()->user();

                app(CancelServiceRequest::class)->handle($record, $admin, (string) $data['reason']);

                Notification::make()->title('Užklausa atšaukta, klientas informuotas')->success()->send();
            }));
    }

    /**
     * Jei kol administratorius žiūrėjo, būsena pasikeitė – parodom klaidą, o ne 500.
     */
    private static function guarded(Closure $callback): Closure
    {
        return function (ServiceRequest $record, array $data = []) use ($callback): void {
            try {
                $callback($record, $data);
            } catch (InvalidStateTransitionException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();
            }
        };
    }
}
