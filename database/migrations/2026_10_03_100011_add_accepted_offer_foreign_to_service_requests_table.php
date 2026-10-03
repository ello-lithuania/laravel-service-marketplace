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
        // Žiedinė nuoroda: dabar offers lentelė jau yra, todėl FK galima pridėti
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreign('accepted_offer_id')->references('id')->on('offers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['accepted_offer_id']);
        });
    }
};
