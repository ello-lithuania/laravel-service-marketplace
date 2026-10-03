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
        // Kokias paslaugas teikia teikėjas ir už kiek „nuo" (pivot; vardas – abu modeliai abėcėlės tvarka).
        Schema::create('category_provider_profile', function (Blueprint $table) {
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_from_cents')->nullable();
            $table->string('price_unit', 20)->nullable();

            // PK: teikėjas tą pačią kategoriją gali turėti tik kartą + greitos „teikėjo kategorijos"
            $table->primary(['provider_profile_id', 'category_id']);
            // Atvirkštinė kryptis „visi teikėjai kategorijoje" – svarbiausias atitikimo indeksas
            $table->index(['category_id', 'provider_profile_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_provider_profile');
    }
};
