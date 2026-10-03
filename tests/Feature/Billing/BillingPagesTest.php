<?php

use App\Enums\CreditTransactionType;
use App\Models\Category;
use App\Models\CreditPackage;
use App\Models\CreditTransaction;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Billing;

beforeEach(function () {
    config(['payments.default' => 'fake']);
});

test('kainų puslapis: aktyvūs paketai ir planai pagal eilę, kreditų kaina, SEO', function () {
    CreditPackage::factory()->create(['name' => 'Didelis', 'credits' => 120, 'bonus_credits' => 15, 'price_cents' => 8990, 'sort_order' => 2]);
    CreditPackage::factory()->create(['name' => 'Mažas', 'credits' => 10, 'bonus_credits' => 0, 'price_cents' => 990, 'sort_order' => 1]);
    CreditPackage::factory()->create(['name' => 'Senas', 'is_active' => false]);
    SubscriptionPlan::factory()->create(['name' => 'Startas', 'sort_order' => 1, 'features' => ['max_categories' => 10, 'badge' => false]]);
    SubscriptionPlan::factory()->create(['name' => 'Paslėptas', 'is_active' => false]);
    Category::factory()->leaf()->create(['offer_cost_credits' => 1]);
    Category::factory()->leaf()->create(['offer_cost_credits' => 4]);

    $this->get('/kainos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Pricing')
            ->has('packages', 2)
            ->where('packages.0.name', 'Mažas')
            ->where('packages.1.name', 'Didelis')
            ->where('packages.1.total_credits', 135)
            ->where('packages.1.price_per_credit_cents', 67)
            ->has('plans', 1)
            ->where('plans.0.name', 'Startas')
            ->where('plans.0.features', ['25 kreditų kas mėn.', 'Iki 10 paslaugų kategorijų'])
            ->where('offerCost', ['min' => 1, 'max' => 4])
            ->where('viewer.role', null)
            ->where('viewer.can_purchase', false)
            ->where('seo.title', 'Kainos teikėjams: kreditai ir prenumeratos')
            ->where('seo.canonical', route('pricing'))
            ->etc());
});

test('kainų puslapis: teikėjas gali pirkti ir mato savo planą, klientas – ne', function () {
    $user = Billing::provider();
    $plan = SubscriptionPlan::factory()->create();
    Subscription::factory()->for($user->providerProfile)->create(['subscription_plan_id' => $plan->id]);

    $this->actingAs($user)->get('/kainos')->assertInertia(fn (Assert $page) => $page
        ->where('viewer.role', 'provider')
        ->where('viewer.can_purchase', true)
        ->where('viewer.current_plan_id', $plan->id)
        ->etc());

    $this->actingAs(User::factory()->create())->get('/kainos')->assertInertia(fn (Assert $page) => $page
        ->where('viewer.role', 'client')
        ->where('viewer.can_purchase', false)
        ->etc());
});

test('kreditų puslapis: balansas, paketai, prenumerata, laukiantis pratęsimas ir tik savo istorija', function () {
    $user = Billing::provider();
    $provider = $user->providerProfile;
    $ledger = app(CreditLedger::class);
    $ledger->credit($provider, 30, CreditTransactionType::Purchase, null, 'Kreditų paketas „30 kreditų"');
    $ledger->debit($provider, 2, CreditTransactionType::Offer, null, 'Pasiūlymas: Plytelių klijavimas');
    $ledger->credit(Billing::provider()->providerProfile, 10, CreditTransactionType::Bonus);
    CreditPackage::factory()->create();
    $plan = SubscriptionPlan::factory()->create(['name' => 'Startas']);
    $subscription = Subscription::factory()->for($provider)->create(['subscription_plan_id' => $plan->id]);
    $renewal = Payment::factory()->fake()->pending()->forPlan($plan, $subscription)->for($user)->create();

    $this->actingAs($user)->get('/teikejas/kreditai')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/Credits')
            ->where('balance', 28)
            ->where('lowCreditsThreshold', 3)
            ->has('packages', 1)
            ->where('subscription.plan.name', 'Startas')
            ->where('subscription.status.value', 'active')
            ->where('subscription.can_cancel', true)
            ->where('scheduledSubscription', null)
            ->where('renewalPayment.uuid', $renewal->uuid)
            ->where('renewalPayment.can.pay', true)
            ->has('transactions.data', 2)
            ->where('transactions.data.0.amount', -2)
            ->where('transactions.data.0.type', ['value' => 'offer', 'label' => 'Pasiūlymas'])
            ->where('transactions.data.0.balance_after', 28)
            ->where('transactions.data.1.description', 'Kreditų paketas „30 kreditų"')
            ->has('transactions.meta'));
});

test('kreditų istorija puslapiuojama po 20', function () {
    $user = Billing::provider();
    CreditTransaction::factory()->count(25)->for($user->providerProfile)->create();

    $this->actingAs($user)->get('/teikejas/kreditai?page=2')
        ->assertInertia(fn (Assert $page) => $page->has('transactions.data', 5)->where('transactions.meta.current_page', 2)->etc());
});

test('mokėjimų puslapis: tik savi mokėjimai, sąskaitos ir „Apmokėti" teisės', function () {
    $user = Billing::provider();
    $package = CreditPackage::factory()->create(['name' => '10 kreditų']);
    $paid = Billing::complete(Payment::factory()->fake()->pending()->forPackage($package)->for($user)->create());
    $pending = Payment::factory()->fake()->pending()->forPackage($package)->for($user)->create();
    Payment::factory()->create();

    $this->actingAs($user)->get('/teikejas/mokejimai')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/Payments')
            ->has('payments.data', 2)
            ->where('payments.data.0.uuid', $pending->uuid)
            ->where('payments.data.0.can', ['pay' => true, 'download_invoice' => false])
            ->where('payments.data.1.uuid', $paid->uuid)
            ->where('payments.data.1.description', 'Kreditų paketas „10 kreditų"')
            ->where('payments.data.1.invoice_number', $paid->invoice_number)
            ->where('payments.data.1.can', ['pay' => false, 'download_invoice' => true])
            ->etc());
});

test('mokėjimo puslapį mato tik savininkas; „Apmokėti" nukreipia į tiekėją', function () {
    $user = Billing::provider();
    $payment = Payment::factory()->fake()->pending()->for($user)->create();

    $this->actingAs($user)->get("/mokejimai/{$payment->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/PaymentShow')
            ->where('payment.status.value', 'pending')
            ->etc());

    $this->actingAs($user)->withHeader('X-Inertia', 'true')->post("/mokejimai/{$payment->uuid}/apmoketi")
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('payments.fake.show', $payment));
    $this->flushHeaders();

    $other = Billing::provider();
    $this->actingAs($other)->get("/mokejimai/{$payment->uuid}")->assertForbidden();
    $this->actingAs($other)->post("/mokejimai/{$payment->uuid}/apmoketi")->assertForbidden();
    $this->actingAs($other)->get("/mokejimai/{$payment->uuid}/atsaukti")->assertForbidden();
});

test('apmokėto ar seed\'ų „manual" mokėjimo apmokėti dar kartą negalima', function () {
    $user = Billing::provider();
    $paid = Payment::factory()->fake()->for($user)->create();
    $manual = Payment::factory()->pending()->for($user)->create(['gateway' => 'manual']);

    $this->actingAs($user)->post("/mokejimai/{$paid->uuid}/apmoketi")->assertForbidden();
    $this->actingAs($user)->post("/mokejimai/{$manual->uuid}/apmoketi")->assertForbidden();
});

test('kreditų ir mokėjimų puslapiai – tik teikėjams su profiliu', function () {
    $this->get('/teikejas/kreditai')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/teikejas/kreditai')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/teikejas/mokejimai')->assertForbidden();
    $this->actingAs(User::factory()->provider()->create())->get('/teikejas/kreditai')->assertRedirect(route('provider.details.edit'));
});

test('mokėjimo URL – tik uuid (ne skaitinis ID)', function () {
    $user = Billing::provider();
    $payment = Payment::factory()->for($user)->create();

    $this->actingAs($user)->get("/mokejimai/{$payment->id}")->assertNotFound();
});
