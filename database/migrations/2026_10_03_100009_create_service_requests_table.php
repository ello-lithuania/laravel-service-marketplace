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
        // Kliento užklausa (darbas), į kurią teikėjai siunčia pasiūlymus.
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('slug', 180)->unique();
            $table->string('title', 150);
            $table->text('description');
            $table->string('address')->nullable();
            $table->unsignedInteger('budget_min_cents')->nullable();
            $table->unsignedInteger('budget_max_cents')->nullable();
            $table->string('start_preference', 20);
            $table->date('start_date')->nullable();
            $table->string('status', 20)->default('pending');
            // FK pridedamas atskira migracija po offers lentelės – žiedinė nuoroda (docs/DB_SCHEMA.md → service_requests)
            $table->unsignedBigInteger('accepted_offer_id')->nullable();
            $table->unsignedSmallInteger('offers_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Teikėjo srautas: category_id IN (…) AND status = 'open' ORDER BY published_at
            $table->index(['category_id', 'status', 'published_at']);
            $table->index(['city_id', 'status', 'published_at']);
            // Viešas „naujausios užklausos" sąrašas ir pasibaigusių tikrinimas
            $table->index(['status', 'published_at']);
            // „Mano užklausos"
            $table->index(['client_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
