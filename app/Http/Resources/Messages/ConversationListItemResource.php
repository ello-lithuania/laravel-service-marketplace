<?php

namespace App\Http\Resources\Messages;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Pokalbis „Žinučių" sąraše: su kuo, dėl ko, paskutinė žinutė ir kiek neperskaitytų.
 * unread_count ateina iš withCount() (ConversationController::index), latestMessage – iš latestOfMany().
 *
 * @property Conversation $resource
 */
class ConversationListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $conversation = $this->resource;
        $last = $conversation->latestMessage;
        $viewer = $request->user();

        return [
            'id' => $conversation->id,
            'counterpart' => ConversationResource::counterpart($conversation, $viewer),
            'title' => $conversation->offer?->serviceRequest->title,
            'offer_status' => $conversation->offer === null ? null : [
                'value' => $conversation->offer->status->value,
                'label' => $conversation->offer->status->label(),
            ],
            'last_message' => $last === null ? null : [
                'excerpt' => $last->body !== '' ? Str::limit($last->body, 90) : __('messages.attachment_only'),
                'is_mine' => $viewer !== null && $last->sender_id === $viewer->id,
                'created_at' => $last->created_at?->toIso8601String(),
            ],
            'unread_count' => (int) ($conversation->getAttribute('unread_count') ?? 0),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
        ];
    }
}
