<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etapas 9: mokėjimų grąžinimai ir kreditinės sąskaitos (docs/DB_SCHEMA.md → refunds).
     * UNIQUE(payment_id) – paskutinis saugiklis nuo dvigubo grąžinimo, jei kodo patikros kažkaip būtų apeitos.
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('refunded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('reason', 500);
            $table->unsignedInteger('credits_reversed')->default(0);
            $table->unsignedInteger('credits_shortfall')->default(0);
            $table->string('credit_note_number', 30)->unique();
            $table->json('billing_details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
