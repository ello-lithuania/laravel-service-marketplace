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
        // Kreditų „didžioji knyga" (ledger): kiekvienas pokytis – nauja nekeičiama eilutė (docs/DB_SCHEMA.md 2.8).
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            // Istorija chronologiškai: (provider_profile_id) + PK
            $table->foreignId('provider_profile_id')->index()->constrained()->restrictOnDelete();
            // + gauta / − išleista
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            $table->string('type', 30);
            // Payment / Offer / Subscription; sukuria ir indeksą (source_type, source_id)
            $table->nullableMorphs('source');
            $table->string('description')->nullable();
            // Įrašai nekeičiami – updated_at nėra
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
