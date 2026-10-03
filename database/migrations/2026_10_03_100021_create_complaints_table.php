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
        // Skundai dėl turinio ar vartotojų.
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            // ServiceRequest, Offer, Review, Message, ProviderProfile, User; sukuria indeksą (reportable_type, reportable_id)
            $table->morphs('reportable');
            $table->string('reason', 30);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('handled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Admin eilė, seniausi viršuje
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
