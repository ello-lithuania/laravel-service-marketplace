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
        // Mokėjimai už kreditų paketus ir prenumeratas.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Viešas užsakymo numeris (siunčiamas mokėjimų tiekėjui)
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 20);
            $table->string('gateway_reference', 100)->nullable();
            $table->nullableMorphs('purchasable');
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('EUR');
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('invoice_number', 30)->nullable()->unique();
            $table->json('meta')->nullable();
            $table->timestamps();

            // Idempotencija: pakartotinis tiekėjo callback'as negali antrą kartą užskaityti kreditų
            $table->unique(['gateway', 'gateway_reference']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
