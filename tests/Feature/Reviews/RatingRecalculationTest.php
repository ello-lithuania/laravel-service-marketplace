<?php

use App\Enums\ReviewStatus;
use App\Jobs\RecalculateProviderRating;
use App\Models\ProviderProfile;
use App\Models\Review;
use Database\Seeders\Demo\CounterSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Reitingo perskaičiavimas: ReviewObserver → RecalculateProviderRating (Etapas 6).
 * Testuose QUEUE_CONNECTION=sync, todėl job'as įvykdomas iš karto.
 */
beforeEach(function () {
    $this->provider = ProviderProfile::factory()->create();
});

function ratingOf(ProviderProfile $provider): array
{
    $fresh = $provider->fresh();

    return [(float) $fresh->rating_avg, $fresh->reviews_count];
}

test('paskelbtas atsiliepimas iš karto perskaičiuoja reitingą (tik published)', function () {
    Review::factory()->for($this->provider)->create(['rating' => 5]);
    Review::factory()->for($this->provider)->create(['rating' => 4]);
    Review::factory()->for($this->provider)->create(['rating' => 2]);
    Review::factory()->for($this->provider)->hidden()->create(['rating' => 1]);
    Review::factory()->for($this->provider)->create(['rating' => 1, 'status' => ReviewStatus::Pending, 'published_at' => null]);

    // (5 + 4 + 2) / 3 = 3,666… → 3,67
    expect(ratingOf($this->provider))->toBe([3.67, 3]);
});

test('paslėpus, paskelbus ar ištrynus – perskaičiuojama', function () {
    $five = Review::factory()->for($this->provider)->create(['rating' => 5]);
    $one = Review::factory()->for($this->provider)->create(['rating' => 1]);
    expect(ratingOf($this->provider))->toBe([3.0, 2]);

    $one->forceFill(['status' => ReviewStatus::Hidden])->save();
    expect(ratingOf($this->provider))->toBe([5.0, 1]);

    $one->forceFill(['status' => ReviewStatus::Published])->save();
    expect(ratingOf($this->provider))->toBe([3.0, 2]);

    $five->delete();
    $one->delete();
    expect(ratingOf($this->provider))->toBe([0.0, 0]);
});

test('formulė sutampa su seed\'ų CounterSync', function () {
    foreach ([5, 5, 4, 3, 5, 1, 4] as $rating) {
        Review::factory()->for($this->provider)->create(['rating' => $rating]);
    }
    Review::factory()->for($this->provider)->hidden()->create(['rating' => 1]);
    $afterObserver = ratingOf($this->provider);

    // Sugadinam skaitliukus ir perskaičiuojam seed'ų būdu
    DB::table('provider_profiles')->where('id', $this->provider->id)->update(['rating_avg' => 0, 'reviews_count' => 0]);
    CounterSync::run();

    expect(ratingOf($this->provider))->toBe($afterObserver)->toBe([3.86, 7]);
});

test('job\'as įdedamas į eilę tik kai keičiasi reitingą lemiantys laukai', function () {
    Queue::fake();
    $review = Review::factory()->for($this->provider)->create(['status' => ReviewStatus::Pending, 'published_at' => null]);

    // Laukiantis – reitingo nekeičia
    Queue::assertNotPushed(RecalculateProviderRating::class);

    $review->forceFill(['provider_reply' => 'Ačiū!', 'provider_replied_at' => now()])->save();
    Queue::assertNotPushed(RecalculateProviderRating::class);

    $review->forceFill(['status' => ReviewStatus::Published, 'published_at' => now()])->save();
    Queue::assertPushed(RecalculateProviderRating::class, fn (RecalculateProviderRating $job) => $job->providerProfileId === $this->provider->id);
});

test('perkėlus atsiliepimą kitam teikėjui – perskaičiuojami abu', function () {
    $review = Review::factory()->for($this->provider)->create(['rating' => 5]);
    $other = ProviderProfile::factory()->create();

    $review->forceFill(['provider_profile_id' => $other->id])->save();

    expect(ratingOf($this->provider))->toBe([0.0, 0])
        ->and(ratingOf($other))->toBe([5.0, 1]);
});

test('transakcijai nepavykus job\'as nepaleidžiamas (ShouldHandleEventsAfterCommit)', function () {
    Queue::fake();

    try {
        DB::transaction(function () {
            Review::factory()->for($this->provider)->create(['rating' => 5]);

            throw new RuntimeException('atšaukiam');
        });
    } catch (RuntimeException) {
        // tikėtasi
    }

    Queue::assertNotPushed(RecalculateProviderRating::class);
});

test('to paties teikėjo job\'as eilėje – tik vienas (ShouldBeUniqueUntilProcessing)', function () {
    Queue::fake();

    RecalculateProviderRating::dispatch($this->provider->id);
    RecalculateProviderRating::dispatch($this->provider->id);
    RecalculateProviderRating::dispatch(ProviderProfile::factory()->create()->id);

    Queue::assertPushed(RecalculateProviderRating::class, 2);
});
