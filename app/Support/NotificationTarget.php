<?php

namespace App\Support;

use App\Enums\ProviderStatus;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceRequest;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Kur veda pranešimo paspaudimas. Skaičiuojama iš type + data (ID), o ne saugoma pranešime:
 * pasikeitus URL struktūrai, seni pranešimai (ir seed'ų sukurti) vis tiek ves teisingai.
 */
final class NotificationTarget
{
    public static function url(DatabaseNotification $notification): string
    {
        $data = $notification->data;
        $type = class_basename($notification->type);

        // --- Etapas 9c: administratorius pakeitė teikėjo profilio būseną ---
        $providerUrl = self::providerStatusUrl($type, $data);

        if ($providerUrl !== null) {
            return $providerUrl;
        }

        // --- Etapas 7: mokėjimai ir kreditai ---
        $billingUrl = self::billingUrl($type, $data);

        if ($billingUrl !== null) {
            return $billingUrl;
        }

        // --- Etapas 6 ---
        $etapas6 = self::etapas6Url($type, $data);

        if ($etapas6 !== null) {
            return $etapas6;
        }

        $requestId = isset($data['service_request_id']) && is_numeric($data['service_request_id']) ? (int) $data['service_request_id'] : null;
        $offerId = isset($data['offer_id']) && is_numeric($data['offer_id']) ? (int) $data['offer_id'] : null;

        $slug = $requestId === null ? null : ServiceRequest::withTrashed()->whereKey($requestId)->value('slug');

        if (! is_string($slug)) {
            // Ištrinti įrašai ir tipai be užklausos – į pranešimų sąrašą
            return route('notifications.index');
        }

        // Klientui naujas pasiūlymas atidaromas tiesiai (tai pažymi jį peržiūrėtu)
        if ($type === 'NewOffer' && $offerId !== null && Offer::query()->whereKey($offerId)->where('service_request_id', $requestId)->exists()) {
            return route('offers.show', ['serviceRequest' => $slug, 'offer' => $offerId]);
        }

        return route('service-requests.show', ['serviceRequest' => $slug]);
    }

    /**
     * Etapas 7: mokėjimas → jo puslapis (sąskaita, „Apmokėti"), mažai kreditų → kreditų puslapis.
     *
     * @param  array<array-key, mixed>  $data
     */
    private static function billingUrl(string $type, array $data): ?string
    {
        $paymentUuid = isset($data['payment_uuid']) && is_string($data['payment_uuid']) ? $data['payment_uuid'] : null;

        return match ($type) {
            'PaymentSucceeded', 'SubscriptionExpiring',
            // --- Etapas 9b: grąžintas mokėjimas → jo puslapis (kreditinė sąskaita) ---
            'PaymentRefunded' => $paymentUuid !== null && Payment::query()->where('uuid', $paymentUuid)->exists()
                ? route('payments.show', ['payment' => $paymentUuid])
                : route('credits.index'),
            'LowCredits' => route('credits.index'),
            default => null,
        };
    }

    // -------------------------------------------------------------------------
    // Etapas 6: žinutės, atsiliepimai, skundai
    // -------------------------------------------------------------------------

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function etapas6Url(string $type, array $data): ?string
    {
        $id = fn (string $key): ?int => isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : null;

        return match (true) {
            $type === 'NewMessage' && $id('conversation_id') !== null => route('conversations.show', $id('conversation_id')),
            // Teikėjui – jo atsiliepimų puslapis (ten galima atsakyti)
            $type === 'NewReview' => route('provider-reviews.index'),
            // Autoriui – viešas teikėjo profilis, kur matomas atsakymas
            $type === 'ReviewReplied' && $id('review_id') !== null => self::providerProfileUrl($id('review_id')),
            default => null,
        };
    }

    private static function providerProfileUrl(int $reviewId): ?string
    {
        $slug = Review::query()
            ->join('provider_profiles', 'provider_profiles.id', '=', 'reviews.provider_profile_id')
            ->where('reviews.id', $reviewId)
            ->value('provider_profiles.slug');

        return is_string($slug) ? route('providers.show', $slug).'#atsiliepimai' : null;
    }

    // -------------------------------------------------------------------------
    // Etapas 9c: teikėjo profilio būsena (ProviderStatusChanged, ProviderVerified)
    // -------------------------------------------------------------------------

    /**
     * Kur teikėjui verta eiti pagal DABARTINĘ profilio būseną (ne pagal pranešime įrašytą – ji galėjo pasikeisti):
     * aktyvus – viešas profilis, nebaigtas – vedlys, paslėptas ar užblokuotas – „Mano paskyra" (ten matosi būsena).
     */
    public static function providerAccountUrl(ProviderProfile $profile): string
    {
        return match ($profile->status) {
            ProviderStatus::Active => route('providers.show', $profile->slug),
            ProviderStatus::Pending => route('provider.wizard'),
            default => route('dashboard'),
        };
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function providerStatusUrl(string $type, array $data): ?string
    {
        if (! in_array($type, ['ProviderStatusChanged', 'ProviderVerified'], true)) {
            return null;
        }

        $id = isset($data['provider_profile_id']) && is_numeric($data['provider_profile_id']) ? (int) $data['provider_profile_id'] : null;
        $profile = $id === null ? null : ProviderProfile::query()->find($id, ['id', 'slug', 'status']);

        return $profile === null ? route('dashboard') : self::providerAccountUrl($profile);
    }
}
