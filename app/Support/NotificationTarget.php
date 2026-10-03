<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\Payment;
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

        // --- Etapas 7: mokėjimai ir kreditai ---
        $billingUrl = self::billingUrl($type, $data);

        if ($billingUrl !== null) {
            return $billingUrl;
        }

        $requestId = isset($data['service_request_id']) && is_numeric($data['service_request_id']) ? (int) $data['service_request_id'] : null;
        $offerId = isset($data['offer_id']) && is_numeric($data['offer_id']) ? (int) $data['offer_id'] : null;

        $slug = $requestId === null ? null : ServiceRequest::withTrashed()->whereKey($requestId)->value('slug');

        if (! is_string($slug)) {
            // Etapo 6 tipai (NewMessage, NewReview) ir ištrinti įrašai – kol kas į pranešimų sąrašą
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
            'PaymentSucceeded', 'SubscriptionExpiring' => $paymentUuid !== null && Payment::query()->where('uuid', $paymentUuid)->exists()
                ? route('payments.show', ['payment' => $paymentUuid])
                : route('credits.index'),
            'LowCredits' => route('credits.index'),
            default => null,
        };
    }
}
