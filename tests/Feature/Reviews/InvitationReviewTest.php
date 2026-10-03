<?php

use App\Enums\ReviewStatus;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Atsiliepimas pagal teikėjo pakvietimo nuorodą (signed URL).
 */
beforeEach(function () {
    Notification::fake();
    $this->provider = ProviderProfile::factory()->create(['display_name' => 'Meistras Jonas']);
    $this->client = User::factory()->create();
});

function invitationUrl(ProviderProfile $provider, ?CarbonInterface $expires = null): string
{
    return URL::temporarySignedRoute('reviews.invitation.show', $expires ?? now()->addDays(30), ['providerProfile' => $provider]);
}

function invitationReview(): array
{
    return ['rating' => 4, 'comment' => 'Prieš metus dažė mūsų butą – tvarkingai ir laiku.'];
}

test('teikėjo puslapyje – pasirašyta pakvietimo nuoroda', function () {
    $this->actingAs($this->provider->user)->get(route('provider-reviews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/reviews/Index')
            ->where('invitation.url', fn (string $url) => str_contains($url, '/atsiliepimas/'.$this->provider->slug)
                && str_contains($url, 'signature=')
                && str_contains($url, 'expires=')));
});

test('klientas atidaro nuorodą ir palieka atsiliepimą – laukia moderavimo, teikėjui dar nepranešama', function () {
    $url = invitationUrl($this->provider);

    $this->actingAs($this->client)->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reviews/Invitation')
            ->where('provider.display_name', 'Meistras Jonas')
            ->where('can.create', true));

    $this->actingAs($this->client)->post($url, invitationReview())
        ->assertRedirect(route('providers.show', $this->provider))
        ->assertInertiaFlash('toast.message', 'Ačiū! Atsiliepimas bus paskelbtas, kai jį peržiūrės administratorius.');

    expect(Review::query()->sole())
        ->service_request_id->toBeNull()
        ->provider_profile_id->toBe($this->provider->id)
        ->author_id->toBe($this->client->id)
        ->status->toBe(ReviewStatus::Pending)
        ->published_at->toBeNull();
    Notification::assertNothingSent();
});

test('be parašo, su suklastotu ar pasibaigusiu parašu – 403', function () {
    $this->actingAs($this->client)->get(route('reviews.invitation.show', $this->provider))->assertForbidden();

    $forged = str_replace($this->provider->slug, ProviderProfile::factory()->create()->slug, invitationUrl($this->provider));
    $this->actingAs($this->client)->get($forged)->assertForbidden();

    $expired = invitationUrl($this->provider, now()->addDay());
    $this->travel(2)->days();
    $this->actingAs($this->client)->get($expired)
        ->assertForbidden()
        ->assertSee('Nuoroda neteisinga arba jos galiojimas baigėsi.');
    $this->actingAs($this->client)->post($expired, invitationReview())->assertForbidden();

    expect(Review::query()->count())->toBe(0);
});

test('neprisijungęs nukreipiamas prisijungti, po to grįžta į tą pačią nuorodą', function () {
    $url = invitationUrl($this->provider);

    $this->get($url)->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $this->client->email, 'password' => 'password'])
        ->assertRedirect($url);
});

test('savo profilio ir teikėjo paskyra vertinti negali', function () {
    $url = invitationUrl($this->provider);

    $this->actingAs($this->provider->user)->get($url)
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.create', false)
            ->where('can.reason', 'Savo profilio vertinti negalima.'));
    $this->actingAs($this->provider->user)->post($url, invitationReview())->assertForbidden();

    $otherProvider = User::factory()->provider()->create();
    $this->actingAs($otherProvider)->post($url, invitationReview())->assertForbidden();

    expect(Review::query()->count())->toBe(0);
});

test('tas pats klientas tam pačiam teikėjui – ne dažniau kaip kartą per 12 mėn.', function () {
    $url = invitationUrl($this->provider);
    $this->actingAs($this->client)->post($url, invitationReview())->assertRedirect();

    $this->actingAs($this->client)->get($url)
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.create', false)
            ->where('can.reason', 'Šiam teikėjui atsiliepimą jau palikote per pastaruosius 12 mėnesių.'));
    $this->actingAs($this->client)->post($url, invitationReview())->assertForbidden();

    // Po metų – vėl galima (nauja nuoroda)
    $this->travel(Review::INVITATION_COOLDOWN_DAYS + 1)->days();
    $this->actingAs($this->client)->post(invitationUrl($this->provider), invitationReview())->assertRedirect();

    expect(Review::query()->count())->toBe(2);
});

test('patvirtintas atsiliepimas tam pačiam teikėjui irgi skaičiuojamas į 12 mėn. ribą', function () {
    Review::factory()->for($this->provider)->create(['author_id' => $this->client->id]);

    $this->actingAs($this->client)->post(invitationUrl($this->provider), invitationReview())->assertForbidden();
});

test('neaktyvaus teikėjo vertinti negalima', function () {
    $this->provider->forceFill(['status' => 'hidden'])->save();

    $this->actingAs($this->client)->post(invitationUrl($this->provider), invitationReview())->assertForbidden();
});
