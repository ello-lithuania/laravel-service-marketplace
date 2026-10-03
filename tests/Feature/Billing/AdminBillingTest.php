<?php

use App\Enums\CreditTransactionType;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\CreditTransactions\Pages\ListCreditTransactions;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\Widgets\PaymentStats;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Models\CreditTransaction;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Support\Billing;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('mokėjimų sąrašas: filtrai pagal būseną ir tiekėją, paieška pagal sąskaitą', function () {
    $paid = Payment::factory()->create(['invoice_number' => 'SF-2026-000777', 'paid_at' => now()]);
    $failed = Payment::factory()->failed()->create(['gateway' => PaymentGateway::Fake]);

    $this->get('/admin/mokejimai')->assertOk()->assertSee('Mokėjimai');

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$paid, $failed])
        ->filterTable('status', PaymentStatus::Failed->value)
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$paid])
        ->resetTableFilters()
        ->filterTable('gateway', PaymentGateway::Paysera->value)
        ->assertCanSeeTableRecords([$paid])
        ->assertCanNotSeeTableRecords([$failed])
        ->resetTableFilters()
        ->searchTable('SF-2026-000777')
        ->assertCanSeeTableRecords([$paid])
        ->assertCanNotSeeTableRecords([$failed]);
});

test('mokėjimų sąrašas: datos intervalo filtras', function () {
    $old = Payment::factory()->create(['created_at' => '2026-01-15 10:00']);
    $new = Payment::factory()->create(['created_at' => '2026-03-15 10:00']);

    Livewire::test(ListPayments::class)
        ->filterTable('created_at', ['from' => '2026-03-01', 'until' => '2026-03-31'])
        ->assertCanSeeTableRecords([$new])
        ->assertCanNotSeeTableRecords([$old]);
});

test('mokėjimo peržiūra ir sąskaitos PDF veiksmas tik apmokėtam', function () {
    $paid = Payment::factory()->create(['invoice_number' => 'SF-2026-000778']);
    $pending = Payment::factory()->pending()->create();

    $this->get("/admin/mokejimai/{$paid->uuid}")->assertOk()->assertSee('SF-2026-000778');

    Livewire::test(ViewPayment::class, ['record' => $paid->getRouteKey()])
        ->assertActionVisible('invoice')
        ->assertActionHasUrl('invoice', route('payments.invoice', $paid));

    Livewire::test(ViewPayment::class, ['record' => $pending->getRouteKey()])
        ->assertActionHidden('invoice');
});

test('pajamų suvestinė skaičiuoja tik apmokėtus šio mėnesio mokėjimus', function () {
    Payment::factory()->create(['amount_cents' => 2690, 'paid_at' => now()]);
    Payment::factory()->create(['amount_cents' => 1900, 'paid_at' => now()]);
    Payment::factory()->pending()->create(['amount_cents' => 9999]);

    Livewire::test(PaymentStats::class)
        ->assertSee('45,90 €')
        ->assertSee('Apmokėtų mokėjimų: 2');
});

test('kreditų operacijos – tik skaityti, filtras pagal tipą', function () {
    $purchase = CreditTransaction::factory()->create(['type' => CreditTransactionType::Purchase, 'amount' => 30]);
    $offer = CreditTransaction::factory()->create(['type' => CreditTransactionType::Offer, 'amount' => -2]);

    $this->get('/admin/kreditu-operacijos')->assertOk()->assertSee('Koreguoti kreditus');

    Livewire::test(ListCreditTransactions::class)
        ->assertCanSeeTableRecords([$purchase, $offer])
        ->filterTable('type', CreditTransactionType::Offer->value)
        ->assertCanSeeTableRecords([$offer])
        ->assertCanNotSeeTableRecords([$purchase]);

    // Kūrimo ir redagavimo puslapių nėra
    $this->get('/admin/kreditu-operacijos/create')->assertNotFound();
});

test('„Koreguoti kreditus": + ir − per ledger\'į su priežastimi ir administratoriumi kaip šaltiniu', function () {
    $provider = ProviderProfile::factory()->withCredits(3)->create(['display_name' => 'Petras Meistras']);

    Livewire::test(ListCreditTransactions::class)
        ->callAction('adjustCredits', ['provider_profile_id' => $provider->id, 'amount' => 10, 'reason' => 'Kompensacija už klaidą'])
        ->assertHasNoActionErrors()
        ->assertNotified('Kreditai pakoreguoti. Naujas balansas: 13');

    Livewire::test(ListCreditTransactions::class)
        ->callAction('adjustCredits', ['provider_profile_id' => $provider->id, 'amount' => -4, 'reason' => 'Klaidingai suteikta'])
        ->assertNotified('Kreditai pakoreguoti. Naujas balansas: 9');

    $last = CreditTransaction::query()->latest('id')->firstOrFail();

    expect($provider->refresh()->credits_balance)->toBe(9)
        ->and($last->type)->toBe(CreditTransactionType::AdminAdjustment)
        ->and($last->amount)->toBe(-4)
        ->and($last->source_type)->toBe('user')
        ->and($last->source_id)->toBe($this->admin->id)
        ->and($last->description)->toBe("Koregavimas: Klaidingai suteikta ({$this->admin->name})");
});

test('koreguojant balansas negali tapti neigiamas, 0 neleidžiamas', function () {
    $provider = ProviderProfile::factory()->withCredits(3)->create();

    Livewire::test(ListCreditTransactions::class)
        ->callAction('adjustCredits', ['provider_profile_id' => $provider->id, 'amount' => -5, 'reason' => 'Bandymas'])
        ->assertNotified('Nepakanka kreditų: teikėjas turi 3, o atimti norite 5.');

    Livewire::test(ListCreditTransactions::class)
        ->callAction('adjustCredits', ['provider_profile_id' => $provider->id, 'amount' => 0, 'reason' => 'Nulis'])
        ->assertHasActionErrors(['amount']);

    expect($provider->refresh()->credits_balance)->toBe(3)
        ->and(CreditTransaction::query()->count())->toBe(0);
});

test('prenumeratos: skirtukas „Galiojančios", peržiūra ir atšaukimas', function () {
    $active = Subscription::factory()->create();
    $expired = Subscription::factory()->expired()->create();

    $this->get('/admin/prenumeratos')->assertOk();

    Livewire::test(ListSubscriptions::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$expired])
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$active, $expired])
        ->assertActionHidden(TestAction::make('cancel')->table($expired))
        ->callAction(TestAction::make('cancel')->table($active))
        ->assertNotified('Prenumerata atšaukta');

    expect($active->refresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($active->auto_renew)->toBeFalse();

    $this->get("/admin/prenumeratos/{$active->id}")->assertOk()->assertSee('Kreditai suteikti iki');
    Livewire::test(ViewSubscription::class, ['record' => $active->id])->assertActionHidden('cancel');
});

test('finansų puslapiai – tik administratoriui', function () {
    $this->actingAs(Billing::provider());

    $this->get('/admin/mokejimai')->assertForbidden();
    $this->get('/admin/kreditu-operacijos')->assertForbidden();
    $this->get('/admin/prenumeratos')->assertForbidden();
});

test('Policy: administratorius mato visus mokėjimus, teikėjas – tik savo; kurti ir redaguoti negali niekas', function () {
    $provider = Billing::provider();
    $own = Payment::factory()->for($provider)->create();
    $foreign = Payment::factory()->create();

    expect($this->admin->can('viewAny', Payment::class))->toBeTrue()
        ->and($this->admin->can('view', $foreign))->toBeTrue()
        ->and($this->admin->can('create', Payment::class))->toBeFalse()
        ->and($this->admin->can('update', $foreign))->toBeFalse()
        ->and($provider->can('viewAny', Payment::class))->toBeFalse()
        ->and($provider->can('view', $own))->toBeTrue()
        ->and($provider->can('view', $foreign))->toBeFalse()
        ->and($this->admin->can('update', CreditTransaction::factory()->create()))->toBeFalse();
});

test('ledger suma visada lygi balansui po pirkimų, prenumeratų ir koregavimų', function () {
    $provider = ProviderProfile::factory()->create();
    $ledger = app(CreditLedger::class);
    $ledger->credit($provider, 30, CreditTransactionType::Purchase);
    $ledger->credit($provider, 60, CreditTransactionType::Subscription);
    $ledger->debit($provider, 7, CreditTransactionType::AdminAdjustment);

    expect((int) CreditTransaction::query()->where('provider_profile_id', $provider->id)->sum('amount'))
        ->toBe($provider->refresh()->credits_balance)
        ->toBe(83);
});
