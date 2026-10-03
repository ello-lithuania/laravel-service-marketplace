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
        // Klientų įvertinimai teikėjams.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            // Yra – patvirtintas atsiliepimas; NULL – pagal pakvietimą. UNIQUE leidžia daug NULL.
            $table->foreignId('service_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->text('provider_reply')->nullable();
            $table->timestamp('provider_replied_at')->nullable();
            $table->string('status', 20)->default('published');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Profilio atsiliepimų sąrašas ir AVG(rating) perskaičiavimas
            $table->index(['provider_profile_id', 'status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
