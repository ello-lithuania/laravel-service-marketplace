<?php

namespace App\Services\Invoices;

use App\Enums\InvoiceSeries;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Sąskaitų numeriai: SF-2026-000123 – ištisinė numeracija kiekvienos serijos ir kiekvienų metų viduje
 * (docs/DB_SCHEMA.md → invoice_sequences). Etapas 9: serijos – sąskaitos faktūros (SF) ir kreditinės sąskaitos (KS).
 *
 * Kodėl ne MAX(invoice_number) + 1: du vienu metu apmokami mokėjimai perskaitytų tą patį MAX ir gautų tą patį
 * numerį. Skaitiklio eilutė užrakinama (SELECT … FOR UPDATE), todėl antras laukia, kol pirmas baigs transakciją.
 * Kviečiama TOS PAČIOS transakcijos viduje, kurioje mokėjimas tampa „paid" (ar „refunded"): jei transakcija
 * atšaukiama, atšaukiamas ir skaitiklio padidinimas – numeracijoje neatsiranda tarpų.
 */
class InvoiceNumberGenerator
{
    public function next(CarbonInterface $date, InvoiceSeries $series = InvoiceSeries::Invoice): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Sąskaitos numeris išduodamas tik DB transakcijos viduje (kartu su mokėjimo būsena).');
        }

        $year = (int) $date->copy()->setTimezone((string) config('invoices.timezone'))->format('Y');

        $last = $this->lockedLastNumber($series, $year);
        $number = $last + 1;

        DB::table('invoice_sequences')
            ->where('series', $series->value)
            ->where('year', $year)
            ->update(['last_number' => $number, 'updated_at' => now()]);

        return $this->format($year, $number, $series);
    }

    public function format(int $year, int $number, InvoiceSeries $series = InvoiceSeries::Invoice): string
    {
        return sprintf('%s-%d-%06d', $series->prefix(), $year, $number);
    }

    /**
     * Užrakina serijos metų skaitiklį. Pirmą kartą metuose jį sukuria – pradeda nuo didžiausio jau esančio tų metų
     * numerio (seed'ų numeriai naudoja mokėjimo ID, todėl skaičiuoti nuo 1 negalima – numeriai susidurtų).
     */
    private function lockedLastNumber(InvoiceSeries $series, int $year): int
    {
        $row = $this->lockRow($series, $year);

        if ($row === null) {
            // insertOrIgnore: jei kita transakcija ką tik įterpė tą pačią eilutę, klaidos nebus – tiesiog ją užrakinsim
            DB::table('invoice_sequences')->insertOrIgnore([
                'series' => $series->value,
                'year' => $year,
                'last_number' => $this->maxExistingNumber($series, $year),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = $this->lockRow($series, $year);
        }

        return (int) ($row->last_number ?? 0);
    }

    private function lockRow(InvoiceSeries $series, int $year): ?object
    {
        return DB::table('invoice_sequences')
            ->where('series', $series->value)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Didžiausias tų metų numeris toje lentelėje, kurioje serijos numeriai saugomi. Rikiuojam tekstu – tai
     * teisinga, kol numeris turi vienodai skaitmenų (6, t. y. iki 999 999 sąskaitų per metus).
     */
    private function maxExistingNumber(InvoiceSeries $series, int $year): int
    {
        [$table, $column] = match ($series) {
            InvoiceSeries::Invoice => ['payments', 'invoice_number'],
            InvoiceSeries::CreditNote => ['refunds', 'credit_note_number'],
        };

        $prefix = $series->prefix().'-'.$year.'-';

        $max = DB::table($table)
            ->where($column, 'like', $prefix.'%')
            ->orderByDesc($column)
            ->value($column);

        return is_string($max) ? (int) substr($max, strlen($prefix)) : 0;
    }
}
