<?php

namespace App\Observers;

use App\Enums\ReviewStatus;
use App\Jobs\RecalculateProviderRating;
use App\Models\Review;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Atsiliepimo pokyčiai → teikėjo reitingo perskaičiavimas eilėje (RecalculateProviderRating).
 *
 * Observer – klasė, kurios metodai (created, updated, deleted…) iškviečiami per Eloquent modelio įvykius,
 * kad ir kur kode modelis būtų išsaugotas: Action, Filament veiksmas, tinker. WordPress analogas –
 * add_action('save_post', …). Prijungtas prie modelio atributu #[ObservedBy] (Review.php).
 * https://laravel.com/docs/13.x/eloquent#observers
 *
 * ShouldHandleEventsAfterCommit – metodai vykdomi tik DB transakcijai pavykus: job'as negaus dar neįrašytų
 * (ar atšauktų) duomenų. Svarbu: masinis Review::query()->update([...]) įvykių NEKELIA – reikia keisti
 * per modelį (save()/update() ant konkretaus įrašo).
 */
class ReviewObserver implements ShouldHandleEventsAfterCommit
{
    /** Laukai, nuo kurių priklauso reitingas. Pvz. teikėjo atsakymas (provider_reply) jo nekeičia. */
    private const RATING_FIELDS = ['status', 'rating', 'provider_profile_id'];

    public function created(Review $review): void
    {
        // Laukiantis moderavimo atsiliepimas reitingo dar nekeičia
        if ($review->status === ReviewStatus::Published) {
            RecalculateProviderRating::dispatch($review->provider_profile_id);
        }
    }

    public function updated(Review $review): void
    {
        if (! $review->wasChanged(self::RATING_FIELDS)) {
            return;
        }

        RecalculateProviderRating::dispatch($review->provider_profile_id);

        // Jei atsiliepimas perkeltas kitam teikėjui (retas atvejis) – perskaičiuojam ir senąjį
        $previous = $review->getPrevious()['provider_profile_id'] ?? null;

        if (is_numeric($previous) && (int) $previous !== $review->provider_profile_id) {
            RecalculateProviderRating::dispatch((int) $previous);
        }
    }

    public function deleted(Review $review): void
    {
        RecalculateProviderRating::dispatch($review->provider_profile_id);
    }
}
