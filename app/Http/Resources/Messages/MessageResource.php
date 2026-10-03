<?php

namespace App\Http\Resources\Messages;

use App\Models\Message;
use App\Support\PrivateMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Žinutė pokalbio puslapyje. Siuntėjo vardas imamas iš pokalbio (klientas arba teikėjas), todėl kiekvienai
 * žinutei siuntėjo atskirai neužkraunam: controller'is priskiria jau užkrautą pokalbį (setRelation).
 * Paslėptos (soft delete) žinutės tekstas nesiunčiamas – rodom tik „Žinutė paslėpta".
 *
 * @property Message $resource
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $message = $this->resource;
        $hidden = $message->trashed();

        return [
            'id' => $message->id,
            'body' => $hidden ? null : $message->body,
            'is_hidden' => $hidden,
            'is_system' => $message->isSystem(),
            'is_mine' => $message->sender_id !== null && $message->sender_id === $request->user()?->id,
            'sender_name' => $this->senderName(),
            'created_at' => $message->created_at?->toIso8601String(),
            // Paslėptos žinutės priedai irgi nerodomi (PrivateMediaController jų ir neatiduotų)
            'attachments' => $hidden ? [] : array_values($message->getMedia('attachments')
                ->map(fn (Media $media): array => PrivateMedia::toArray($media))
                ->all()),
        ];
    }

    private function senderName(): string
    {
        $message = $this->resource;
        $offer = $message->conversation->offer;

        return match (true) {
            $message->isSystem() => __('messages.system_sender'),
            $offer !== null && $message->sender_id === $offer->providerProfile->user_id => $offer->providerProfile->display_name,
            $offer !== null && $message->sender_id === $offer->serviceRequest->client_id => $offer->serviceRequest->client->public_name,
            default => '–',
        };
    }
}
