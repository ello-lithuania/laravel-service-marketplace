<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Sąskaitų numeracijos serija (Etapas 9, docs/DB_SCHEMA.md → invoice_sequences).
 *
 * DB (invoice_sequences.series) saugomas loginis vardas, o spausdinamos serijos raidės (SF, KS) – iš config:
 * pakeitus prefiksą .env faile, skaitiklis nesusimaišo.
 */
enum InvoiceSeries: string implements HasLabel
{
    use HasFilamentLabel;

    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Sąskaita faktūra',
            self::CreditNote => 'Kreditinė sąskaita faktūra',
        };
    }

    /**
     * Serijos raidės numeryje: SF-2026-000123, KS-2026-000001.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Invoice => (string) config('invoices.prefix'),
            self::CreditNote => (string) config('invoices.credit_note_prefix'),
        };
    }
}
