<?php

use App\Enums\OfferStatus;
use App\Models\Category;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Marketplace;

beforeEach(function () {
    Notification::fake();
    $this->request = Marketplace::openRequest(cost: 2, attributes: ['address' => 'Slapta g. 5']);
    $this->provider = Marketplace::eligibleProvider($this->request, credits: 5);
});

function offerPayload(array $overrides = []): array
{
    return [
        'message' => 'Laba diena, galiu atvykti apžiūrėti jau rytoj po pietų.',
        'price_type' => 'fixed',
        'price' => '149.90',
        'duration_text' => '2 dienos',
        'start_date' => '',
        ...$overrides,
    ];
}

test('srautas rodo tik tinkamas atviras užklausas, kurioms dar nesiųsta', function () {
    $offered = ServiceRequest::factory()->create(['category_id' => $this->request->category_id, 'city_id' => $this->request->city_id]);
    Offer::factory()->for($offered)->for($this->provider)->create();
    ServiceRequest::factory()->pending()->create(['category_id' => $this->request->category_id, 'city_id' => $this->request->city_id]);
    ServiceRequest::factory()->create(); // kita kategorija ir miestas

    $this->actingAs($this->provider->user)
        ->get('/teikejas/uzklausos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('provider-feed/Index')
            ->has('serviceRequests.data', 1)
            ->where('serviceRequests.data.0.id', $this->request->id)
            ->where('serviceRequests.data.0.category.offer_cost_credits', 2)
            ->missing('serviceRequests.data.0.address')
            ->where('provider.credits_balance', 5)
            ->has('options.categories', 1)
            ->has('options.cities', 1));
});

test('srauto filtrai: kategorija, miestas, laikotarpis', function () {
    $sibling = Category::factory()->create(['parent_id' => $this->request->category->parent_id, 'depth' => 3]);
    $this->provider->categories()->attach($this->request->category->parent_id);
    $older = ServiceRequest::factory()->create([
        'category_id' => $sibling->id,
        'city_id' => $this->request->city_id,
        'published_at' => now()->subDays(10),
    ]);

    $ids = fn (string $query) => collect($this->actingAs($this->provider->user)->get('/teikejas/uzklausos?'.$query)
        ->viewData('page')['props']['serviceRequests']['data'])->pluck('id')->all();

    expect($ids(''))->toEqualCanonicalizing([$this->request->id, $older->id])
        ->and($ids('kategorija='.$sibling->id))->toBe([$older->id])
        ->and($ids('laikotarpis=7'))->toBe([$this->request->id])
        ->and($ids('miestas='.$this->request->city_id.'&laikotarpis=30'))->toEqualCanonicalizing([$this->request->id, $older->id]);
});

test('klientas srauto nemato', function () {
    $this->actingAs(User::factory()->create())->get('/teikejas/uzklausos')->assertForbidden();
});

test('teikėjas be profilio mato tuščią srautą su paaiškinimu', function () {
    $this->actingAs(User::factory()->provider()->create())
        ->get('/teikejas/uzklausos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('provider', null)->where('serviceRequests', null));
});

test('tinkamas teikėjas mato užklausą be adreso ir kontaktų, su pasiūlymo forma', function () {
    $this->actingAs($this->provider->user)
        ->get(route('service-requests.show', $this->request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-requests/ProviderShow')
            ->missing('serviceRequest.address')
            ->where('client.public_name', $this->request->client->public_name)
            ->where('client.phone', null)
            ->where('client.email', null)
            ->where('myOffer', null)
            ->where('offerForm.allowed', true)
            ->where('offerForm.cost', 2)
            ->where('offerForm.balance', 5));

    expect($this->request->refresh()->views_count)->toBe(1);
});

test('netinkamas teikėjas užklausos nemato', function () {
    $stranger = ProviderProfile::factory()->create();

    $this->actingAs($stranger->user)->get(route('service-requests.show', $this->request))->assertForbidden();
});

test('pasiūlymo siuntimas per HTTP: kaina eurais → centais, kreditai nurašomi', function () {
    $this->actingAs($this->provider->user)
        ->post(route('offers.store', $this->request), offerPayload())
        ->assertRedirect(route('service-requests.show', $this->request))
        ->assertInertiaFlash('toast.message', 'Pasiūlymas išsiųstas. Nurašyta kreditų: 2.');

    expect(Offer::query()->sole())
        ->price_cents->toBe(14990)
        ->credits_spent->toBe(2)
        ->provider_profile_id->toBe($this->provider->id)
        ->and($this->provider->refresh()->credits_balance)->toBe(3);
});

test('pasiūlymo validacija: kaina privaloma, nebent „po apžiūros"', function () {
    $this->actingAs($this->provider->user)
        ->post(route('offers.store', $this->request), offerPayload(['price' => '', 'message' => 'Trumpa']))
        ->assertSessionHasErrors([
            'price' => 'Nurodykite kainą arba pasirinkite „Po apžiūros".',
            'message' => 'Simbolių kiekis lauke žinutė klientui turi būti ne mažiau nei 20.',
        ]);

    $this->actingAs($this->provider->user)
        ->post(route('offers.store', $this->request), offerPayload(['price' => '', 'price_type' => 'after_inspection']))
        ->assertSessionHasNoErrors();

    expect(Offer::query()->sole()->price_cents)->toBeNull();
});

test('neužtenkant kreditų – klaida „credits", pasiūlymas nesukuriamas', function () {
    $this->provider->forceFill(['credits_balance' => 1])->save();

    $this->actingAs($this->provider->user)
        ->post(route('offers.store', $this->request), offerPayload())
        ->assertSessionHasErrors(['credits' => 'Nepakanka kreditų: pasiūlymas kainuoja 2 kred., o jūs turite 1.']);

    expect(Offer::query()->count())->toBe(0);
});

test('netinkamas teikėjas ir pakartotinis siuntimas atmetami Policy (403 su priežastimi)', function () {
    $stranger = ProviderProfile::factory()->withCredits(10)->create();
    $this->actingAs($stranger->user)
        ->post(route('offers.store', $this->request), offerPayload())
        ->assertForbidden();

    $this->actingAs($this->provider->user)->post(route('offers.store', $this->request), offerPayload());
    $this->actingAs($this->provider->user)
        ->post(route('offers.store', $this->request), offerPayload())
        ->assertForbidden()
        ->assertSee('Šiai užklausai pasiūlymą jau išsiuntėte.');

    expect(Offer::query()->count())->toBe(1);
});

test('išsiuntęs pasiūlymą teikėjas mato jį užklausos puslapyje ir gali atšaukti', function () {
    $offer = Offer::factory()->for($this->request)->for($this->provider)->create();

    $this->actingAs($this->provider->user)
        ->get(route('service-requests.show', $this->request))
        ->assertInertia(fn (Assert $page) => $page
            ->where('myOffer.id', $offer->id)
            ->where('myOffer.can.withdraw', true)
            ->where('offerForm.allowed', false));

    $this->actingAs($this->provider->user)
        ->post(route('offers.withdraw', $offer))
        ->assertRedirect(route('service-requests.show', $this->request));

    expect($offer->refresh()->status)->toBe(OfferStatus::Withdrawn);
});

test('svetimo pasiūlymo atšaukti negalima', function () {
    $offer = Offer::factory()->for($this->request)->create();

    $this->actingAs($this->provider->user)->post(route('offers.withdraw', $offer))->assertForbidden();
});

test('išrinktas teikėjas mato adresą ir kliento kontaktus, net kai užklausa nebe atvira', function () {
    $offer = Offer::factory()->accepted()->for($this->request)->for($this->provider)->create();
    $this->request->forceFill(['status' => 'in_progress', 'accepted_offer_id' => $offer->id])->save();

    $this->actingAs($this->provider->user)
        ->get(route('service-requests.show', $this->request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('serviceRequest.address', 'Slapta g. 5')
            ->where('client.email', $this->request->client->email)
            ->where('client.phone', $this->request->client->phone)
            ->where('myOffer.is_chosen', true));
});

test('neišrinktas teikėjas, siuntęs pasiūlymą, mato užklausą, bet ne adresą', function () {
    $winner = Offer::factory()->accepted()->for($this->request)->create();
    Offer::factory()->declined()->for($this->request)->for($this->provider)->create();
    $this->request->forceFill(['status' => 'in_progress', 'accepted_offer_id' => $winner->id])->save();

    $this->actingAs($this->provider->user)
        ->get(route('service-requests.show', $this->request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('serviceRequest.address')
            ->where('client.phone', null)
            ->where('myOffer.is_chosen', false));
});

test('„Mano pasiūlymai" – tik savi', function () {
    Offer::factory()->for($this->request)->for($this->provider)->create();
    Offer::factory()->create();

    $this->actingAs($this->provider->user)
        ->get('/mano-pasiulymai')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('offers/Index')
            ->has('offers.data', 1)
            ->where('offers.data.0.service_request.slug', $this->request->slug)
            ->where('creditsBalance', 5));
});
