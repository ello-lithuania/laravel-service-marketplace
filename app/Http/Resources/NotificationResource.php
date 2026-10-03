<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Pranešimas varpeliui ir pranešimų puslapiui. Nuoroda veda per notifications.open:
 * tas maršrutas pažymi pranešimą perskaitytu ir nukreipia į tikslą (NotificationTarget).
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data;

        return [
            'id' => $this->id,
            // „App\Notifications\NewOffer" → „NewOffer" (ikonai Vue pusėje)
            'type' => class_basename($this->type),
            'message' => is_string($data['message'] ?? null) ? $data['message'] : '',
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'url' => route('notifications.open', $this->id),
        ];
    }
}
