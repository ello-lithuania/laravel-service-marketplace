<?php

namespace App\Services\Invoices;

use App\Models\Payment;
use App\Models\Refund;
use App\Support\AmountInWords;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sąskaita faktūra PDF formatu: Blade šablonas (resources/views/invoices/invoice.blade.php) → dompdf → PDF.
 * Etapas 9: tas pats šablonas ir kreditinei sąskaitai (grąžinimui) – sumos su minusu, nuoroda į koreguojamą sąskaitą.
 *
 * Dompdf HTML ir CSS paverčia PDF be naršyklės ar išorinių programų. Lietuviškoms raidėms reikia Unicode šrifto –
 * naudojam DejaVu Sans, kurį dompdf platina kartu. https://github.com/barryvdh/laravel-dompdf
 * Alternatyvos: Browsershot (Chrome – gražesnis CSS, bet reikia Node ir Chromium serveryje), Snappy (wkhtmltopdf).
 */
class InvoicePdf
{
    public function __construct(private readonly BillingDetails $billingDetails) {}

    public function download(Payment $payment): Response
    {
        return Pdf::loadView('invoices.invoice', $this->data($payment))
            ->setPaper('a4')
            ->download($payment->invoice_number.'.pdf');
    }

    /**
     * Šablono duomenys. Rekvizitai – iš billing_details (išrašymo metu); seed'ų mokėjimams jų nėra,
     * todėl tada imami dabartiniai.
     *
     * @return array<string, mixed>
     */
    public function data(Payment $payment): array
    {
        $payment->loadMissing(['user', 'purchasable']);

        $details = $payment->billing_details ?? $this->billingDetails->snapshot($payment->user);
        $vatPayer = (bool) ($details['vat_payer'] ?? false);
        $vatRate = (int) ($details['vat_rate'] ?? 0);

        ['net' => $net, 'vat' => $vat, 'gross' => $gross] = $this->amounts($payment->amount_cents, $vatPayer, $vatRate);

        $timezone = (string) config('invoices.timezone');

        return [
            'title' => $vatPayer ? 'PVM sąskaita faktūra' : 'Sąskaita faktūra',
            'number' => $payment->invoice_number,
            'date' => $payment->paid_at?->timezone($timezone)->format('Y-m-d'),
            'seller' => $details['seller'] ?? [],
            'buyer' => $details['buyer'] ?? [],
            'line' => [
                'description' => $payment->description(),
                'quantity' => 1,
                'unit_price' => Money::format($net),
                'total' => Money::format($net),
            ],
            'vat_payer' => $vatPayer,
            'vat_rate' => $vatRate,
            'net' => Money::format($net),
            'vat' => Money::format($vat),
            'gross' => Money::format($gross),
            // Etapas 9: suma žodžiais (Str::ucfirst – daugiabaitis, todėl „Š" tampa didžiąja teisingai)
            'amount_in_words' => Str::ucfirst(AmountInWords::eur($gross)),
            'payment_method' => $payment->gateway->label(),
            'payment_reference' => $payment->uuid,
            'credit_note' => null,
        ];
    }

    // --- Etapas 9b: kreditinė sąskaita faktūra -----------------------------------

    public function downloadCreditNote(Refund $refund): Response
    {
        return Pdf::loadView('invoices.invoice', $this->creditNoteData($refund))
            ->setPaper('a4')
            ->download($refund->credit_note_number.'.pdf');
    }

    /**
     * Kreditinės sąskaitos duomenys: tie patys rekvizitai (snapshot'as), tas pats PVM skaičiavimas kaip sąskaitoje,
     * tik sumos neigiamos – kartu su sąskaita faktūra jos duoda lygiai 0.
     *
     * @return array<string, mixed>
     */
    public function creditNoteData(Refund $refund): array
    {
        $refund->loadMissing('payment.purchasable');
        $payment = $refund->payment;

        $details = $refund->billing_details;
        $vatPayer = (bool) ($details['vat_payer'] ?? false);
        $vatRate = (int) ($details['vat_rate'] ?? 0);

        ['net' => $net, 'vat' => $vat, 'gross' => $gross] = $this->amounts($refund->amount_cents, $vatPayer, $vatRate);

        $timezone = (string) config('invoices.timezone');

        return [
            'title' => $vatPayer ? 'Kreditinė PVM sąskaita faktūra' : 'Kreditinė sąskaita faktūra',
            'number' => $refund->credit_note_number,
            'date' => $refund->created_at?->timezone($timezone)->format('Y-m-d'),
            'seller' => $details['seller'] ?? [],
            'buyer' => $details['buyer'] ?? [],
            'line' => [
                'description' => 'Grąžinimas: '.$payment->description(),
                'quantity' => 1,
                'unit_price' => Money::format(-$net),
                'total' => Money::format(-$net),
            ],
            'vat_payer' => $vatPayer,
            'vat_rate' => $vatRate,
            'net' => Money::format(-$net),
            'vat' => Money::format(-$vat),
            'gross' => Money::format(-$gross),
            'amount_in_words' => Str::ucfirst(AmountInWords::eur(-$gross)),
            'payment_method' => $payment->gateway->label(),
            'payment_reference' => $payment->uuid,
            'credit_note' => [
                'invoice_number' => $payment->invoice_number,
                'invoice_date' => $payment->paid_at?->timezone($timezone)->format('Y-m-d'),
                'reason' => $refund->reason,
            ],
        ];
    }

    /**
     * Kaina su PVM; PVM išskiriamas iš jos: 24,20 € su 21 % PVM = 20,00 € + 4,20 €. Ne PVM mokėtojui PVM = 0.
     *
     * @return array{net: int, vat: int, gross: int}
     */
    private function amounts(int $gross, bool $vatPayer, int $vatRate): array
    {
        $vat = $vatPayer ? (int) round($gross * $vatRate / (100 + $vatRate)) : 0;

        return ['net' => $gross - $vat, 'vat' => $vat, 'gross' => $gross];
    }
}
