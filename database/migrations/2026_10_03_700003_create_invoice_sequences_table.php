<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etapas 7: sąskaitų faktūrų numerių skaitiklis kiekvieniems metams (docs/DB_SCHEMA.md → invoice_sequences).
     * Eilutė užrakinama (lockForUpdate), todėl du vienu metu apmokami mokėjimai negauna to paties numerio.
     */
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
