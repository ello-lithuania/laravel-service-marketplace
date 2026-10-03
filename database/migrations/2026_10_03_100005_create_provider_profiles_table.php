<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Teikėjo vieši ir verslo duomenys, 1:1 su users (docs/DB_SCHEMA.md → provider_profiles)
        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('display_name', 150);
            $table->string('slug', 170)->unique();
            $table->string('headline', 160)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('company_code', 20)->nullable();
            $table->string('vat_code', 20)->nullable();
            $table->string('website')->nullable();
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->boolean('serves_whole_country')->default(false);
            $table->string('status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();
            // Denormalizuoti laukai (docs/DB_SCHEMA.md 2.10); unsigned – saugiklis nuo neigiamo balanso
            $table->unsignedInteger('credits_balance')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('completed_jobs_count')->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // „Geriausiai įvertinti aktyvūs teikėjai" be papildomo rūšiavimo
            $table->index(['status', 'rating_avg']);

            // FULLTEXT paieška – tik MySQL (SQLite jo nepalaiko)
            if (DB::getDriverName() === 'mysql') {
                $table->fullText(['display_name', 'headline', 'description']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_profiles');
    }
};
