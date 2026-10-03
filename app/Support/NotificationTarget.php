<?php

namespace App\Support;

use App\Models\Offer;
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
            default => null,
        };
    }
}
