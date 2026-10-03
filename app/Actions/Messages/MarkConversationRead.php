<?php

namespace App\Actions\Messages;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Support\Facades\DB;

/**
 * Pažymi pokalbį perskaitytu: conversation_user.last_read_message_id = paskutinės žinutės id.
 *
 * Neperskaitytos = „id > last_read_message_id" (docs/DB_SCHEMA.md → conversation_user), todėl vienas UPDATE
 * pažymi visas iš karto, kad ir kiek jų būtų – nereikia read_at kiekvienai žinutei.
 * Reikšmė tik didinama: jei lygiagrečiai atėjo naujesnė žinutė, senesnis id jos „neatžymi".
 * Kartu perskaitytais pažymimi ir šio pokalbio NewMessage pranešimai varpelyje.
 */
class MarkConversationRead
{
    public function handle(Conversation $conversation, User $user): void
    {
        // withTrashed – paslėpta žinutė irgi „perskaityta", kitaip ji amžinai liktų neperskaityta
        $lastId = $conversation->messages()->withTrashed()->max('id');

        if (! is_numeric($lastId)) {
            return;
        }

        DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->where(fn ($query) => $query
                ->whereNull('last_read_message_id')
                ->orWhere('last_read_message_id', '<', (int) $lastId))
            ->update(['last_read_message_id' => (int) $lastId]);

        // Varpelio pranešimai apie šio pokalbio žinutes nebeaktualūs – žinutės jau perskaitytos.
        // data->conversation_id – JSON stulpelio laukas; Laravel jį verčia į json_extract (MySQL ir SQLite)
        $user->unreadNotifications()
            ->where('type', NewMessage::class)
            ->where('data->conversation_id', $conversation->id)
            ->update(['read_at' => now()]);
    }
}
