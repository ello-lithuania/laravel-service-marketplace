<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Perkami kreditų paketai.
        Schema::create('credit_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedInteger('credits');
            $table->unsignedInteger('bonus_credits')->default(0);
            // Pinigai – sveikais centais (docs/DB_SCHEMA.md 2.7)
            $table->unsignedInteger('price_cents');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_packages');
    }
};
