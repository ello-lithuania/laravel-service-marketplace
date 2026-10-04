<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Invoices\InvoicePdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kreditinės sąskaitos faktūros PDF (Etapas 9): teikėjui – savo grąžinto mokėjimo, administratoriui – bet kurio
 * (PaymentPolicy::downloadCreditNote). URL'e – mokėjimo uuid, kaip ir sąskaitos faktūros.
 */
class CreditNoteController extends Controller
{
    public function __invoke(Payment $payment, InvoicePdf $pdf): Response
    {
        Gate::authorize('downloadCreditNote', $payment);

        return $pdf->downloadCreditNote($payment->refund()->firstOrFail());
    }
}
