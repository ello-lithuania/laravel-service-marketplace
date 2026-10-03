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
        // Schema: docs/DB_SCHEMA.md → users. city_id pridedamas atskira migracija,
        // kai jau bus cities lentelė (FK gali rodyti tik į esančią lentelę).
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Migracijoje – tekstas, ne UserRole enum: migracija neturi priklausyti nuo besikeičiančio kodo
            $table->string('role', 20)->default('client');
            $table->string('first_name', 60);
            $table->string('last_name', 80);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->json('notification_settings')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->string('ban_reason')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Admin sąrašai „naujausi teikėjai": role viena per mažai selektyvi, kartu su rikiavimu – naudinga
            $table->index(['role', 'created_at']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
