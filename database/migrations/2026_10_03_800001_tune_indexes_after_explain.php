<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapas 8: indeksų korekcijos pagal EXPLAIN ANALYZE su pilnu MySQL seed'u (docs/PERFORMANCE.md).
 *
 * Senų migracijų nekeičiam (jos jau paleistos kitose DB) – keitimas visada daromas nauja migracija.
 * Pirma sukuriamas naujas indeksas, tik tada ištrinamas senas: taip lentelė nė akimirkos nelieka be indekso.
 * Veikia ir SQLite (dev, testai), ir MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            // Katalogas: WHERE status = 'active' AND deleted_at IS NULL ORDER BY rating_avg DESC, reviews_count DESC, id DESC.
            // Lygybės stulpeliai pirmi, rikiavimo – po jų; InnoDB gale prideda id, todėl filesort nebereikia.
            $table->index(['status', 'deleted_at', 'rating_avg', 'reviews_count'], 'provider_profiles_catalog_index');
            $table->dropIndex(['status', 'rating_avg']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            // Varpelis kiekviename puslapyje: COUNT(*) … AND read_at IS NULL – vien iš indekso
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_at_index');
            $table->dropIndex(['notifiable_type', 'notifiable_id']);
        });

        Schema::table('service_requests', function (Blueprint $table) {
            // Kas valandą: status = 'open' AND expires_at <= now() (ExpireServiceRequests, chunkById)
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->dropIndex('notifications_notifiable_read_at_index');
        });

        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->index(['status', 'rating_avg']);
            $table->dropIndex('provider_profiles_catalog_index');
        });
    }
};
