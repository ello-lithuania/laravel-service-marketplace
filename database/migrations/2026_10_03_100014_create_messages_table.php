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
        // Pokalbio žinutės.
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            // InnoDB indeksas (conversation_id) savyje turi ir PK, todėl ORDER BY id pokalbio viduje – nemokamai
            $table->foreignId('conversation_id')->index()->constrained()->cascadeOnDelete();
            // NULL – sisteminė žinutė („Pasiūlymas priimtas")
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
