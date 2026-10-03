{{--
    Sąskaita faktūra PDF'ui (dompdf, Etapas 7). Tai ne Inertia puslapis, todėl – paprastas Blade šablonas.
    Dompdf supranta tik dalį CSS (be flex/grid), todėl išdėstymas – lentelėmis.
    DejaVu Sans – Unicode šriftas su lietuviškomis raidėmis (platinamas kartu su dompdf).
--}}
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $number }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .parties { width: 100%; margin: 24px 0; border-collapse: collapse; }
        .parties td { width: 50%; vertical-align: top; padding: 0 12px 0 0; }
        .parties h2 { font-size: 12px; text-transform: uppercase; color: #6b7280; margin: 0 0 6px; }
        .lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .lines th { text-align: left; border-bottom: 1px solid #d1d5db; padding: 6px 4px; font-size: 10px; color: #6b7280; }
        .lines td { border-bottom: 1px solid #e5e7eb; padding: 8px 4px; }
        .right { text-align: right; }
        .totals { width: 45%; margin-left: 55%; margin-top: 12px; border-collapse: collapse; }
        .totals td { padding: 4px; }
        .totals .grand td { font-weight: bold; font-size: 13px; border-top: 1px solid #1f2937; }
        .footer { margin-top: 32px; font-size: 10px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div>Serija ir Nr. <strong>{{ $number }}</strong></div>
    <div class="muted">Išrašymo data: {{ $date }}</div>

    <table class="parties">
        <tr>
            <td>
                <h2>Pardavėjas</h2>
                <strong>{{ $seller['name'] ?? '' }}</strong><br>
                @if (! empty($seller['company_code']))Įmonės kodas: {{ $seller['company_code'] }}<br>@endif
                @if (! empty($seller['vat_code']))PVM mokėtojo kodas: {{ $seller['vat_code'] }}<br>@endif
                @if (! empty($seller['address'])){{ $seller['address'] }}<br>@endif
                @if (! empty($seller['email'])){{ $seller['email'] }}<br>@endif
                @if (! empty($seller['bank_account']))A. s. {{ $seller['bank_account'] }}@endif
            </td>
            <td>
                <h2>Pirkėjas</h2>
                <strong>{{ $buyer['name'] ?? '' }}</strong><br>
                @if (! empty($buyer['company_code']))Įmonės kodas: {{ $buyer['company_code'] }}<br>@endif
                @if (! empty($buyer['vat_code']))PVM mokėtojo kodas: {{ $buyer['vat_code'] }}<br>@endif
                @if (! empty($buyer['address'])){{ $buyer['address'] }}<br>@endif
                @if (! empty($buyer['email'])){{ $buyer['email'] }}@endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Paslauga</th>
                <th class="right">Kiekis</th>
                <th class="right">Kaina{{ $vat_payer ? ' be PVM' : '' }}</th>
                <th class="right">Suma{{ $vat_payer ? ' be PVM' : '' }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $line['description'] }}</td>
                <td class="right">{{ $line['quantity'] }}</td>
                <td class="right">{{ $line['unit_price'] }}</td>
                <td class="right">{{ $line['total'] }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        @if ($vat_payer)
            <tr><td>Suma be PVM</td><td class="right">{{ $net }}</td></tr>
            <tr><td>PVM {{ $vat_rate }} %</td><td class="right">{{ $vat }}</td></tr>
        @endif
        <tr class="grand"><td>Iš viso mokėti</td><td class="right">{{ $gross }}</td></tr>
    </table>

    <div class="footer muted">
        Apmokėta: {{ $payment_method }}, {{ $date }}. Užsakymo Nr. {{ $payment_reference }}.<br>
        @unless ($vat_payer)Pardavėjas nėra PVM mokėtojas.<br>@endunless
        Sąskaita išrašyta elektroniniu būdu ir galioja be parašo.
    </div>
</body>
</html>
