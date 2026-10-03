<?php

use App\Enums\PaymentStatus;
use App\Models\CreditTransaction;
use App\Models\Payment;
use App\Models\ProviderProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Billing;

/*
 * Etapo 7 kodas ant demo duomenų (docs/SEEDING.md): seed'ų prenumeratos ir mokėjimai turi būti suderinti su
 * nauja logika – Scheduler negali antrą kartą suteikti kreditų, o naujų sąskaitų numeriai – susidurti su senais.
 */

beforeEach(function () {
    config(['seeding.demo' => true, 'seeding.scale' => 0.01, 'payments.default' => 'fake']);
    $this->seed(DatabaseSeeder::class);
    Notification::fake();
});

test('seed\'ų prenumeratų mokėjimai susieti su prenumerata, kreditai pažymėti suteiktais', function () {
    expect(DB::table('payments')->where('purchasable_type', 'subscription_plan')->whereNull('subscription_id')->count())->toBe(0)
        ->and(DB::table('payments')->where('purchasable_type', 'credit_package')->whereNotNull('subscription_id')->count())->toBe(0)
        ->and(DB::table('subscriptions')->whereColumn('credits_granted_until', '!=', 'ends_at')->count())->toBe(0);
});

test('Scheduler komandos ant seed\'ų nesuteikia papildomų kreditų ir nesugadina ledger\'io', function () {
    $before = CreditTransaction::query()->count();

    $this->artisan('subscriptions:grant-credits')->assertSuccessful();
    $this->artisan('subscriptions:renew')->assertSuccessful();

    expect(CreditTransaction::query()->count())->toBe($before)
        // Balansas = ledger suma (docs/DB_SCHEMA.md 2.8)
        ->and(DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS aggregate FROM provider_profiles pp
            WHERE pp.credits_balance <> COALESCE((SELECT SUM(amount) FROM credit_transactions ct WHERE ct.provider_profile_id = pp.id), 0)
        SQL)->aggregate)->toBe(0)
        // Priminimai sukūrė pratęsimo mokėjimus tik automatiškai pratęsiamoms prenumeratoms
        ->and(Payment::query()->where('status', PaymentStatus::Pending)->whereNotNull('subscription_id')
            ->whereHas('subscription', fn ($query) => $query->where('auto_renew', false))->count())->toBe(0);
});

test('naujas apmokėjimas gauna numerį, tęsiantį seed\'ų numeraciją', function () {
    $year = now('Europe/Vilnius')->year;
    $max = (string) DB::table('payments')->where('invoice_number', 'like', "SF-{$year}-%")->max('invoice_number');
    $provider = ProviderProfile::query()->where('status', 'active')->firstOrFail();
    $payment = Payment::factory()->fake()->pending()->for($provider->user)->create();

    $paid = Billing::complete($payment);

    expect($paid->invoice_number)->toBe(sprintf('SF-%d-%06d', $year, ((int) substr($max, -6)) + 1));
});
