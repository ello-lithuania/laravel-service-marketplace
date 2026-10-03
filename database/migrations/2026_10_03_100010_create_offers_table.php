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
        // Teikėjo pasiūlymas užklausai.
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_profile_id')->constrained()->restrictOnDelete();
            $table->text('message');
            $table->unsignedInteger('price_cents')->nullable();
            $table->string('price_type', 20);
            $table->string('duration_text', 100)->nullable();
            $table->date('start_date')->nullable();
            $table->string('status', 20)->default('pending');
            // Kiek kainavo siuntimo momentu (kategorijos kaina vėliau gali keistis)
            $table->unsignedSmallInteger('credits_spent')->default(0);
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // Vienas teikėjas – vienas pasiūlymas užklausai; tas pats indeksas – „užklausos pasiūlymai"
            $table->unique(['service_request_id', 'provider_profile_id']);
            // „Mano pasiūlymai", naujausi viršuje
            $table->index(['provider_profile_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
