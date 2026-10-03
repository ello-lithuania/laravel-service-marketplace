<?php

namespace App\Actions\Messages;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Išsiunčia žinutę pokalbyje.
 *
 * Transakcijoje: žinutė, conversations.last_message_at (denormalizuota – pokalbių sąrašo rikiavimui,
 * docs/DB_SCHEMA.md 2.10) ir siuntėjo last_read_message_id (savo žinutę jis jau „perskaitė").
 * Pokalbio eilutė užrakinama: dvi vienu metu siunčiamos žinutės įrašomos po vieną.
 *
 * Priedai pridedami PO transakcijos: failų įrašymas į diską nėra transakcijos dalis (jo „neatšauksi"), todėl
 * pirma išsaugom žinutę, tada failus – kaip SavePortfolioItem (Etapas 3).
 */
class SendMessage
{
    /**
     * @param  list<UploadedFile>  $attachments
     */
    public function handle(Conversation $conversation, User $sender, string $body, array $attachments = []): Message
    {
        $message = DB::transaction(function () use ($conversation, $sender, $body): Message {
            Conversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            $message = new Message(['body' => $body]);
            $message->conversation()->associate($conversation);
            $message->sender()->associate($sender);
            $message->save();

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();
            $conversation->participants()->updateExistingPivot($sender->id, ['last_read_message_id' => $message->id]);

            return $message;
        });

        foreach ($attachments as $file) {
            $message->addMedia($file)->toMediaCollection('attachments');
        }

        return $message;
    }
}
