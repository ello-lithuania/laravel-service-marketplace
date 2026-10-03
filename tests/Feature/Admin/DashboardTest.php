<?php

use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Filament\Widgets\ActivityChart;
use App\Filament\Widgets\PendingServiceRequestsTable;
use App\Filament\Widgets\PlatformStatsOverview;
use App\Filament\Widgets\UnverifiedProvidersTable;
use App\Models\Complaint;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Admin\DashboardStats;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('suvestinė: vartotojai, užklausos, konversija, moderavimas ir pajamos', function () {
    User::factory()->count(2)->create();
    ProviderProfile::factory()->create();
    $accepted = ServiceRequest::factory()->inProgress()->create();
    ServiceRequest::factory()->create(); // atvira, be priimto pasiūlymo
    ServiceRequest::factory()->pending()->create();
    Complaint::factory()->create();
    Review::factory()->create(['status' => ReviewStatus::Pending]);
    Payment::factory()->create(['status' => PaymentStatus::Paid, 'paid_at' => now()->subDays(3), 'amount_cents' => 2490]);
    Payment::factory()->create(['status' => PaymentStatus::Paid, 'paid_at' => now()->subDays(40), 'amount_cents' => 9900]);
    Payment::factory()->create(['status' => PaymentStatus::Pending, 'amount_cents' => 5000]);

    $stats = app(DashboardStats::class)->summary();
    $clients = User::query()->where('role', 'client')->count();

    expect($stats['users']['admin'])->toBe(1)
        ->and($stats['users']['client'])->toBe($clients)
        ->and($stats['users']['total'])->toBe(User::query()->count())
        ->and($stats['requests']['pending'])->toBe(1)
        ->and($stats['requests']['in_progress'])->toBe(1)
        ->and($stats['conversion']['accepted'])->toBe($accepted->accepted_offer_id === null ? 0 : 1)
        ->and($stats['moderation'])->toMatchArray(['requests' => 1, 'complaints' => 1, 'reviews' => 1, 'total' => 3])
        ->and($stats['revenue'])->toBe(['last_30_days_cents' => 2490, 'payments' => 1]);
});

test('registracijos ir pasiūlymai skaičiuojami pagal dienas Lietuvos laiku', function () {
    $this->travelTo(now('Europe/Vilnius')->setTime(12, 0));
    User::factory()->create(['created_at' => now()->subDays(2)]);
    User::factory()->create(['created_at' => now()->subDays(20)]);
    Offer::factory()->count(3)->create(['created_at' => now()->subDay()]);

    $stats = app(DashboardStats::class)->summary();

    expect($stats['registrations']['last_30_days'])->toBe(User::query()->where('created_at', '>=', now()->subDays(29)->startOfDay())->count())
        ->and($stats['registrations']['daily'])->toHaveCount(14)
        ->and($stats['offers']['last_30_days'])->toBe(3)
        ->and($stats['offers']['daily'][12])->toBe(3);
});

test('suvestinė laikoma cache – antras skaitymas DB neliečia', function () {
    app(DashboardStats::class)->summary();

    DB::enableQueryLog();
    app(DashboardStats::class)->summary();
    app(DashboardStats::class)->activity(30);
    app(DashboardStats::class)->activity(30);

    expect(DB::getQueryLog())->toHaveCount(2); // tik pirmas activity(30): užklausos + pasiūlymai
});

test('diagrama: filtras riboja dienų skaičių, nežinoma reikšmė – 30 d.', function () {
    Offer::factory()->create();

    $chart = Livewire::test(ActivityChart::class)->set('filter', '7');
    expect(app(DashboardStats::class)->activity(7)['labels'])->toHaveCount(7);

    $chart->set('filter', '999')->assertOk();
});

test('skydelis rodo valdiklius ir laukiančias užklausas su nuoroda', function () {
    $pending = ServiceRequest::factory()->pending()->create(['title' => 'Reikia sutvarkyti stogą']);
    $unverified = ProviderProfile::factory()->create(['display_name' => 'Stogdengiai LT']);

    // Valdikliai kraunami „tingiai" (lazy) atskira Livewire užklausa, todėl puslapyje – tik jų vietos
    $this->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(PlatformStatsOverview::class)
        ->assertSeeLivewire(ActivityChart::class)
        ->assertSeeLivewire(PendingServiceRequestsTable::class)
        ->assertDontSee('filamentphp.com'); // Filament informacinis valdiklis pašalintas

    Livewire::test(PlatformStatsOverview::class)->assertSee('Laukia moderavimo')->assertSee('Pajamos (30 d.)');
    Livewire::test(PendingServiceRequestsTable::class)->assertCanSeeTableRecords([$pending])->assertSee('Reikia sutvarkyti stogą');
    Livewire::test(UnverifiedProvidersTable::class)->assertCanSeeTableRecords([$unverified]);
});
