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
        // 10 apskričių (docs/DB_SCHEMA.md → regions). Žinyninė lentelė – be timestamps.
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
