<?php

namespace App\Http\Resources\Messages;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pokalbio antraštė: dėl kurios užklausos ir pasiūlymo, su kuo kalbamasi.
 * Reikia užkrautų ryšių: offer.providerProfile, offer.serviceRequest.client (ConversationController).
 *
 * @property Conversation $resource
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $conversation = $this->resource;
        $offer = $conversation->offer;
        $serviceRequest = $offer?->serviceRequest;

        return [
            'id' => $conversation->id,
            'counterpart' => self::counterpart($conversation, $request->user()),
            'service_request' => $serviceRequest === null ? null : [
                'slug' => $serviceRequest->slug,
                'title' => $serviceRequest->title,
                'status' => ['value' => $serviceRequest->status->value, 'label' => $serviceRequest->status->label()],
            ],
            'offer' => $offer === null ? null : [
                'id' => $offer->id,
                'status' => ['value' => $offer->status->value, 'label' => $offer->status->label()],
                'price_cents' => $offer->price_cents,
                'price_type' => $offer->price_type->label(),
            ],
        ];
    }

    /**
     * Kita pokalbio pusė žiūrinčiojo akimis: klientui – teikėjas, teikėjui – klientas („Jonas P.").
     * Administratorius (ne dalyvis) mato abu.
     *
     * @return array{name: string, role: string}
     */
    public static function counterpart(Conversation $conversation, ?User $viewer): array
    {
        $provider = $conversation->offer?->providerProfile;
        $client = $conversation->offer?->serviceRequest->client;
        $providerName = $provider->display_name ?? '–';
        $clientName = $client->public_name ?? '–';

        return match (true) {
            $viewer !== null && $client !== null && $viewer->id === $client->id => ['name' => $providerName, 'role' => 'provider'],
            $viewer !== null && $provider !== null && $viewer->id === $provider->user_id => ['name' => $clientName, 'role' => 'client'],
            default => ['name' => "{$clientName} ↔ {$providerName}", 'role' => 'both'],
        };
    }
}
