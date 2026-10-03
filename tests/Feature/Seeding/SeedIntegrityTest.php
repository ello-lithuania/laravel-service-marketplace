<?php

use App\Models\ProviderProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
 * Demo duomenų vientisumas (docs/SEEDING.md 8 sk.). Seed'inama mažu masteliu (SEED_SCALE=0.01),
 * bet taisyklės tos pačios kaip pilname seed'e.
 */

beforeEach(function () {
    config(['seeding.demo' => true, 'seeding.scale' => 0.01]);
    $this->seed(DatabaseSeeder::class);
});

/**
 * Kiek eilučių grąžina „pažeidimų" užklausa (turi būti 0).
 */
function violations(string $sql): int
{
    return (int) DB::selectOne("SELECT COUNT(*) AS aggregate FROM ({$sql}) AS violations")->aggregate;
}

test('pagrindinių lentelių kiekiai = tiksliniai × SEED_SCALE', function () {
    expect(DB::table('users')->where('role', 'client')->count())->toBe(600)
        ->and(DB::table('users')->where('role', 'provider')->count())->toBe(200)
        ->and(DB::table('provider_profiles')->count())->toBe(200)
        ->and(DB::table('service_requests')->count())->toBe(1_000)
        ->and(DB::table('offers')->count())->toBe(3_000)
        ->and(DB::table('reviews')->count())->toBe(1_000)
        ->and(DB::table('messages')->count())->toBe(2_000);
});

test('pasiūlymą siunčia tik tinkamas teikėjas: aktyvus, kategorija (su tėvais) ir zona', function () {
    expect(violations(<<<'SQL'
        SELECT offers.id FROM offers
        JOIN service_requests sr ON sr.id = offers.service_request_id
        JOIN provider_profiles pp ON pp.id = offers.provider_profile_id
        JOIN categories leaf ON leaf.id = sr.category_id
        JOIN categories grp ON grp.id = leaf.parent_id
        WHERE pp.status <> 'active'
            OR NOT EXISTS (
                SELECT 1 FROM category_provider_profile cpp
                WHERE cpp.provider_profile_id = pp.id AND cpp.category_id IN (leaf.id, grp.id, grp.parent_id)
            )
            OR (pp.serves_whole_country = 0 AND NOT EXISTS (
                SELECT 1 FROM city_provider_profile cp WHERE cp.provider_profile_id = pp.id AND cp.city_id = sr.city_id
            ))
        SQL))->toBe(0);
});

test('accepted_offer_id: tik in_progress/completed, pasiūlymas accepted ir tos pačios užklausos', function () {
    expect(violations(<<<'SQL'
        SELECT sr.id FROM service_requests sr
        LEFT JOIN offers o ON o.id = sr.accepted_offer_id
        WHERE (sr.status IN ('in_progress', 'completed') AND (o.id IS NULL OR o.status <> 'accepted' OR o.service_request_id <> sr.id))
            OR (sr.status NOT IN ('in_progress', 'completed') AND sr.accepted_offer_id IS NOT NULL)
        SQL))->toBe(0)
        ->and(violations("SELECT service_request_id FROM offers WHERE status = 'accepted' GROUP BY service_request_id HAVING COUNT(*) > 1"))->toBe(0);
});

test('užklausų datos dera su statusais', function () {
    $now = now()->toDateTimeString();

    expect(DB::table('service_requests')->where('status', 'open')->where('expires_at', '<=', $now)->count())->toBe(0)
        ->and(DB::table('service_requests')->where('status', 'pending')->whereNotNull('published_at')->count())->toBe(0)
        ->and(DB::table('service_requests')->where('status', 'expired')->where('expires_at', '>', $now)->count())->toBe(0)
        ->and(DB::table('service_requests')->where('status', 'completed')->whereNull('completed_at')->count())->toBe(0)
        ->and(DB::table('service_requests')->where('status', 'cancelled')->whereColumn('cancelled_at', '<', 'published_at')->count())->toBe(0)
        ->and(violations('SELECT o.id FROM offers o JOIN service_requests sr ON sr.id = o.service_request_id WHERE o.created_at < sr.published_at'))->toBe(0)
        ->and(violations('SELECT sr.id FROM service_requests sr JOIN users u ON u.id = sr.client_id WHERE u.created_at > sr.created_at'))->toBe(0)
        ->and(violations('SELECT o.id FROM offers o JOIN provider_profiles pp ON pp.id = o.provider_profile_id WHERE pp.created_at > o.created_at'))->toBe(0);
});

test('patvirtintas atsiliepimas: atlikta užklausa, jos klientas, priimto pasiūlymo teikėjas, po completed_at', function () {
    expect(DB::table('reviews')->whereNotNull('service_request_id')->count())->toBeGreaterThan(0)
        ->and(violations(<<<'SQL'
            SELECT r.id FROM reviews r
            JOIN service_requests sr ON sr.id = r.service_request_id
            JOIN offers o ON o.id = sr.accepted_offer_id
            WHERE sr.status <> 'completed' OR r.author_id <> sr.client_id
                OR r.provider_profile_id <> o.provider_profile_id OR r.created_at < sr.completed_at
            SQL))->toBe(0);
});

test('kreditai: balansas = ledger suma, balance_after niekada neigiamas ir sutampa su eiga', function () {
    expect(violations(<<<'SQL'
        SELECT pp.id FROM provider_profiles pp
        WHERE pp.credits_balance <> COALESCE((SELECT SUM(amount) FROM credit_transactions ct WHERE ct.provider_profile_id = pp.id), 0)
        SQL))->toBe(0)
        ->and(DB::table('credit_transactions')->where('balance_after', '<', 0)->count())->toBe(0)
        // Paskutinis balance_after = balansas
        ->and(violations(<<<'SQL'
            SELECT pp.id FROM provider_profiles pp
            WHERE pp.credits_balance <> (SELECT ct.balance_after FROM credit_transactions ct WHERE ct.provider_profile_id = pp.id ORDER BY ct.id DESC LIMIT 1)
            SQL))->toBe(0)
        // Kiekvienas pasiūlymas turi lygiai vieną nurašymą, lygų credits_spent
        ->and(violations(<<<'SQL'
            SELECT o.id FROM offers o
            LEFT JOIN credit_transactions ct ON ct.source_type = 'offer' AND ct.source_id = o.id AND ct.type = 'offer'
            GROUP BY o.id, o.credits_spent HAVING COUNT(ct.id) <> 1 OR SUM(-ct.amount) <> o.credits_spent
            SQL))->toBe(0);
});

test('kreditų grąžinimai tik pagal docs/STATES.md 3 sk. ir ne daugiau kaip vienas pasiūlymui', function () {
    expect(DB::table('credit_transactions')->where('type', 'refund')->count())->toBeGreaterThan(0)
        ->and(violations(<<<'SQL'
            SELECT ct.id FROM credit_transactions ct
            JOIN offers o ON o.id = ct.source_id
            JOIN service_requests sr ON sr.id = o.service_request_id
            WHERE ct.type = 'refund' AND ct.source_type = 'offer'
                AND NOT (
                    o.status = 'declined' AND o.responded_at IS NULL
                    AND (sr.status = 'cancelled' OR (sr.status = 'expired' AND o.viewed_at IS NULL))
                )
            SQL))->toBe(0)
        ->and(violations("SELECT source_id FROM credit_transactions WHERE type = 'refund' GROUP BY source_id HAVING COUNT(*) > 1"))->toBe(0);
});

test('denormalizuoti skaitliukai sutampa su perskaičiuotais per Eloquent', function () {
    $profiles = ProviderProfile::query()
        ->withCount(['reviews as published_reviews' => fn ($q) => $q->where('status', 'published')])
        ->withAvg(['reviews as published_avg' => fn ($q) => $q->where('status', 'published')], 'rating')
        ->get();

    foreach ($profiles as $profile) {
        expect($profile->reviews_count)->toBe($profile->published_reviews)
            ->and((float) $profile->rating_avg)->toEqualWithDelta((float) ($profile->published_avg ?? 0), 0.006);
    }

    expect(violations(<<<'SQL'
        SELECT sr.id FROM service_requests sr
        WHERE sr.offers_count <> (SELECT COUNT(*) FROM offers o WHERE o.service_request_id = sr.id AND o.status <> 'withdrawn')
        SQL))->toBe(0)
        ->and(violations(<<<'SQL'
            SELECT pp.id FROM provider_profiles pp
            WHERE pp.completed_jobs_count <> (
                SELECT COUNT(*) FROM service_requests sr JOIN offers o ON o.id = sr.accepted_offer_id
                WHERE sr.status = 'completed' AND o.provider_profile_id = pp.id
            )
            SQL))->toBe(0)
        ->and(violations('SELECT c.id FROM conversations c WHERE c.last_message_at <> (SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.id)'))->toBe(0);
});

test('žinutės: siuntėjas – pokalbio dalyvis, laikas didėja, pokalbyje du dalyviai', function () {
    expect(violations(<<<'SQL'
        SELECT m.id FROM messages m
        WHERE NOT EXISTS (SELECT 1 FROM conversation_user cu WHERE cu.conversation_id = m.conversation_id AND cu.user_id = m.sender_id)
        SQL))->toBe(0)
        ->and(violations(<<<'SQL'
            SELECT m.id FROM messages m JOIN messages prev ON prev.conversation_id = m.conversation_id AND prev.id < m.id
            WHERE prev.created_at > m.created_at
            SQL))->toBe(0)
        ->and(violations('SELECT conversation_id FROM conversation_user GROUP BY conversation_id HAVING COUNT(*) <> 2'))->toBe(0);
});

test('jokių tikrų kontaktų: el. paštai @example.test, telefonai +3700, įmonių kodai 999', function () {
    expect(DB::table('users')->where('email', 'not like', '%@example.test')->count())->toBe(0)
        ->and(DB::table('users')->whereNotNull('phone')->where('phone', 'not like', '+3700%')->count())->toBe(0)
        ->and(DB::table('provider_profiles')->whereNotNull('company_code')->where('company_code', 'not like', '999%')->count())->toBe(0)
        ->and(DB::table('provider_profiles')->whereNotNull('website')->where('website', 'not like', '%.example.test')->count())->toBe(0);
});
