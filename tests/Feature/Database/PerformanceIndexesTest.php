<?php

use Illuminate\Support\Facades\Schema;

/**
 * Etapo 8 indeksai (docs/PERFORMANCE.md) turi egzistuoti abiejose DB (SQLite testuose, MySQL CI). Jei kas nors
 * „sutvarkytų" migraciją ir indeksą prarastų, katalogas pilname seed'e vėl lėtėtų 4–10 kartų – tai pagautų šis testas.
 *
 * @return list<list<string>>
 */
function indexColumns(string $table): array
{
    return array_map(fn (array $index): array => $index['columns'], Schema::getIndexes($table));
}

test('katalogo indeksas: status, deleted_at, rating_avg, reviews_count (senas status+rating_avg pašalintas)', function () {
    expect(indexColumns('provider_profiles'))
        ->toContain(['status', 'deleted_at', 'rating_avg', 'reviews_count'])
        ->not->toContain(['status', 'rating_avg']);
});

test('varpelio indeksas su read_at pakeitė morphs indeksą', function () {
    expect(indexColumns('notifications'))
        ->toContain(['notifiable_type', 'notifiable_id', 'read_at'])
        ->not->toContain(['notifiable_type', 'notifiable_id']);
});

test('pasibaigusių užklausų indeksas ir srauto indeksai', function () {
    expect(indexColumns('service_requests'))
        ->toContain(['status', 'expires_at'])
        ->toContain(['category_id', 'status', 'published_at'])
        ->toContain(['city_id', 'status', 'published_at']);
});

test('atitikimo indeksai pivot lentelėse (atvirkštinė kryptis)', function () {
    expect(indexColumns('category_provider_profile'))->toContain(['category_id', 'provider_profile_id'])
        ->and(indexColumns('city_provider_profile'))->toContain(['city_id', 'provider_profile_id']);
});
