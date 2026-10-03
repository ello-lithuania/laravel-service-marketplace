<?php

use App\Enums\ServiceRequestStatus;
use App\Filament\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\NewMatchingRequest;
use App\Notifications\ServiceRequestPublished;
use App\Notifications\ServiceRequestRejected;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\Marketplace;

beforeEach(function () {
    Notification::fake();
    $this->actingAs(User::factory()->admin()->create());
});

test('sąrašas: numatytasis skirtukas – laukiančios moderavimo, filtras ir paieška', function () {
    $pending = ServiceRequest::factory()->pending()->create(['title' => 'Reikia nudažyti tvorą']);
    $open = ServiceRequest::factory()->create();

    $this->get('/admin/uzklausos')->assertOk()->assertSee('Užklausos');

    Livewire::test(ListServiceRequests::class)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$open])
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$pending, $open])
        ->filterTable('status', ServiceRequestStatus::Open->value)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$pending])
        ->resetTableFilters()
        ->searchTable('tvorą')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$open]);
});

test('peržiūroje rodomos automatinio moderavimo priežastys', function () {
    $pending = ServiceRequest::factory()->pending()->create(['description' => 'Skambinkite +370 612 34567, aptarsim.']);

    $this->get("/admin/uzklausos/{$pending->id}")
        ->assertOk()
        ->assertSee('Automatinis moderavimas')
        ->assertSee('Tekste yra telefono numeris');
});

test('patvirtinimas paskelbia užklausą, praneša klientui ir tinkamiems teikėjams', function () {
    $pending = Marketplace::openRequest();
    $pending->forceFill(['status' => ServiceRequestStatus::Pending, 'published_at' => null, 'expires_at' => null])->save();
    $provider = Marketplace::eligibleProvider($pending);

    Livewire::test(ViewServiceRequest::class, ['record' => $pending->getRouteKey()])
        ->callAction('approve')
        ->assertHasNoActionErrors()
        ->assertNotified('Užklausa paskelbta');

    expect($pending->refresh())
        ->status->toBe(ServiceRequestStatus::Open)
        ->expires_at->not->toBeNull();
    Notification::assertSentTo($pending->client, ServiceRequestPublished::class);
    Notification::assertSentTo($provider->user, NewMatchingRequest::class);
});

test('atmetimui reikia priežasties; klientas ją gauna', function () {
    $pending = ServiceRequest::factory()->pending()->create();

    Livewire::test(ListServiceRequests::class)
        ->callAction(TestAction::make('reject')->table($pending), ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    Livewire::test(ListServiceRequests::class)
        ->callAction(TestAction::make('reject')->table($pending), ['reason' => 'Nurodytas telefono numeris'])
        ->assertHasNoActionErrors();

    expect($pending->refresh())
        ->status->toBe(ServiceRequestStatus::Cancelled)
        ->cancellation_reason->toBe('Nurodytas telefono numeris');
    Notification::assertSentTo($pending->client, ServiceRequestRejected::class);
});

test('veiksmai rodomi tik tinkamoje būsenoje', function () {
    $open = ServiceRequest::factory()->create();
    $completed = ServiceRequest::factory()->completed()->create();

    Livewire::test(ViewServiceRequest::class, ['record' => $open->getRouteKey()])
        ->assertActionHidden('approve')
        ->assertActionHidden('reject')
        ->assertActionVisible('cancel');

    Livewire::test(ViewServiceRequest::class, ['record' => $completed->getRouteKey()])
        ->assertActionHidden('approve')
        ->assertActionHidden('cancel');
});

test('ne administratorius užklausų panelėje nemato', function () {
    $this->actingAs(User::factory()->create())->get('/admin/uzklausos')->assertForbidden();
});
