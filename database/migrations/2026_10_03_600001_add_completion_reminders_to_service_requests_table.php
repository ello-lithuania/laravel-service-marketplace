<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etapas 6 (docs/STATES.md 1 sk. „Papildomos taisyklės"):
     *  - completion_requested_at – teikėjas paprašė klientą pažymėti darbą atliktu (kartoti galima tik po 3 d.);
     *  - completion_reminded_at – sistema priminė klientui, kad užklausa vykdoma jau 60 d. (tik vieną kartą).
     * Laikom DB, o ne cache: taisyklė turi išlikti išvalius cache, o UI rodo, kada paprašyta.
     */
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->timestamp('completion_requested_at')->nullable()->after('completed_at');
            $table->timestamp('completion_reminded_at')->nullable()->after('completion_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn(['completion_requested_at', 'completion_reminded_at']);
        });
    }
};
