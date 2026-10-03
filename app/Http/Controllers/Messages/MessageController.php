<?php

namespace App\Http\Controllers\Messages;

use App\Actions\Messages\SendMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messages\SendMessageRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Žinutės siuntimas. Validacija ir teisės – SendMessageRequest, logika – SendMessage,
 * dažnio riba – throttle:messages maršrute (AppServiceProvider::configureRateLimiting()).
 */
class MessageController extends Controller
{
    public function store(SendMessageRequest $request, Conversation $conversation, SendMessage $send): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $send->handle($conversation, $user, $request->body());

        return to_route('conversations.show', $conversation);
    }
}
