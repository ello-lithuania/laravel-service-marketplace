<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etapas 7: iki kada prenumeratos kreditai jau suteikti (docs/DB_SCHEMA.md → subscriptions).
     * Pagal šį lauką kreditų suteikimas idempotentiškas – tam pačiam laikotarpiui kreditai neduodami du kartus.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('credits_granted_until')->nullable()->after('auto_renew');
        });

        // Jau esančioms prenumeratoms (seniau sukurtoms DB) kreditai buvo suteikti už visus apmokėtus laikotarpius
        DB::table('subscriptions')->update(['credits_granted_until' => DB::raw('ends_at')]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('credits_granted_until');
        });
    }
};
