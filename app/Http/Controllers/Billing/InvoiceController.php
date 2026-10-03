<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Invoices\InvoicePdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sąskaitos faktūros PDF atsisiuntimas: teikėjui – savo, administratoriui – bet kurio (PaymentPolicy::downloadInvoice).
 */
class InvoiceController extends Controller
{
    public function __invoke(Payment $payment, InvoicePdf $pdf): Response
    {
        Gate::authorize('downloadInvoice', $payment);

        return $pdf->download($payment);
    }
}
