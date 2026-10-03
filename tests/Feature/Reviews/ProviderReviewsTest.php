<?php

use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewReplied;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Teikėjo „Atsiliepimai": sąrašas ir vienkartinis atsakymas.
 */
beforeEach(function () {
    Notification::fake();
    $this->provider = ProviderProfile::factory()->create();
    $this->author = User::factory()->create(['first_name' => 'Ona', 'last_name' => 'Petraitė']);
    $this->review = Review::factory()->for($this->provider)->create(['author_id' => $this->author->id, 'rating' => 4]);
});

test('sąraše – paskelbti ir laukiantys moderavimo, be paslėptų', function () {
    Review::factory()->for($this->provider)->create(['status' => 'pending', 'published_at' => null]);
    Review::factory()->for($this->provider)->hidden()->create();
    Review::factory()->create(); // kito teikėjo

    $this->actingAs($this->provider->user)->get(route('provider-reviews.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviews.data', 2)
            ->where('reviews.data.1.author_name', 'Ona P.')
            ->where('reviews.data.1.can.reply', true)
            ->where('reviews.data.0.status.value', 'pending')
            ->where('reviews.data.0.can.reply', false));
});

test('teikėjas atsako vieną kartą, autorius gauna pranešimą', function () {
    $this->actingAs($this->provider->user)
        ->post(route('provider-reviews.reply', $this->review), ['reply' => '  Ačiū, Ona!  '])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Atsakymas paskelbtas.');

    expect($this->review->refresh())
        ->provider_reply->toBe('Ačiū, Ona!')
        ->provider_replied_at->not->toBeNull();
    Notification::assertSentTo($this->author, ReviewReplied::class, fn (ReviewReplied $n) => $n->toMail($this->author)->actionUrl
        === route('providers.show', $this->provider).'#atsiliepimai');

    $this->actingAs($this->provider->user)
        ->post(route('provider-reviews.reply', $this->review), ['reply' => 'Antras atsakymas'])
        ->assertForbidden();
    expect($this->review->refresh()->provider_reply)->toBe('Ačiū, Ona!');
});

test('atsakyti į svetimą ar nepaskelbtą atsiliepimą negalima', function () {
    $other = ProviderProfile::factory()->create();
    $this->actingAs($other->user)
        ->post(route('provider-reviews.reply', $this->review), ['reply' => 'Ne mano'])
        ->assertForbidden();

    $pending = Review::factory()->for($this->provider)->create(['status' => 'pending', 'published_at' => null]);
    $this->actingAs($this->provider->user)
        ->post(route('provider-reviews.reply', $pending), ['reply' => 'Ačiū'])
        ->assertForbidden();
});

test('tuščias atsakymas atmetamas', function () {
    $this->actingAs($this->provider->user)
        ->post(route('provider-reviews.reply', $this->review), ['reply' => '   '])
        ->assertSessionHasErrors('reply');
});

test('klientui teikėjo atsiliepimų puslapis nepasiekiamas', function () {
    $this->actingAs($this->author)->get(route('provider-reviews.index'))->assertForbidden();
});

test('viešame profilyje pakvietimo atsiliepimas pažymimas (is_verified = false)', function () {
    $this->get(route('providers.show', $this->provider))
        ->assertInertia(fn (Assert $page) => $page->where('reviews.data.0.is_verified', false));
});

test('NewReview pranešimas veda į teikėjo atsiliepimų puslapį, ReviewReplied – į viešą profilį', function () {
    $user = $this->provider->user;
    $newReview = $user->notifications()->create([
        'id' => (string) str()->uuid(),
        'type' => 'App\\Notifications\\NewReview',
        'data' => ['review_id' => $this->review->id, 'message' => 'Gavote naują atsiliepimą'],
    ]);
    $replied = $this->author->notifications()->create([
        'id' => (string) str()->uuid(),
        'type' => ReviewReplied::class,
        'data' => ['review_id' => $this->review->id, 'message' => 'x'],
    ]);

    $this->actingAs($user)->get(route('notifications.open', $newReview->id))->assertRedirect(route('provider-reviews.index'));
    $this->actingAs($this->author)->get(route('notifications.open', $replied->id))
        ->assertRedirect(route('providers.show', $this->provider).'#atsiliepimai');
});
