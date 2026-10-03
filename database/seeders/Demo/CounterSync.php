<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Facades\DB;

/**
 * Perskaičiuoja denormalizuotus skaitliukus vienu UPDATE kiekvienai lentelei (docs/DB_SCHEMA.md 2.10).
 *
 * Koreliuotos subužklausos (UPDATE … SET x = (SELECT … WHERE … = lentelė.id)) veikia ir MySQL, ir SQLite.
 * Klasė vieša, nes tą patį darys ir admin komanda „perskaičiuoti skaitliukus", jei jie kada išsiderintų.
 */
final class CounterSync
{
    public static function run(): void
    {
        DB::transaction(function () {
            DB::update(<<<'SQL'
                UPDATE service_requests SET offers_count = (
                    SELECT COUNT(*) FROM offers
                    WHERE offers.service_request_id = service_requests.id AND offers.status <> 'withdrawn'
                )
                SQL);

            DB::update(<<<'SQL'
                UPDATE provider_profiles SET
                    reviews_count = (
                        SELECT COUNT(*) FROM reviews
                        WHERE reviews.provider_profile_id = provider_profiles.id AND reviews.status = 'published'
                    ),
                    rating_avg = COALESCE((
                        SELECT ROUND(AVG(reviews.rating), 2) FROM reviews
                        WHERE reviews.provider_profile_id = provider_profiles.id AND reviews.status = 'published'
                    ), 0),
                    completed_jobs_count = (
                        SELECT COUNT(*) FROM offers
                        JOIN service_requests ON service_requests.id = offers.service_request_id
                        WHERE offers.provider_profile_id = provider_profiles.id
                            AND offers.status = 'accepted' AND service_requests.status = 'completed'
                    ),
                    credits_balance = COALESCE((
                        SELECT SUM(credit_transactions.amount) FROM credit_transactions
                        WHERE credit_transactions.provider_profile_id = provider_profiles.id
                    ), 0)
                SQL);

            DB::update(<<<'SQL'
                UPDATE conversations SET last_message_at = (
                    SELECT MAX(messages.created_at) FROM messages
                    WHERE messages.conversation_id = conversations.id AND messages.deleted_at IS NULL
                )
                SQL);
        });
    }
}
