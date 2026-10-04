<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Actions\Payments\RefundPayment;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\RefundCalculator;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * „Grąžinti pinigus" (Etapas 9): priežastis + patvirtinimas → RefundPayment (būsena, kreditai, prenumerata,
 * kreditinė sąskaita, pranešimas teikėjui). Čia tik forma, peržiūra ir pranešimai administratoriui.
 *
 * Patvirtinimo lange administratorius iš anksto mato pasekmes (RefundCalculator::preview): kiek kreditų bus atimta,
 * kiek teikėjas jau išleido, kas nutiks prenumeratai. Pačius pinigus jis grąžina Paysera savitarnoje – tai
 * primena privaloma varnelė. https://filamentphp.com/docs/5.x/actions/modals
 */
final class RefundPaymentAction
{
    public static function make(): Action
    {
        return Action::make('refund')
            ->label('Grąžinti pinigus')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('danger')
            ->visible(fn (Payment $record): bool => $record->isPaid())
            // PaymentPolicy::refund – tik administratorius ir tik apmokėtą (Filament patikrina ir vykdydamas)
            ->authorize('refund')
            ->requiresConfirmation()
            ->modalHeading('Grąžinti mokėjimą?')
            ->modalDescription(fn (Payment $record): string => self::preview($record))
            ->schema([
                Textarea::make('reason')
                    ->label('Priežastis')
                    ->helperText('Bus nurodyta kreditinėje sąskaitoje ir pranešime teikėjui.')
                    ->required()
                    ->maxLength(500)
                    ->rows(3),
                Checkbox::make('money_returned')
                    ->label('Pinigus teikėjui grąžinsiu (ar jau grąžinau) mokėjimų tiekėjo (Paysera) savitarnoje')
                    ->accepted()
                    ->validationMessages(['accepted' => 'Pažymėkite, kad pinigus grąžinsite mokėjimų tiekėjo savitarnoje.']),
            ])
            ->modalSubmitActionLabel('Grąžinti ir išrašyti kreditinę sąskaitą')
            ->action(function (Payment $record, array $data): void {
                /** @var User $admin */
                $admin = auth()->user();

                try {
                    $refund = app(RefundPayment::class)->handle($record, (string) $data['reason'], $admin);
                } catch (InvalidStateTransitionException $e) {
                    // Pvz. kitas administratorius ką tik grąžino tą patį mokėjimą
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                $body = "Atimta kreditų: {$refund->credits_reversed}.";

                if ($refund->credits_shortfall > 0) {
                    $body .= " Nepavyko atimti (jau išleisti): {$refund->credits_shortfall}.";
                }

                Notification::make()
                    ->title("Mokėjimas grąžintas. Kreditinė sąskaita {$refund->credit_note_number}")
                    ->body($body)
                    ->success()
                    ->send();
            });
    }

    /**
     * Patvirtinimo lango tekstas – pasekmės dar prieš paspaudžiant (tas pats skaičiavimas, kurį vykdys RefundPayment).
     */
    private static function preview(Payment $payment): string
    {
        $calculation = app(RefundCalculator::class)->preview($payment);

        $lines = ['Teikėjui bus išrašyta kreditinė sąskaita '.Money::format(-$payment->amount_cents).'.'];

        if ($calculation->creditsToReverse === 0) {
            $lines[] = 'Kreditų atimti nereikės.';
        } elseif ($calculation->creditsShortfall === 0) {
            $lines[] = "Iš teikėjo balanso bus atimta {$calculation->creditsReversed} kred. (dabar turi {$calculation->balance}).";
        } else {
            $lines[] = "Mokėjimas suteikė {$calculation->creditsToReverse} kred., bet teikėjas dalį jau išleido: bus atimta tik "
                ."{$calculation->creditsReversed}, {$calculation->creditsShortfall} atimti nepavyks (balansas negali tapti neigiamas).";
        }

        if ($calculation->affectsSubscription()) {
            $lines[] = $calculation->endsSubscription()
                ? 'Prenumerata bus baigta iškart.'
                : 'Prenumerata galios iki '.$calculation->subscriptionEndsAt?->timezone('Europe/Vilnius')->format('Y-m-d').' ir nebebus pratęsiama.';
        }

        $lines[] = 'Pačius pinigus grąžinkite mokėjimų tiekėjo savitarnoje – sistema jų neperveda.';

        return implode(' ', $lines);
    }
}
