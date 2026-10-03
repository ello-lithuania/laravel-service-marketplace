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
        // users migracija vykdoma pirma, kai cities dar nėra – todėl city_id pridedam tik dabar
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('phone')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
