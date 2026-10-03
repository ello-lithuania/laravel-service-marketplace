<?php

namespace App\Services\Messaging;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Neperskaitytų žinučių skaičiavimas per conversation_user.last_read_message_id (docs/DB_SCHEMA.md → conversation_user).
 *
 * Neperskaityta = žinutė, kurios id > dalyvio last_read_message_id, ir ji ne paties vartotojo.
 * NULL last_read_message_id = dar nieko neperskaitė, todėl COALESCE(…, 0).
 * Indeksai: conversation_user(user_id, conversation_id) → messages(conversation_id) + PK intervalas.
 */
final class UnreadMessages
{
    /**
     * Visų vartotojo pokalbių neperskaitytos žinutės – vienas COUNT su JOIN (bendram Inertia prop'ui).
     */
    public function total(User $user): int
    {
        return Message::query()
            ->join('conversation_user', 'conversation_user.conversation_id', '=', 'messages.conversation_id')
            ->where('conversation_user.user_id', $user->id)
            ->tap(fn (Builder $query) => $this->onlyUnread($query, $user))
            ->count();
    }

    /**
     * Sąlyga žinučių užklausai, kurioje jau yra conversation_user lentelė (JOIN arba belongsToMany ryšys).
     *
     * @param  Builder<Message>  $query
     */
    public function onlyUnread(Builder $query, User $user): void
    {
        $query
            ->whereRaw('messages.id > COALESCE(conversation_user.last_read_message_id, 0)')
            // Sisteminės žinutės (sender_id NULL) irgi skaitomos; „<>" su NULL būtų false, todėl atskira sąlyga
            ->where(fn (Builder $inner) => $inner
                ->whereNull('messages.sender_id')
                ->orWhere('messages.sender_id', '<>', $user->id));
    }
}
