<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etapas 7 (docs/DB_SCHEMA.md → payments):
     * - subscription_id – kurios prenumeratos laikotarpis apmokamas (pratęsimo mokėjimui žinom, ką pratęsti);
     * - billing_details – sąskaitos faktūros rekvizitų „nuotrauka" apmokėjimo metu (vėliau profilio pakeitimai
     *   jau išrašytos sąskaitos nekeičia).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // restrict – finansinės istorijos netyčia neištrinsim (docs/DB_SCHEMA.md 2.12)
            $table->foreignId('subscription_id')->nullable()->after('purchasable_id')->constrained()->restrictOnDelete();
            $table->json('billing_details')->nullable()->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn('billing_details');
        });
    }
};
