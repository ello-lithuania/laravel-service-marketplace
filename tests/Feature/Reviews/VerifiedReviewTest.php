<?php

use App\Actions\ServiceRequests\CompleteServiceRequest;
use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\NewReview;
use App\Notifications\ReviewInvitation;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Patvirtintas atsiliepimas po atlikto darbo (Etapas 6).
 */
beforeEach(function () {
    Notification::fake();
    $this->request = ServiceRequest::factory()->completed()->create(['title' => 'Vonios plytelės']);
    $this->request->load(['client', 'acceptedOffer.providerProfile.user']);
    $this->client = $this->request->client;
    $this->provider = $this->request->acceptedOffer->providerProfile;
});

function reviewData(array $overrides = []): array
{
    return ['rating' => 5, 'comment' => 'Puikiai ir laiku atliktas darbas, rekomenduoju visiems!', ...$overrides];
}

test('pažymėjus „Darbas atliktas" klientas gauna kvietimą palikti atsiliepimą', function () {
    $inProgress = ServiceRequest::factory()->inProgress()->create();

    app(CompleteServiceRequest::class)->handle($inProgress);

    Notification::assertSentTo($inProgress->client, ReviewInvitation::class, function (ReviewInvitation $notification) use ($inProgress) {
        $data = $notification->toArray($inProgress->client);
        $mail = $notification->toMail($inProgress->client);

        return $data['service_request_id'] === $inProgress->id
            && str_contains($data['message'], 'Įvertinkite atliktą darbą')
            && $mail->actionUrl === route('service-requests.show', $inProgress).'#atsiliepimas';
    });
});

test('klientas palieka patvirtintą atsiliepimą – paskelbtas iš karto, teikėjui pranešama', function () {
    $this->actingAs($this->client)
        ->post(route('reviews.store', $this->request), reviewData(['comment' => '  Puikiai ir laiku atliktas darbas, rekomenduoju!  ']))
        ->assertRedirect(route('service-requests.show', $this->request))
        ->assertInertiaFlash('toast.message', 'Ačiū! Jūsų atsiliepimas paskelbtas.');

    $review = Review::query()->sole();
    expect($review)
        ->service_request_id->toBe($this->request->id)
        ->provider_profile_id->toBe($this->provider->id)
        ->author_id->toBe($this->client->id)
        ->rating->toBe(5)
        ->comment->toBe('Puikiai ir laiku atliktas darbas, rekomenduoju!')
        ->status->toBe(ReviewStatus::Published)
        ->published_at->not->toBeNull()
        ->and($review->isVerified())->toBeTrue();

    Notification::assertSentTo($this->provider->user, NewReview::class, fn (NewReview $n) => $n->toArray($this->provider->user) === [
        'review_id' => $review->id,
        'message' => 'Gavote naują atsiliepimą (5★) nuo '.$this->client->public_name,
    ]);
});

test('užklausos puslapyje – forma, o palikus – atsiliepimas', function () {
    $this->actingAs($this->client)->get(route('service-requests.show', $this->request))
        ->assertInertia(fn (Assert $page) => $page->where('can.review', true)->where('review', null));

    $this->actingAs($this->client)->post(route('reviews.store', $this->request), reviewData());

    $this->actingAs($this->client)->get(route('service-requests.show', $this->request))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.review', false)
            ->where('review.rating', 5)
            ->where('review.is_verified', true)
            ->where('review.service_request_title', 'Vonios plytelės'));
});

test('antrą kartą įvertinti negalima', function () {
    $this->actingAs($this->client)->post(route('reviews.store', $this->request), reviewData());
    $this->actingAs($this->client)->post(route('reviews.store', $this->request), reviewData(['rating' => 1]))->assertForbidden();

    expect(Review::query()->count())->toBe(1);
});

test('įvertinti gali tik užklausos klientas ir tik atliktą darbą per 60 d.', function () {
    $this->actingAs(User::factory()->create())->post(route('reviews.store', $this->request), reviewData())->assertForbidden();
    $this->actingAs($this->provider->user)->post(route('reviews.store', $this->request), reviewData())->assertForbidden();

    $inProgress = ServiceRequest::factory()->inProgress()->create();
    $this->actingAs($inProgress->client)->post(route('reviews.store', $inProgress), reviewData())->assertForbidden();

    $this->request->forceFill(['completed_at' => now()->subDays(Review::VERIFIED_WINDOW_DAYS + 1)])->save();
    $this->actingAs($this->client)->post(route('reviews.store', $this->request), reviewData())->assertForbidden();

    expect(Review::query()->count())->toBe(0);
});

test('validacija: įvertinimas 1–5 ir pakankamai ilgas tekstas', function () {
    $this->actingAs($this->client)
        ->post(route('reviews.store', $this->request), ['rating' => 6, 'comment' => 'Gerai'])
        ->assertSessionHasErrors([
            'rating' => 'Pasirinkite įvertinimą nuo 1 iki 5 žvaigždučių.',
            'comment',
        ]);
});

test('„Mano paskyra" priminimas: atlikti darbai be atsiliepimo, ne senesni nei 60 d.', function () {
    $old = ServiceRequest::factory()->completed()->create(['client_id' => $this->client->id]);
    $old->forceFill(['completed_at' => now()->subDays(61)])->save();
    $reviewed = ServiceRequest::factory()->completed()->create(['client_id' => $this->client->id]);
    $this->actingAs($this->client)->post(route('reviews.store', $reviewed), reviewData());

    $this->actingAs($this->client)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('client.review_prompts', 1)
            ->where('client.review_prompts.0.slug', $this->request->slug)
            ->where('client.review_prompts.0.provider', $this->provider->display_name));
});

test('kvietimo pranešimas veda į užklausos puslapį', function () {
    // Notification::fake() pranešimų į DB neįrašo – įrašom ranka su tais pačiais duomenimis
    $id = $this->client->notifications()->create([
        'id' => (string) str()->uuid(),
        'type' => ReviewInvitation::class,
        'data' => (new ReviewInvitation($this->request))->toArray($this->client),
    ])->id;

    $this->actingAs($this->client)->get(route('notifications.open', $id))
        ->assertRedirect(route('service-requests.show', $this->request));
});
