<?php

use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Messaging;

/**
 * Pokalbiai ir žinutės (Etapas 6): pradžia iš pasiūlymo, siuntimas, teisės, neperskaitytų skaičius.
 */

// --- Pokalbio pradžia („Rašyti") --------------------------------------------------------------

test('klientas pradeda pokalbį dėl laukiančio pasiūlymo – dalyviai abu, antrą kartą tas pats pokalbis', function () {
    $offer = Messaging::offer();
    $client = Messaging::client($offer);

    $this->actingAs($client)->post(route('conversations.store', $offer))
        ->assertRedirect(route('conversations.show', Conversation::query()->sole()));

    $this->actingAs($client)->post(route('conversations.store', $offer))->assertRedirect();

    $conversation = Conversation::query()->sole();
    expect($conversation->offer_id)->toBe($offer->id)
        ->and($conversation->service_request_id)->toBe($offer->service_request_id)
        ->and($conversation->participants()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$client->id, Messaging::provider($offer)->id])->sort()->values()->all());
});

test('teikėjas pradėti gali tik kai jo pasiūlymas priimtas', function () {
    $pending = Messaging::offer();
    $this->actingAs(Messaging::provider($pending))->post(route('conversations.store', $pending))->assertForbidden();

    $accepted = Messaging::offer('accepted');
    $this->actingAs(Messaging::provider($accepted))->post(route('conversations.store', $accepted))->assertRedirect();
    expect(Conversation::query()->where('offer_id', $accepted->id)->exists())->toBeTrue();
});

test('svetimas klientas ar teikėjas pokalbio pradėti negali', function () {
    $offer = Messaging::offer();

    $this->actingAs(User::factory()->create())->post(route('conversations.store', $offer))->assertForbidden();
    $this->actingAs(User::factory()->provider()->create())->post(route('conversations.store', $offer))->assertForbidden();
    expect(Conversation::query()->count())->toBe(0);
});

test('atmesto pasiūlymo pokalbio pradėti nebegalima', function () {
    $offer = Messaging::offer();
    $offer->forceFill(['status' => OfferStatus::Declined])->save();

    $this->actingAs(Messaging::client($offer))->post(route('conversations.store', $offer))->assertForbidden();
});

test('nepatvirtinto el. pašto vartotojas rašyti negali', function () {
    $offer = Messaging::offer();
    $client = Messaging::client($offer);
    $client->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($client)->post(route('conversations.store', $offer))->assertRedirect(route('verification.notice'));
});

// --- Žinutės siuntimas ----------------------------------------------------------------------

test('klientas parašo, teikėjas atsako: last_message_at ir siuntėjo last_read_message_id', function () {
    $offer = Messaging::offer();
    $conversation = Messaging::conversation($offer);
    $client = Messaging::client($offer);
    $provider = Messaging::provider($offer);

    $this->actingAs($client)
        ->post(route('messages.store', $conversation), ['body' => '  Ar galite atvykti šeštadienį?  '])
        ->assertRedirect(route('conversations.show', $conversation));

    $first = Message::query()->sole();
    expect($first->body)->toBe('Ar galite atvykti šeštadienį?')
        ->and($first->sender_id)->toBe($client->id)
        ->and($conversation->refresh()->last_message_at?->equalTo($first->created_at))->toBeTrue()
        ->and($conversation->participants()->find($client->id)?->pivot?->last_read_message_id)->toBe($first->id)
        ->and($conversation->participants()->find($provider->id)?->pivot?->last_read_message_id)->toBeNull();

    $this->actingAs($provider)
        ->post(route('messages.store', $conversation), ['body' => 'Taip, 10 val. tinka.'])
        ->assertRedirect();

    expect(Message::query()->count())->toBe(2);
});

test('tuščios žinutės siųsti negalima', function () {
    $offer = Messaging::offer();
    $conversation = Messaging::conversation($offer);

    $this->actingAs(Messaging::client($offer))
        ->post(route('messages.store', $conversation), ['body' => '   '])
        ->assertSessionHasErrors(['body' => 'Parašykite žinutę arba pridėkite priedą.']);
});

test('ne dalyvis rašyti negali, administratorius – mato, bet nerašo', function () {
    $offer = Messaging::offer();
    $conversation = Messaging::conversation($offer);
    Messaging::send($conversation, Messaging::client($offer));
    $admin = User::factory()->admin()->create();

    $this->actingAs(User::factory()->create())->get(route('conversations.show', $conversation))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('messages.store', $conversation), ['body' => 'Labas'])->assertForbidden();

    $this->actingAs($admin)->get(route('conversations.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('messages/Show')
            ->where('can.send', false)
            ->has('messages.data', 1)
            ->where('conversation.counterpart.role', 'both'));
    $this->actingAs($admin)->post(route('messages.store', $conversation), ['body' => 'Labas'])->assertForbidden();
});

test('kai priimamas kitas pasiūlymas, šio pokalbio rašymas uždaromas', function () {
    $offer = Messaging::offer();
    $conversation = Messaging::conversation($offer);
    $offer->forceFill(['status' => OfferStatus::Declined])->save();

    $this->actingAs(Messaging::client($offer))
        ->post(route('messages.store', $conversation), ['body' => 'Ar dar aktualu?'])
        ->assertForbidden();
});

test('priimto pasiūlymo pokalbis tęsiasi ir užbaigus darbą', function () {
    $offer = Messaging::offer('accepted');
    $conversation = Messaging::conversation($offer);
    $offer->serviceRequest->forceFill(['status' => ServiceRequestStatus::Completed, 'completed_at' => now()])->save();

    $this->actingAs(Messaging::provider($offer))
        ->post(route('messages.store', $conversation), ['body' => 'Ačiū! Jei kas – garantija 2 metai.'])
        ->assertRedirect();

    expect(Message::query()->count())->toBe(1);
});

// --- Sąrašas, perskaitymas, neperskaitytų skaičius ------------------------------------------

test('sąraše – tik mano pokalbiai su žinutėmis, naujausi viršuje, su neperskaitytų skaičiumi', function () {
    $first = Messaging::offer();
    $client = Messaging::client($first);
    $older = Messaging::conversation($first);
    Messaging::send($older, Messaging::provider($first), 'Sena žinutė');
    $this->travel(5)->minutes();

    $second = Messaging::offer();
    $second->serviceRequest->forceFill(['client_id' => $client->id])->save();
    $newer = Messaging::conversation($second->refresh()->load(['serviceRequest.client', 'providerProfile.user']));
    Messaging::send($newer, $client, 'Mano klausimas');
    Messaging::send($newer, Messaging::provider($second), 'Atsakymas 1');
    Messaging::send($newer, Messaging::provider($second), 'Atsakymas 2');

    // Tuščias pokalbis ir svetimas pokalbis nerodomi
    Messaging::conversation(Messaging::offer());
    $empty = Messaging::offer();
    $empty->serviceRequest->forceFill(['client_id' => $client->id])->save();
    Messaging::conversation($empty->refresh()->load(['serviceRequest.client', 'providerProfile.user']));

    $this->actingAs($client)->get(route('conversations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('messages/Index')
            ->has('conversations.data', 2)
            ->where('conversations.data.0.id', $newer->id)
            ->where('conversations.data.0.unread_count', 2)
            ->where('conversations.data.0.last_message.excerpt', 'Atsakymas 2')
            ->where('conversations.data.0.last_message.is_mine', false)
            ->where('conversations.data.0.counterpart.role', 'provider')
            ->where('conversations.data.1.id', $older->id)
            ->where('conversations.data.1.unread_count', 1)
            ->where('inbox.unread_count', 3));
});

test('atidarius pokalbį jis pažymimas perskaitytu', function () {
    $offer = Messaging::offer();
    $conversation = Messaging::conversation($offer);
    $client = Messaging::client($offer);
    Messaging::send($conversation, $client, 'Klausimas');
    $reply = Messaging::send($conversation, Messaging::provider($offer), 'Atsakymas');

    $this->actingAs($client)->get(route('conversations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('inbox.unread_count', 1));

    $this->actingAs($client)->get(route('conversations.show', $conversation))
        ->assertInertia(fn (Assert $page) => $page
            // Puslapis – nuo naujausių (seniausias viršuje sudėliojamas Show.vue)
            ->has('messages.data', 2)
            ->where('messages.data.0.body', 'Atsakymas')
            ->where('messages.data.0.sender_name', $offer->providerProfile->display_name)
            ->where('messages.data.1.is_mine', true)
            ->where('can.send', true));

    expect($conversation->participants()->find($client->id)?->pivot?->last_read_message_id)->toBe($reply->id);

    $this->actingAs($client)->get(route('conversations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('inbox.unread_count', 0)
            ->where('conversations.data.0.unread_count', 0));
});

test('paslėpta žinutė rodoma be teksto', function () {
    $offer = Messaging::offer();
    $conversation = Messaging::conversation($offer);
    Messaging::send($conversation, Messaging::provider($offer), 'Skambinkite man +37060000000')->delete();

    $this->actingAs(Messaging::client($offer))->get(route('conversations.show', $conversation))
        ->assertInertia(fn (Assert $page) => $page
            ->has('messages.data', 1)
            ->where('messages.data.0.is_hidden', true)
            ->where('messages.data.0.body', null));
});

test('teikėjas mato kliento vardą „Vardas P."', function () {
    $offer = Messaging::offer();
    $client = Messaging::client($offer);
    $client->forceFill(['first_name' => 'Rūta', 'last_name' => 'Jonaitienė'])->save();
    $conversation = Messaging::conversation($offer);
    Messaging::send($conversation, $client);

    $this->actingAs(Messaging::provider($offer))->get(route('conversations.show', $conversation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation.counterpart', ['name' => 'Rūta J.', 'role' => 'client'])
            ->where('messages.data.0.sender_name', 'Rūta J.'));
});

// --- „Rašyti žinutę" mygtukas (App\Support\OfferMessaging) -----------------------------------

test('kliento pasiūlymo puslapyje – „Rašyti žinutę", o pokalbį pradėjus – nuoroda į jį', function () {
    $offer = Messaging::offer();
    $client = Messaging::client($offer);
    $url = route('offers.show', ['serviceRequest' => $offer->serviceRequest, 'offer' => $offer]);

    $this->actingAs($client)->get($url)
        ->assertInertia(fn (Assert $page) => $page->where('messaging', ['conversation_id' => null, 'can_start' => true]));

    $conversation = Messaging::conversation($offer);

    $this->actingAs($client)->get($url)
        ->assertInertia(fn (Assert $page) => $page->where('messaging.conversation_id', $conversation->id));
    $this->actingAs($client)->get(route('service-requests.show', $offer->serviceRequest))
        ->assertInertia(fn (Assert $page) => $page->where('offers.0.messaging.conversation_id', $conversation->id));
});

test('teikėjas tuščio pokalbio nemato, o klientui parašius – mato', function () {
    $offer = Messaging::offer();
    $conversation = Messaging::conversation($offer);
    $provider = Messaging::provider($offer);
    $url = route('service-requests.show', $offer->serviceRequest);

    $this->actingAs($provider)->get($url)
        ->assertInertia(fn (Assert $page) => $page->where('myOffer.messaging', ['conversation_id' => null, 'can_start' => false]));

    Messaging::send($conversation, Messaging::client($offer));

    $this->actingAs($provider)->get($url)
        ->assertInertia(fn (Assert $page) => $page->where('myOffer.messaging.conversation_id', $conversation->id));
});
