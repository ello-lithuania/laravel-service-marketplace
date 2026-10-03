<?php

use App\Actions\Moderation\BanUser;
use App\Actions\Offers\AcceptOffer;
use App\Actions\Offers\SendOffer;
use App\Actions\Privacy\AnonymizeUser;
use App\Actions\ServiceRequests\ExpireServiceRequest;
use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Marketplace;

/**
 * Galutinė kreditų patikra (Etapas 8): po visų platformos veiksmų – pasiūlymų, priėmimo, pasibaigimo,
 * blokavimo ir BDAR ištrynimo – kiekvieno teikėjo credits_balance lygus jo ledger sumai, o balansas niekada
 * nebūna neigiamas (docs/DB_SCHEMA.md 2.8, docs/STATES.md 3 sk.).
 */
function offerFrom(ProviderProfile $provider, ServiceRequest $request): Offer
{
    return app(SendOffer::class)->handle($provider, $request, [
        'message' => 'Galiu atlikti darbus šią savaitę, kaina galutinė.',
        'price_cents' => 10000,
        'price_type' => 'fixed',
        'duration_text' => null,
        'start_date' => null,
    ]);
}

test('balansas = ledger suma po pasiūlymų, priėmimo, pasibaigimo, blokavimo ir anonimizavimo', function () {
    Notification::fake();
    Storage::fake('public');
    Storage::fake('local');
    $admin = User::factory()->admin()->create();

    // 1. Trys užklausos, du teikėjai, kiekvienas siunčia pasiūlymus
    $accepted = Marketplace::openRequest(cost: 2);
    $expiring = Marketplace::openRequest(cost: 3);
    $bannedClientRequest = Marketplace::openRequest(cost: 1);

    // Pradiniai kreditai – per ledger'į (factory withCredits() ledger eilutės nesukuria)
    $alpha = Marketplace::eligibleProvider($accepted, credits: 0);
    $beta = Marketplace::eligibleProvider($accepted, credits: 0);
    app(CreditLedger::class)->credit($alpha, 20, CreditTransactionType::Bonus);
    app(CreditLedger::class)->credit($beta, 20, CreditTransactionType::Bonus);
    foreach ([$expiring, $bannedClientRequest] as $request) {
        foreach ([$alpha, $beta] as $provider) {
            $provider->categories()->syncWithoutDetaching([$request->category_id]);
            $provider->serviceAreas()->syncWithoutDetaching([$request->city_id]);
        }
    }

    $winning = offerFrom($alpha, $accepted);
    offerFrom($beta, $accepted);
    offerFrom($alpha, $expiring);
    $viewed = offerFrom($beta, $expiring);
    offerFrom($alpha, $bannedClientRequest);
    offerFrom($beta, $bannedClientRequest);

    // 2. Priėmimas (kreditai negrąžinami), pasibaigimas (grąžinama tik už neatidarytą)
    app(AcceptOffer::class)->handle($winning);
    $viewed->forceFill(['viewed_at' => now()])->save();
    $expiring->forceFill(['expires_at' => now()->subMinute()])->save();
    app(ExpireServiceRequest::class)->handle($expiring->refresh());

    // 3. Kliento blokavimas – jo atvira užklausa atšaukiama, abiem teikėjams grąžinama
    app(BanUser::class)->handle($bannedClientRequest->client, $admin, 'Netikros užklausos');

    // 4. Teikėjas beta ištrina paskyrą
    app(AnonymizeUser::class)->handle($beta->user);

    foreach ([$alpha, $beta] as $provider) {
        $balance = ProviderProfile::withTrashed()->whereKey($provider->id)->value('credits_balance');
        $ledger = (int) CreditTransaction::query()->where('provider_profile_id', $provider->id)->sum('amount');

        expect($balance)->toBe($ledger)->toBeGreaterThanOrEqual(0);
    }

    // alpha: 20 − 2 (laimėjo) − 3 (pasibaigė, grąžinta: neatidarytas) + 3 − 1 + 1 (atšaukta blokavus) = 18
    expect(ProviderProfile::query()->whereKey($alpha->id)->value('credits_balance'))->toBe(18);
    // beta: 20 − 2 (atmestas priėmus kitą) − 3 (atidarytas – negrąžinama) − 1 + 1 (blokavimas) = 15
    expect(ProviderProfile::withTrashed()->whereKey($beta->id)->value('credits_balance'))->toBe(15);
});
