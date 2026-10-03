<?php

use App\Enums\ServiceRequestStatus;
use App\Jobs\NotifyMatchingProviders;
use App\Models\Category;
use App\Models\City;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\NewMatchingRequest;
use App\Services\Matching\ProviderMatcher;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Marketplace;

beforeEach(function () {
    $this->matcher = app(ProviderMatcher::class);
    $this->request = Marketplace::openRequest();
    $this->request->load('category.parent');
});

test('tinka teikėjas su užklausos kategorija, jos tėvu arba seneliu', function () {
    $leaf = Marketplace::eligibleProvider($this->request);
    $parent = Marketplace::eligibleProvider($this->request, category: $this->request->category->parent);
    $grandparent = Marketplace::eligibleProvider($this->request, category: Category::query()->findOrFail($this->request->category->parent->parent_id));

    $eligible = $this->matcher->eligibleProviders($this->request)->pluck('id')->all();

    expect($eligible)->toEqualCanonicalizing([$leaf->id, $parent->id, $grandparent->id]);
});

test('netinka teikėjas su gretima kategorija ar kitame mieste', function () {
    $sibling = Category::factory()->create(['parent_id' => $this->request->category->parent_id, 'depth' => 3]);
    Marketplace::eligibleProvider($this->request, category: $sibling);

    $otherCity = ProviderProfile::factory()->create();
    $otherCity->categories()->attach($this->request->category_id);
    $otherCity->serviceAreas()->attach(City::factory()->create());

    expect($this->matcher->eligibleProviders($this->request)->count())->toBe(0);
});

test('„visa Lietuva" tinka be zonų', function () {
    $provider = ProviderProfile::factory()->wholeCountry()->create();
    $provider->categories()->attach($this->request->category_id);

    expect($this->matcher->isEligible($provider, $this->request))->toBeTrue();
});

test('neaktyvus, užblokuotas ar ištrintas teikėjas netinka', function (Closure $spoil) {
    $provider = Marketplace::eligibleProvider($this->request);
    $spoil($provider);

    expect($this->matcher->isEligible($provider->refresh(), $this->request))->toBeFalse();
})->with([
    'pending' => [fn (ProviderProfile $p) => $p->forceFill(['status' => 'pending'])->save()],
    'suspended' => [fn (ProviderProfile $p) => $p->forceFill(['status' => 'suspended'])->save()],
    'hidden' => [fn (ProviderProfile $p) => $p->forceFill(['status' => 'hidden'])->save()],
    'užblokuotas vartotojas' => [fn (ProviderProfile $p) => User::query()->whereKey($p->user_id)->update(['banned_at' => now()])],
    'ištrinta paskyra' => [fn (ProviderProfile $p) => User::query()->findOrFail($p->user_id)->delete()],
]);

test('atvirkštinė kryptis: teikėjo 2 lygio kategorija apima visus jos lapus', function () {
    $provider = Marketplace::eligibleProvider($this->request, category: $this->request->category->parent);
    $otherLeaf = Category::factory()->create(['parent_id' => $this->request->category->parent_id, 'depth' => 3]);
    $sameGroup = ServiceRequest::factory()->create(['category_id' => $otherLeaf->id, 'city_id' => $this->request->city_id]);
    ServiceRequest::factory()->create(['city_id' => $this->request->city_id]); // kita sritis

    $ids = $this->matcher->matchingRequests($provider)->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing([$this->request->id, $sameGroup->id])
        ->and($this->matcher->leafCategoryIds($provider))->toEqualCanonicalizing([$this->request->category_id, $otherLeaf->id]);
});

test('neaktyvus teikėjas srautui negauna nieko', function () {
    $provider = Marketplace::eligibleProvider($this->request);
    $provider->forceFill(['status' => 'suspended'])->save();

    expect($this->matcher->matchingRequests($provider)->count())->toBe(0);
});

test('job\'as praneša tik tinkamiems teikėjams', function () {
    Notification::fake();
    $eligible = Marketplace::eligibleProvider($this->request);
    $other = ProviderProfile::factory()->create();

    (new NotifyMatchingProviders($this->request))->handle($this->matcher);

    Notification::assertSentTo($eligible->user, NewMatchingRequest::class,
        fn (NewMatchingRequest $n) => $n->serviceRequest->is($this->request));
    Notification::assertNotSentTo($other->user, NewMatchingRequest::class);
});

test('job\'as nieko nesiunčia, jei užklausa jau nebe atvira', function () {
    Notification::fake();
    Marketplace::eligibleProvider($this->request);
    $this->request->forceFill(['status' => ServiceRequestStatus::Cancelled])->save();

    (new NotifyMatchingProviders($this->request))->handle($this->matcher);

    Notification::assertNothingSent();
});

test('pranešimas siunčiamas pagal notification_settings', function () {
    $provider = Marketplace::eligibleProvider($this->request);
    $user = $provider->user;
    $notification = new NewMatchingRequest($this->request);

    expect($notification->via($user))->toBe(['mail', 'database']);

    $user->forceFill(['notification_settings' => ['new_requests' => ['mail' => false]]])->save();
    expect($notification->via($user))->toBe(['database']);

    $user->forceFill(['notification_settings' => ['new_requests' => ['mail' => false, 'database' => false]]])->save();
    expect($notification->via($user))->toBe([]);
});

test('nepatvirtintu el. paštu laiškų nesiunčiam', function () {
    $user = User::factory()->provider()->unverified()->create();

    expect((new NewMatchingRequest($this->request))->via($user))->toBe(['database']);
});
