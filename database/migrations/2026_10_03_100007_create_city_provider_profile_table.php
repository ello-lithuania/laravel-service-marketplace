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
        // Teikėjo aptarnavimo zonos. Jei serves_whole_country = true, eilučių nekuriam.
        Schema::create('city_provider_profile', function (Blueprint $table) {
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();

            $table->primary(['provider_profile_id', 'city_id']);
            $table->index(['city_id', 'provider_profile_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('city_provider_profile');
    }
};
