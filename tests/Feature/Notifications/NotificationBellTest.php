<?php

use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\NewMatchingRequest;
use App\Notifications\NewOffer;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Įrašo pranešimą tiesiai į lentelę – kaip seed'ai (NotificationGenerator).
 *
 * @param  array<string, mixed>  $data
 */
function storeNotification(User $user, string $type, array $data, bool $read = false): string
{
    $id = (string) Str::uuid();

    $user->notifications()->create([
        'id' => $id,
        'type' => 'App\\Notifications\\'.$type,
        'data' => $data,
        'read_at' => $read ? now() : null,
    ]);

    return $id;
}

test('neperskaitytų skaičius – bendrame Inertia prop\'e', function () {
    $user = User::factory()->create();
    storeNotification($user, 'NewOffer', ['message' => 'A']);
    storeNotification($user, 'NewOffer', ['message' => 'B']);
    storeNotification($user, 'NewOffer', ['message' => 'C'], read: true);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 2));
});

test('svečiui pranešimų prop\'as tuščias', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('notifications', null));
});

test('varpelio sąrašas (JSON) – naujausi pirmi, tik savi', function () {
    $user = User::factory()->create();
    $this->travelTo(now()->subHour());
    storeNotification($user, 'NewMatchingRequest', ['service_request_id' => 1, 'message' => 'Senas']);
    $this->travelBack();
    storeNotification($user, 'NewOffer', ['message' => 'Naujas']);
    storeNotification(User::factory()->create(), 'NewOffer', ['message' => 'Svetimas']);

    $this->actingAs($user)
        ->getJson(route('notifications.latest'))
        ->assertOk()
        ->assertJsonPath('unread_count', 2)
        ->assertJsonCount(2, 'notifications')
        ->assertJsonPath('notifications.0.message', 'Naujas')
        ->assertJsonPath('notifications.0.type', 'NewOffer')
        ->assertJsonPath('notifications.1.type', 'NewMatchingRequest');
});

test('atidarius pranešimą jis pažymimas perskaitytu ir nukreipiama į užklausą', function () {
    $request = ServiceRequest::factory()->create();
    $provider = User::factory()->provider()->create();
    $id = storeNotification($provider, 'NewMatchingRequest', ['service_request_id' => $request->id, 'message' => 'x']);

    $this->actingAs($provider)
        ->get(route('notifications.open', $id))
        ->assertRedirect(route('service-requests.show', $request));

    expect($provider->notifications()->find($id)->read_at)->not->toBeNull();
});

test('naujo pasiūlymo pranešimas veda tiesiai į pasiūlymą', function () {
    $offer = Offer::factory()->create();
    $client = $offer->serviceRequest->client;
    $id = storeNotification($client, 'NewOffer', [
        'offer_id' => $offer->id,
        'service_request_id' => $offer->service_request_id,
        'message' => 'x',
    ]);

    $this->actingAs($client)
        ->get(route('notifications.open', $id))
        ->assertRedirect(route('offers.show', [$offer->serviceRequest, $offer]));
});

test('pranešimas be užklausos ir nežinomo tipo veda į pranešimų sąrašą', function () {
    $user = User::factory()->create();
    $id = storeNotification($user, 'SomethingOld', ['message' => 'Senas pranešimas']);

    $this->actingAs($user)->get(route('notifications.open', $id))->assertRedirect(route('notifications.index'));
});

test('žinutės pranešimas (Etapas 6) veda į pokalbį', function () {
    $user = User::factory()->create();
    $id = storeNotification($user, 'NewMessage', ['conversation_id' => 5, 'message' => 'Gavote naują žinutę']);

    $this->actingAs($user)->get(route('notifications.open', $id))->assertRedirect(route('conversations.show', 5));
});

test('svetimo pranešimo atidaryti ar pažymėti negalima (404)', function () {
    $id = storeNotification(User::factory()->create(), 'NewOffer', ['message' => 'x']);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('notifications.open', $id))->assertNotFound();
    $this->actingAs($user)->post(route('notifications.read', $id))->assertNotFound();
});

test('pažymėti vieną ir visus perskaitytais', function () {
    $user = User::factory()->create();
    $first = storeNotification($user, 'NewOffer', ['message' => 'A']);
    storeNotification($user, 'NewOffer', ['message' => 'B']);
    $foreign = storeNotification(User::factory()->create(), 'NewOffer', ['message' => 'C']);

    $this->actingAs($user)->from('/pranesimai')->post(route('notifications.read', $first))->assertRedirect('/pranesimai');
    expect($user->unreadNotifications()->count())->toBe(1);

    $this->actingAs($user)->from('/pranesimai')->post(route('notifications.read-all'))->assertRedirect('/pranesimai');
    expect($user->unreadNotifications()->count())->toBe(0)
        ->and(User::query()->whereKeyNot($user->id)->firstOrFail()->notifications()->find($foreign)->read_at)->toBeNull();
});

test('pranešimų puslapis su puslapiavimu', function () {
    $user = User::factory()->create();
    foreach (range(1, 25) as $i) {
        storeNotification($user, 'NewOffer', ['message' => "Pranešimas {$i}"]);
    }

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/Index')
            ->has('notifications.data', 20)
            ->where('notifications.meta.total', 25)
            ->where('notifications.data.0.url', fn (string $url) => str_contains($url, '/pranesimai/')));
});

test('tikras pranešimas per database kanalą atsiranda varpelyje', function () {
    $offer = Offer::factory()->create();
    $client = $offer->serviceRequest->client;

    $client->notifyNow(new NewOffer($offer), ['database']);
    $offer->providerProfile->user->notifyNow(new NewMatchingRequest($offer->serviceRequest), ['database']);

    $this->actingAs($client)
        ->getJson(route('notifications.latest'))
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.message', $offer->providerProfile->display_name.' atsiuntė pasiūlymą užklausai „'.$offer->serviceRequest->title.'"');
});

test('svečias pranešimų nemato', function () {
    $this->get(route('notifications.index'))->assertRedirect('/login');
    $this->getJson(route('notifications.latest'))->assertUnauthorized();
});
