<?php

namespace App\Services\Invoices;

use App\Models\Payment;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sąskaita faktūra PDF formatu: Blade šablonas (resources/views/invoices/invoice.blade.php) → dompdf → PDF.
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

        // Kaina su PVM; PVM išskiriamas iš jos: 24,20 € su 21 % PVM = 20,00 € + 4,20 €
        $gross = $payment->amount_cents;
        $vat = $vatPayer ? (int) round($gross * $vatRate / (100 + $vatRate)) : 0;
        $net = $gross - $vat;

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
            'payment_method' => $payment->gateway->label(),
            'payment_reference' => $payment->uuid,
        ];
    }
}
