<?php

namespace App\Actions\Messages;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Išsiunčia žinutę pokalbyje ir praneša kitai pusei.
 *
 * Transakcijoje: žinutė, conversations.last_message_at (denormalizuota – pokalbių sąrašo rikiavimui,
 * docs/DB_SCHEMA.md 2.10) ir siuntėjo last_read_message_id (savo žinutę jis jau „perskaitė").
 * Pokalbio eilutė užrakinama: dvi vienu metu siunčiamos žinutės įrašomos po vieną, todėl teisingai
 * nustatoma ir „kas jau perskaitė ankstesnę žinutę".
 *
 * Priedai pridedami PO transakcijos: failų įrašymas į diską nėra transakcijos dalis (jo „neatšauksi"), todėl
 * pirma išsaugom žinutę, tada failus – kaip SavePortfolioItem (Etapas 3). Pranešimas – pačioje pabaigoje.
 */
class SendMessage
{
    /**
     * @param  list<UploadedFile>  $attachments
     */
    public function handle(Conversation $conversation, User $sender, string $body, array $attachments = []): Message
    {
        /** @var Collection<int, User> $recipients */
        $recipients = new Collection;

        $message = DB::transaction(function () use ($conversation, $sender, $body, &$recipients): Message {
            Conversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            $previousId = $conversation->messages()->max('id');

            $message = new Message(['body' => $body]);
            $message->conversation()->associate($conversation);
            $message->sender()->associate($sender);
            $message->save();

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();
            $conversation->participants()->updateExistingPivot($sender->id, ['last_read_message_id' => $message->id]);

            $recipients = $this->recipientsToNotify($conversation, $sender, is_numeric($previousId) ? (int) $previousId : null);

            return $message;
        });

        foreach ($attachments as $file) {
            $message->addMedia($file)->toMediaCollection('attachments');
        }

        Notification::send($recipients, new NewMessage($message));

        return $message;
    }

    /**
     * Kitiems dalyviams pranešam tik tada, kai ši žinutė jiems – pirmoji neperskaityta: jie buvo perskaitę
     * viską iki jos (arba tai pirma pokalbio žinutė). Jei ankstesnė dar neperskaityta – apie šį pokalbį jau
     * pranešta, ir antras pranešimas būtų „šlamštas".
     *
     * @return Collection<int, User>
     */
    private function recipientsToNotify(Conversation $conversation, User $sender, ?int $previousId): Collection
    {
        return $conversation->participants()
            ->whereKeyNot($sender->id)
            ->get()
            ->filter(function (User $participant) use ($previousId): bool {
                $lastRead = $participant->getRelationValue('pivot')?->getAttribute('last_read_message_id');

                return $previousId === null || (is_numeric($lastRead) && (int) $lastRead >= $previousId);
            })
            ->values();
    }
}
