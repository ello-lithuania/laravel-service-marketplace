<?php

namespace App\Jobs;

use App\Enums\ReviewStatus;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Perskaičiuoja teikėjo reitingą: provider_profiles.rating_avg ir reviews_count (docs/DB_SCHEMA.md 2.10).
 * Paleidžia ReviewObserver, kai atsiliepimas sukuriamas, paskelbiamas, paslepiamas, pakeičiamas ar ištrinamas.
 *
 * Formulė – ta pati kaip seed'ų CounterSync: tik „published", ROUND(AVG(rating), 2), be atsiliepimų – 0.
 * Vienas UPDATE su koreliuotomis subužklausomis: skaičiavimas ir įrašymas – viena atominė operacija
 * (tarp SELECT ir UPDATE niekas negali „įsiterpti"), veikia ir MySQL, ir SQLite.
 *
 * ShouldBeUniqueUntilProcessing: kol to paties teikėjo job'as laukia eilėje, nauji neįdedami – jis vis tiek
 * perskaitys naujausius duomenis. Užraktas atleidžiamas, kai job'as PRADEDA darbą, todėl pakeitimas,
 * įvykęs skaičiavimo metu, įdės naują job'ą ir nepasimes (ShouldBeUnique laikytų užraktą iki pabaigos).
 * https://laravel.com/docs/13.x/queues#unique-jobs
 */
class RecalculateProviderRating implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Perduodam ID, o ne modelį: job'ui reikia tik skaičiaus, o profilis gali būti ir „ištrintas" (soft delete).
     */
    public function __construct(public int $providerProfileId) {}

    /**
     * Unikalumo raktas – vienas laukiantis job'as kiekvienam teikėjui.
     */
    public function uniqueId(): string
    {
        return (string) $this->providerProfileId;
    }

    public function handle(): void
    {
        $published = ReviewStatus::Published->value;

        DB::update(<<<'SQL'
            UPDATE provider_profiles SET
                reviews_count = (
                    SELECT COUNT(*) FROM reviews
                    WHERE reviews.provider_profile_id = provider_profiles.id AND reviews.status = ?
                ),
                rating_avg = COALESCE((
                    SELECT ROUND(AVG(reviews.rating), 2) FROM reviews
                    WHERE reviews.provider_profile_id = provider_profiles.id AND reviews.status = ?
                ), 0)
            WHERE provider_profiles.id = ?
            SQL, [$published, $published, $this->providerProfileId]);
    }
}
