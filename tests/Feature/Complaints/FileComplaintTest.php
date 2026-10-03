<?php

use App\Enums\ComplaintReason;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use Tests\Support\Marketplace;
use Tests\Support\Messaging;

/**
 * Skundai (Etapas 6): „Pranešti apie pažeidimą" užklausai, pasiūlymui, atsiliepimui, žinutei ir profiliui.
 */
function complaintData(string $type, int $id, array $overrides = []): array
{
    return ['type' => $type, 'id' => $id, 'reason' => 'spam', 'description' => 'Atrodo kaip reklama.', ...$overrides];
}

test('klientas praneša apie pasiūlymą – skundas su morph tipu „offer"', function () {
    $offer = Messaging::offer();
    $client = Messaging::client($offer);

    $this->actingAs($client)
        ->from(route('offers.show', ['serviceRequest' => $offer->serviceRequest, 'offer' => $offer]))
        ->post(route('complaints.store'), complaintData('offer', $offer->id, ['reason' => 'fraud']))
        ->assertRedirect(route('offers.show', ['serviceRequest' => $offer->serviceRequest, 'offer' => $offer]))
        ->assertInertiaFlash('toast.message', 'Ačiū! Pranešimą gavome – administratorius jį peržiūrės.');

    $complaint = Complaint::query()->sole();
    expect($complaint)
        ->reporter_id->toBe($client->id)
        ->reportable_type->toBe('offer')
        ->reportable_id->toBe($offer->id)
        ->reason->toBe(ComplaintReason::Fraud)
        ->status->toBe(ComplaintStatus::Open)
        ->and($complaint->reportable)->toBeInstanceOf(Offer::class);
});

test('teikėjas praneša apie jam matomą užklausą, bet ne apie nematomą (404)', function () {
    $request = Marketplace::openRequest();
    $provider = Marketplace::eligibleProvider($request);

    $this->actingAs($provider->user)->post(route('complaints.store'), complaintData('service_request', $request->id))->assertRedirect();

    $hidden = ServiceRequest::factory()->pending()->create();
    $this->actingAs($provider->user)->post(route('complaints.store'), complaintData('service_request', $hidden->id))->assertNotFound();

    expect(Complaint::query()->count())->toBe(1);
});

test('teikėjas praneša apie netikrą atsiliepimą apie save', function () {
    $provider = ProviderProfile::factory()->create();
    $review = Review::factory()->for($provider)->create();

    $this->actingAs($provider->user)
        ->post(route('complaints.store'), complaintData('review', $review->id, ['reason' => 'fake_review']))
        ->assertRedirect();

    expect(Complaint::query()->sole()->reason)->toBe(ComplaintReason::FakeReview);
});

test('žinutė: dalyvis gali pranešti apie kito žinutę, ne apie savo; ne dalyviui – 404', function () {
    $offer = Messaging::offer();
    $conversation = Messaging::conversation($offer);
    $theirs = Messaging::send($conversation, Messaging::provider($offer), 'Mokėkite avansą į mano sąskaitą');
    $mine = Messaging::send($conversation, Messaging::client($offer), 'Ne, ačiū');

    $this->actingAs(Messaging::client($offer))->post(route('complaints.store'), complaintData('message', $theirs->id))->assertRedirect();
    $this->actingAs(Messaging::client($offer))->post(route('complaints.store'), complaintData('message', $mine->id))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('complaints.store'), complaintData('message', $theirs->id))->assertNotFound();

    expect(Complaint::query()->count())->toBe(1);
});

test('profilis: bet kuris prisijungęs gali pranešti, savininkas – ne', function () {
    $provider = ProviderProfile::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('complaints.store'), complaintData('provider_profile', $provider->id, ['reason' => 'wrong_info']))->assertRedirect();
    $this->actingAs($provider->user)->post(route('complaints.store'), complaintData('provider_profile', $provider->id))->assertForbidden();
});

test('tas pats žmogus apie tą patį įrašą – vienas neužbaigtas skundas', function () {
    $provider = ProviderProfile::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('complaints.store'), complaintData('provider_profile', $provider->id));
    $this->actingAs($user)->post(route('complaints.store'), complaintData('provider_profile', $provider->id, ['reason' => 'fraud']))
        ->assertSessionHasErrors(['complaint' => 'Apie tai jau pranešėte – skundas nagrinėjamas.']);

    // Kitas žmogus – gali
    $this->actingAs(User::factory()->create())->post(route('complaints.store'), complaintData('provider_profile', $provider->id))->assertSessionHasNoErrors();

    // Išnagrinėjus – vėl galima
    Complaint::query()->where('reporter_id', $user->id)->update(['status' => ComplaintStatus::Rejected]);
    $this->actingAs($user)->post(route('complaints.store'), complaintData('provider_profile', $provider->id))->assertSessionHasNoErrors();

    expect(Complaint::query()->count())->toBe(3);
});

test('validacija: tipas, įrašo buvimas, „Kita" reikalauja aprašymo', function () {
    $user = User::factory()->create();
    $provider = ProviderProfile::factory()->create();

    $this->actingAs($user)->post(route('complaints.store'), complaintData('user', $user->id))->assertSessionHasErrors('type');
    $this->actingAs($user)->post(route('complaints.store'), complaintData('provider_profile', 999_999))
        ->assertSessionHasErrors(['id' => 'Turinys, apie kurį norite pranešti, nebeegzistuoja.']);
    $this->actingAs($user)->post(route('complaints.store'), complaintData('provider_profile', $provider->id, ['reason' => 'other', 'description' => '']))
        ->assertSessionHasErrors(['description' => 'Pasirinkus „Kita", trumpai aprašykite problemą.']);
    $this->actingAs($user)->post(route('complaints.store'), complaintData('provider_profile', $provider->id, ['reason' => 'nezinoma']))
        ->assertSessionHasErrors('reason');

    expect(Complaint::query()->count())->toBe(0);
});

test('paslėptos žinutės skųsti nebegalima', function () {
    $offer = Messaging::offer();
    $message = Messaging::send(Messaging::conversation($offer), Messaging::provider($offer));
    $message->delete();

    $this->actingAs(Messaging::client($offer))->post(route('complaints.store'), complaintData('message', $message->id))->assertSessionHasErrors('id');
});

test('svečias nukreipiamas prisijungti, nepatvirtintas – patvirtinti el. paštą', function () {
    $provider = ProviderProfile::factory()->create();

    $this->post(route('complaints.store'), complaintData('provider_profile', $provider->id))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())
        ->post(route('complaints.store'), complaintData('provider_profile', $provider->id))
        ->assertRedirect(route('verification.notice'));
});
