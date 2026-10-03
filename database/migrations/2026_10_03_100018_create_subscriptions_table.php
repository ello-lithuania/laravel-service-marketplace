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
        // Teikėjų prenumeratos.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->timestamp('starts_at');
            // Dabartinio laikotarpio pabaiga (pratęsiant pastumiama)
            $table->timestamp('ends_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();

            // „Ar teikėjas turi aktyvią prenumeratą?"
            $table->index(['provider_profile_id', 'status']);
            // Kasdienis job'as: pratęsti arba užbaigti
            $table->index(['status', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
