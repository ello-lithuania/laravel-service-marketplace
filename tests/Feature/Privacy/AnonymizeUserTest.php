<?php

use App\Actions\Offers\SendOffer;
use App\Actions\Privacy\AnonymizeUser;
use App\Enums\OfferStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProviderStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\DataExportReady;
use App\Notifications\OfferDeclined;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\Marketplace;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

test('klientas ištrina paskyrą: asmens duomenys pašalinami, verslo įrašai lieka', function () {
    Notification::fake();
    $client = User::factory()->create(['phone' => '+37061234567']);
    $client->addMedia(UploadedFile::fake()->image('avataras.jpg', 300, 300))->toMediaCollection('avatar');
    $client->notify(new DataExportReady);
    $open = Marketplace::openRequest(cost: 2, attributes: ['client_id' => $client->id, 'address' => 'Gedimino pr. 1']);
    $provider = Marketplace::eligibleProvider($open, credits: 5);
    app(SendOffer::class)->handle($provider, $open, [
        'message' => 'Galiu atlikti darbus šią savaitę, kaina galutinė.',
        'price_cents' => 10000, 'price_type' => 'fixed', 'duration_text' => null, 'start_date' => null,
    ]);
    $review = Review::factory()->create(['author_id' => $client->id]);
    $payment = Payment::factory()->create(['user_id' => $client->id, 'status' => PaymentStatus::Paid]);
    $originalEmail = $client->email;

    $this->actingAs($client)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('home'))
        ->assertInertiaFlash('toast.message', __('privacy.delete.deleted'));

    $this->assertGuest();
    $deleted = User::withTrashed()->findOrFail($client->id);

    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->name)->toBe('Ištrintas vartotojas')
        ->and($deleted->public_name)->toBe('Ištrintas vartotojas')
        ->and($deleted->email)->toBe("deleted-{$client->id}@example.invalid")
        ->and($deleted->phone)->toBeNull()
        ->and(Media::query()->where('model_type', 'user')->where('model_id', $client->id)->exists())->toBeFalse()
        ->and($deleted->notifications()->count())->toBe(0)
        // Atvira užklausa atšaukta, teikėjui grąžinti kreditai, adresas pašalintas
        ->and($open->refresh()->status)->toBe(ServiceRequestStatus::Cancelled)
        ->and($open->address)->toBeNull()
        ->and($provider->refresh()->credits_balance)->toBe(5)
        // Atsiliepimas ir mokėjimas lieka (kitų istorija, buhalterija)
        ->and($review->fresh())->not->toBeNull()
        ->and($payment->fresh())->not->toBeNull();

    Notification::assertSentTo($provider->user, OfferDeclined::class);

    // Tuo pačiu el. paštu galima registruotis iš naujo
    expect(User::query()->where('email', $originalEmail)->exists())->toBeFalse();
});

test('ištrinto kliento atsiliepimas teikėjo profilyje rodomas kaip „Ištrintas vartotojas"', function () {
    $provider = ProviderProfile::factory()->create();
    $client = User::factory()->create(['first_name' => 'Jonas', 'last_name' => 'Petraitis']);
    Review::factory()->for($provider)->create(['author_id' => $client->id]);

    $this->actingAs($client)->delete(route('profile.destroy'), ['password' => 'password']);

    $this->get(route('providers.show', $provider->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('reviews.data.0.author_name', 'Ištrintas vartotojas'));
});

test('teikėjas ištrina paskyrą: profilis paslėptas ir anonimizuotas, pasiūlymai atšaukti, nuotraukos ištrintos', function () {
    $request = Marketplace::openRequest();
    $provider = Marketplace::eligibleProvider($request);
    $provider->forceFill(['description' => 'Asmeninis aprašymas', 'website' => 'https://jonas.lt'])->save();
    $provider->addMedia(UploadedFile::fake()->image('logo.jpg', 300, 300))->toMediaCollection('logo');
    $item = PortfolioItem::factory()->for($provider)->create();
    $item->addMedia(UploadedFile::fake()->image('darbas.jpg', 400, 300))->toMediaCollection('images');
    $offer = Offer::factory()->for($request)->for($provider)->create();
    $request->forceFill(['offers_count' => 1])->save();
    $subscription = Subscription::factory()->create([
        'provider_profile_id' => $provider->id,
        'status' => SubscriptionStatus::Active,
        'auto_renew' => true,
    ]);

    $this->actingAs($provider->user)->delete(route('profile.destroy'), ['password' => 'password']);

    $profile = ProviderProfile::withTrashed()->findOrFail($provider->id);

    expect($profile->trashed())->toBeTrue()
        ->and($profile->status)->toBe(ProviderStatus::Hidden)
        ->and($profile->display_name)->toBe('Ištrintas teikėjas')
        ->and($profile->description)->toBeNull()
        ->and($profile->website)->toBeNull()
        ->and($profile->categories()->count())->toBe(0)
        ->and($offer->refresh()->status)->toBe(OfferStatus::Withdrawn)
        ->and(PortfolioItem::query()->whereKey($item->id)->exists())->toBeFalse()
        ->and(Media::query()->count())->toBe(0)
        ->and($subscription->refresh())
        ->auto_renew->toBeFalse()
        ->status->toBe(SubscriptionStatus::Cancelled);

    $this->get(route('providers.show', $provider->slug))->assertNotFound();
});

test('įmonės kodas ir PVM kodas paliekami tik juridiniam asmeniui', function () {
    $company = ProviderProfile::factory()->company()->create(['company_code' => '300000000', 'vat_code' => 'LT100000000']);
    $individual = ProviderProfile::factory()->create(['company_code' => '123456', 'vat_code' => null]);

    app(AnonymizeUser::class)->handle($company->user);
    app(AnonymizeUser::class)->handle($individual->user);

    expect(ProviderProfile::withTrashed()->find($company->id)->company_code)->toBe('300000000')
        ->and(ProviderProfile::withTrashed()->find($individual->id)->company_code)->toBeNull();
});

test('neteisingas slaptažodis – niekas nekeičiama', function () {
    $user = User::factory()->create(['first_name' => 'Ona']);

    $this->actingAs($user)
        ->from(route('privacy.edit'))
        ->delete(route('profile.destroy'), ['password' => 'neteisingas'])
        ->assertSessionHasErrors('password');

    expect($user->fresh())->first_name->toBe('Ona')->trashed()->toBeFalse();
});

test('administratorius savo paskyros ištrinti negali', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    expect($admin->fresh()->trashed())->toBeFalse();
});

test('administratorius Filament\'e vykdo BDAR ištrynimo prašymą', function () {
    $this->actingAs(User::factory()->admin()->create());
    $user = User::factory()->create();
    $otherAdmin = User::factory()->admin()->create();

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->callAction('anonymize')
        ->assertNotified('Paskyra anonimizuota');

    expect(User::withTrashed()->find($user->id))
        ->trashed()->toBeTrue()
        ->email->toBe("deleted-{$user->id}@example.invalid");

    Livewire::test(ViewUser::class, ['record' => $otherAdmin->getRouteKey()])
        ->assertActionHidden('anonymize');
});

test('anonimizuojant ištrinami ir anksčiau paruošti BDAR archyvai', function () {
    $user = User::factory()->create();
    Storage::disk('local')->put("data-exports/{$user->id}/20261003-120000-abcdefabcdef.zip", 'zip');

    app(AnonymizeUser::class)->handle($user);

    expect(Storage::disk('local')->allFiles("data-exports/{$user->id}"))->toBe([]);
});

test('kategorijų lentelėje nieko nepakeičiama (tik pivot ryšiai)', function () {
    $provider = ProviderProfile::factory()->create();
    $category = Category::factory()->leaf()->create();
    $provider->categories()->attach($category);

    app(AnonymizeUser::class)->handle($provider->user);

    expect($category->fresh())->not->toBeNull();
});
