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
        // Pokalbis tarp kliento ir teikėjo dėl pasiūlymo. offer_id = NULL – ateityje palaikymo pokalbiams.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            // Aiškus indeksas – MySQL jį sukurtų ir pats (FK), SQLite – ne
            $table->foreignId('service_request_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
