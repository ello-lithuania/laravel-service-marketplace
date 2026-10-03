<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * Žinutė (ir jos priedai) matoma tiems, kas mato pokalbį – dalyviams ir administratoriui.
 * Naudoja PrivateMediaController: prieš atiduodamas priedą jis klausia Gate::authorize('view', $message).
 */
class MessagePolicy
{
    public function __construct(private readonly ConversationPolicy $conversations) {}

    public function view(User $user, Message $message): bool
    {
        return $this->conversations->view($user, $message->loadMissing('conversation')->conversation);
    }
}
