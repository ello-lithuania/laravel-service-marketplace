<?php

use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use App\Services\Site\SiteHighlights;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Etapas 10: pradžios puslapio skaičiai ir atsiliepimai (SiteHighlights, cache 1 val.).
 */

$longComment = 'Atvyko laiku, viską paaiškino ir po darbo sutvarkė. Labai rekomenduoju!';

test('skaičiai – tik aktyvūs teikėjai, vidurkis svertinis pagal atsiliepimų skaičių', function () {
    ProviderProfile::factory()->create(['rating_avg' => 4.0, 'reviews_count' => 10, 'completed_jobs_count' => 3]);
    ProviderProfile::factory()->create(['rating_avg' => 5.0, 'reviews_count' => 30, 'completed_jobs_count' => 7]);
    ProviderProfile::factory()->pending()->create(['rating_avg' => 1.0, 'reviews_count' => 100, 'completed_jobs_count' => 50]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.providers', 2)
            ->where('stats.reviews', 40)
            // (4 × 10 + 5 × 30) / 40 = 4,75 → 4,8
            ->where('stats.rating_avg', 4.8)
            ->where('stats.completed_jobs', 10));
});

test('tuščioje platformoje vidurkis null, o ne dalyba iš nulio', function () {
    expect(SiteHighlights::loadStats())->toBe([
        'providers' => 0,
        'reviews' => 0,
        'rating_avg' => null,
        'completed_jobs' => 0,
    ]);
});

test('atsiliepimai – tik paskelbti 5★ su išsamiu komentaru apie aktyvius teikėjus', function () use ($longComment) {
    $author = User::factory()->create(['first_name' => 'Jonas', 'last_name' => 'Petraitis']);
    $shown = Review::factory()->verified()->create(['rating' => 5, 'comment' => $longComment, 'author_id' => $author->id]);

    Review::factory()->create(['rating' => 4, 'comment' => $longComment]);
    Review::factory()->create(['rating' => 5, 'comment' => 'Viskas gerai.']);
    Review::factory()->hidden()->create(['rating' => 5, 'comment' => $longComment]);
    Review::factory()->create([
        'rating' => 5,
        'comment' => $longComment,
        'provider_profile_id' => ProviderProfile::factory()->suspended(),
    ]);

    $shown->load('serviceRequest.category', 'serviceRequest.city', 'providerProfile');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('testimonials', 1, fn (Assert $testimonial) => $testimonial
                ->where('id', $shown->id)
                ->where('rating', 5)
                ->where('comment', $longComment)
                ->where('author_name', 'Jonas P.')
                ->where('provider.slug', $shown->providerProfile->slug)
                ->where('category', $shown->serviceRequest?->category->name)
                ->where('city', $shown->serviceRequest?->city->name)
                ->etc()));
});

test('rodomi ne daugiau kaip 6 naujausi atsiliepimai', function () use ($longComment) {
    $reviews = Review::factory()->count(8)->create(['rating' => 5, 'comment' => $longComment]);

    $ids = array_column(SiteHighlights::loadTestimonials(), 'id');

    expect($ids)->toBe($reviews->sortByDesc('id')->take(6)->pluck('id')->values()->all());
});

test('atsiliepimas pagal pakvietimą (be užklausos) neturi kategorijos ir miesto', function () use ($longComment) {
    Review::factory()->create(['rating' => 5, 'comment' => $longComment]);

    expect(SiteHighlights::loadTestimonials()[0])
        ->category->toBeNull()
        ->city->toBeNull();
});

test('skaičiai ir atsiliepimai laikomi cache valandą', function () use ($longComment) {
    ProviderProfile::factory()->create(['reviews_count' => 5]);
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.providers', 1)
        ->has('testimonials', 0));

    ProviderProfile::factory()->create();
    Review::factory()->create(['rating' => 5, 'comment' => $longComment]);

    // Per valandą – senas cache
    $this->travel(30)->minutes();
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.providers', 1)
        ->has('testimonials', 0));

    // Po valandos – nauji duomenys (Review::factory sukūrė dar vieną teikėją)
    $this->travel(31)->minutes();
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.providers', 3)
        ->has('testimonials', 1));
});
