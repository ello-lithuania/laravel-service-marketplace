<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etapas 9: numeracijos serijos (docs/DB_SCHEMA.md → invoice_sequences). Iki šiol skaitiklis buvo vienas
     * kiekvieniems metams (PK = year); kreditinės sąskaitos turi savo ištisinę numeraciją (KS-2026-000001),
     * todėl raktas tampa (series, year). Esamos eilutės – sąskaitų faktūrų serija „invoice".
     *
     * Pirminio rakto keitimas: MySQL – ALTER TABLE … DROP PRIMARY KEY, ADD PRIMARY KEY; SQLite pirminio rakto
     * keisti nemoka, todėl Laravel (11+) pats perkuria lentelę su duomenimis. Kodas vienodas abiem.
     * https://laravel.com/docs/13.x/migrations#dropping-indexes
     */
    public function up(): void
    {
        Schema::table('invoice_sequences', function (Blueprint $table) {
            // Default užpildo esamas eilutes; naujas eilutes generatorius visada įrašo su serija
            $table->string('series', 20)->default('invoice');
        });

        Schema::table('invoice_sequences', function (Blueprint $table) {
            $table->dropPrimary();
            // SQLite vienintelį INTEGER PRIMARY KEY stulpelį laiko „rowid" (automatiškai didėjančiu), ir Laravel,
            // perkurdamas lentelę, tą požymį išsaugotų, o sudėtinį raktą praleistų. change() aprašo stulpelį iš naujo.
            $table->unsignedSmallInteger('year')->change();
            $table->primary(['series', 'year']);
        });
    }

    /**
     * Atgal – tik sąskaitų faktūrų skaitikliai (kitų serijų eilutės be series stulpelio nebeturėtų prasmės).
     */
    public function down(): void
    {
        DB::table('invoice_sequences')->where('series', '!=', 'invoice')->delete();

        Schema::table('invoice_sequences', function (Blueprint $table) {
            $table->dropPrimary();
            $table->unsignedSmallInteger('year')->change();
            $table->primary('year');
        });

        Schema::table('invoice_sequences', function (Blueprint $table) {
            $table->dropColumn('series');
        });
    }
};
