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
        // 3 lygių paslaugų medis: parent_id + depth (docs/DB_SCHEMA.md 2.2 ir → categories).
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->unsignedTinyInteger('depth');
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 60)->nullable();
            $table->unsignedTinyInteger('offer_cost_credits')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('meta_title', 160)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->timestamps();

            // „Duok vaikus nurodyta tvarka"; prasideda parent_id, todėl tinka ir FK reikmėms
            $table->index(['parent_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
