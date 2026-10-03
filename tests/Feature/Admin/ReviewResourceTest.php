<?php

use App\Enums\ReviewStatus;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Pages\ViewReview;
use App\Models\Complaint;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use App\Notifications\NewReview;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Filament: atsiliepimų moderavimas (Etapas 6). Paskelbus / paslėpus reitingas persiskaičiuoja (observer → job).
 */
beforeEach(function () {
    Notification::fake();
    $this->actingAs(User::factory()->admin()->create());
    $this->provider = ProviderProfile::factory()->create();
});

function pendingReview(ProviderProfile $provider, int $rating = 5): Review
{
    return Review::factory()->for($provider)->create(['rating' => $rating, 'status' => ReviewStatus::Pending, 'published_at' => null]);
}

test('sąrašas: numatytai – laukiantys moderavimo; filtrai ir paieška', function () {
    $pending = pendingReview($this->provider);
    $published = Review::factory()->verified()->create(['comment' => 'Labai tvarkingas meistras']);
    $reported = Review::factory()->create();
    Complaint::factory()->create(['reportable_id' => $reported->id]);

    $this->get('/admin/atsiliepimai')->assertOk()->assertSee('Atsiliepimai');

    Livewire::test(ListReviews::class)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$published, $reported])
        ->set('activeTab', 'reported')
        ->assertCanSeeTableRecords([$reported])
        ->assertCanNotSeeTableRecords([$pending, $published])
        ->set('activeTab', 'all')
        ->filterTable('verified', true)
        ->assertCanSeeTableRecords([$published])
        ->assertCanNotSeeTableRecords([$pending, $reported])
        ->resetTableFilters()
        ->searchTable('tvarkingas')
        ->assertCanSeeTableRecords([$published])
        ->assertCanNotSeeTableRecords([$pending]);
});

test('paskelbus pakvietimo atsiliepimą – reitingas perskaičiuojamas, teikėjui pranešama', function () {
    Review::factory()->for($this->provider)->create(['rating' => 3]);
    $pending = pendingReview($this->provider, 5);
    expect((float) $this->provider->fresh()->rating_avg)->toBe(3.0);

    Livewire::test(ViewReview::class, ['record' => $pending->getRouteKey()])
        ->callAction('publish')
        ->assertHasNoActionErrors()
        ->assertNotified('Atsiliepimas paskelbtas');

    expect($pending->refresh())
        ->status->toBe(ReviewStatus::Published)
        ->published_at->not->toBeNull()
        ->and($this->provider->fresh())
        ->rating_avg->toEqual('4.00')
        ->reviews_count->toBe(2);
    Notification::assertSentTo($this->provider->user, NewReview::class);
});

test('paslėpus – reitingas perskaičiuojamas, pakartotinai paskelbus pranešimo nebėra', function () {
    $review = Review::factory()->for($this->provider)->create(['rating' => 1]);
    Review::factory()->for($this->provider)->create(['rating' => 5]);

    Livewire::test(ListReviews::class)
        ->set('activeTab', 'all')
        ->callAction(TestAction::make('hide')->table($review))
        ->assertNotified('Atsiliepimas paslėptas');

    expect($review->refresh()->status)->toBe(ReviewStatus::Hidden)
        ->and((float) $this->provider->fresh()->rating_avg)->toBe(5.0)
        ->and($this->provider->fresh()->reviews_count)->toBe(1);

    Livewire::test(ListReviews::class)
        ->set('activeTab', 'all')
        ->callAction(TestAction::make('publish')->table($review));

    expect((float) $this->provider->fresh()->rating_avg)->toBe(3.0);
    // Jau anksčiau skelbtas (published_at buvo) – teikėjas antrą kartą nepranešamas
    Notification::assertNotSentTo($this->provider->user, NewReview::class);
});

test('masinis paslėpimas keičia kiekvieną įrašą per modelį – reitingas persiskaičiuoja', function () {
    $reviews = Review::factory()->for($this->provider)->count(3)->create(['rating' => 4]);
    expect($this->provider->fresh()->reviews_count)->toBe(3);

    Livewire::test(ListReviews::class)
        ->set('activeTab', 'all')
        ->selectTableRecords($reviews->modelKeys())
        ->callAction(TestAction::make('hideSelected')->table()->bulk());

    expect(Review::query()->where('status', ReviewStatus::Hidden)->count())->toBe(3)
        ->and($this->provider->fresh()->reviews_count)->toBe(0)
        ->and((float) $this->provider->fresh()->rating_avg)->toBe(0.0);
});

test('peržiūroje matomas autorius, teikėjas ir tipas', function () {
    $review = Review::factory()->verified()->create();

    $this->get("/admin/atsiliepimai/{$review->id}")
        ->assertOk()
        ->assertSee($review->comment)
        ->assertSee('Patvirtintas (darbas per platformą)');
});

test('ne administratorius atsiliepimų moderavimo nepasiekia', function () {
    $this->actingAs(User::factory()->create())->get('/admin/atsiliepimai')->assertForbidden();
});
