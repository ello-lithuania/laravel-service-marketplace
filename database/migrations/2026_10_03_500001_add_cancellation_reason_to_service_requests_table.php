<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Atšaukimo / atmetimo priežastis (Etapas 5, docs/STATES.md 1 sk.):
     * admin atmeta užklausą su priežastimi, klientas atšaukia vykdomą užklausą su priežastimi.
     * Esamos migracijos nekeičiam – schema keičiama nauja migracija, kad ją gautų ir jau veikiančios DB.
     */
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn('cancellation_reason');
        });
    }
};
