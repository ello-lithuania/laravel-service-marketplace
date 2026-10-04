<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Svetainės dizaino nuotraukos (docs/DB_SCHEMA.md → site_photos). Failas – medialibrary kolekcija „photo".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_photos', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('alt', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_photos');
    }
};
