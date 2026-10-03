<?php

namespace App\Services\Invoices;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Sąskaitų faktūrų numeriai: SF-2026-000123 – ištisinė numeracija kiekvienų metų viduje (docs/DB_SCHEMA.md
 * → invoice_sequences).
 *
 * Kodėl ne MAX(invoice_number) + 1: du vienu metu apmokami mokėjimai perskaitytų tą patį MAX ir gautų tą patį
 * numerį. Skaitiklio eilutė užrakinama (SELECT … FOR UPDATE), todėl antras laukia, kol pirmas baigs transakciją.
 * Kviečiama TOS PAČIOS transakcijos viduje, kurioje mokėjimas tampa „paid": jei transakcija atšaukiama,
 * atšaukiamas ir skaitiklio padidinimas – numeracijoje neatsiranda tarpų.
 */
class InvoiceNumberGenerator
{
    public function next(CarbonInterface $paidAt): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Sąskaitos numeris išduodamas tik DB transakcijos viduje (kartu su mokėjimo būsena).');
        }

        $year = (int) $paidAt->copy()->setTimezone((string) config('invoices.timezone'))->format('Y');

        $last = $this->lockedLastNumber($year);
        $number = $last + 1;

        DB::table('invoice_sequences')->where('year', $year)->update(['last_number' => $number, 'updated_at' => now()]);

        return $this->format($year, $number);
    }

    public function format(int $year, int $number): string
    {
        return sprintf('%s-%d-%06d', config('invoices.prefix'), $year, $number);
    }

    /**
     * Užrakina metų skaitiklį. Pirmą kartą metuose jį sukuria – pradeda nuo didžiausio jau esančio tų metų numerio
     * (seed'ų numeriai naudoja mokėjimo ID, todėl skaičiuoti nuo 1 negalima – numeriai susidurtų).
     */
    private function lockedLastNumber(int $year): int
    {
        $row = DB::table('invoice_sequences')->where('year', $year)->lockForUpdate()->first();

        if ($row === null) {
            // insertOrIgnore: jei kita transakcija ką tik įterpė tą pačią eilutę, klaidos nebus – tiesiog ją užrakinsim
            DB::table('invoice_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => $this->maxExistingNumber($year),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('invoice_sequences')->where('year', $year)->lockForUpdate()->first();
        }

        return (int) ($row->last_number ?? 0);
    }

    /**
     * Didžiausias tų metų numeris payments lentelėje. Rikiuojam tekstu – tai teisinga, kol numeris turi
     * vienodai skaitmenų (6, t. y. iki 999 999 sąskaitų per metus).
     */
    private function maxExistingNumber(int $year): int
    {
        $prefix = config('invoices.prefix').'-'.$year.'-';

        $max = DB::table('payments')
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        return is_string($max) ? (int) substr($max, strlen($prefix)) : 0;
    }
}
