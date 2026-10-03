<?php

use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Messaging;

/**
 * NewMessage pranešimas: tik pirmai neperskaitytai žinutei, laiškas – su uždelsimu ir tik jei dar neperskaityta.
 */
beforeEach(function () {
    $this->offer = Messaging::offer();
    $this->conversation = Messaging::conversation($this->offer);
    $this->client = Messaging::client($this->offer);
    $this->provider = Messaging::provider($this->offer);
});

test('gavėjas gauna pranešimą tik apie pirmą neperskaitytą žinutę', function () {
    Notification::fake();

    Messaging::send($this->conversation, $this->client, 'Pirmas klausimas');
    Messaging::send($this->conversation, $this->client, 'Dar vienas klausimas');

    Notification::assertSentToTimes($this->provider, NewMessage::class, 1);
    Notification::assertNotSentTo($this->client, NewMessage::class);

    // Teikėjas perskaito – kita kliento žinutė vėl sukelia pranešimą
    $this->actingAs($this->provider)->get(route('conversations.show', $this->conversation))->assertOk();
    Messaging::send($this->conversation, $this->client, 'Trečias');

    Notification::assertSentToTimes($this->provider, NewMessage::class, 2);
});

test('atsakius – pranešimas kitai pusei; data kaip seed\'uose', function () {
    Notification::fake();

    Messaging::send($this->conversation, $this->client, 'Klausimas');
    $reply = Messaging::send($this->conversation, $this->provider, 'Atsakymas');

    Notification::assertSentTo($this->client, NewMessage::class, function (NewMessage $notification, array $channels) use ($reply) {
        $data = $notification->toArray($this->client);

        return $channels === ['mail', 'database']
            && $data === [
                'conversation_id' => $this->conversation->id,
                'message_id' => $reply->id,
                'message' => 'Nauja žinutė nuo '.$this->offer->providerProfile->display_name,
            ];
    });
});

test('nustatymuose išjungus – nesiunčiama', function () {
    Notification::fake();
    $this->provider->forceFill(['notification_settings' => ['messages' => ['mail' => false, 'database' => false]]])->save();

    Messaging::send($this->conversation, $this->client, 'Klausimas');

    // via() grąžina tuščią kanalų sąrašą – Laravel tokio pranešimo visai nesiunčia
    Notification::assertNotSentTo($this->provider, NewMessage::class);
});

test('laiškas uždelsiamas, o varpelis – iš karto', function () {
    $message = Messaging::send($this->conversation, $this->client, 'Klausimas');
    $notification = new NewMessage($message);

    expect($notification->withDelay($this->provider, 'database'))->toBeNull()
        ->and($notification->withDelay($this->provider, 'mail')?->getTimestamp())
        ->toBe(now()->addMinutes(NewMessage::MAIL_DELAY_MINUTES)->getTimestamp());
});

test('jei gavėjas žinutę jau perskaitė – laiškas nesiunčiamas', function () {
    $message = Messaging::send($this->conversation, $this->client, 'Klausimas');
    $notification = new NewMessage($message);

    expect($notification->shouldSend($this->provider, 'mail'))->toBeTrue();

    $this->actingAs($this->provider)->get(route('conversations.show', $this->conversation));

    expect($notification->shouldSend($this->provider, 'mail'))->toBeFalse()
        ->and($notification->shouldSend($this->provider, 'database'))->toBeTrue();
});

test('laiško tekstas lietuviškai su ištrauka ir nuoroda', function () {
    $message = Messaging::send($this->conversation, $this->provider, 'Galiu atvykti antradienį 9 val.');
    $mail = (new NewMessage($message))->toMail($this->client);

    expect($mail->subject)->toBe('Nauja žinutė nuo '.$this->offer->providerProfile->display_name)
        ->and($mail->introLines)->toContain('„Galiu atvykti antradienį 9 val."')
        ->and($mail->actionText)->toBe('Atsakyti')
        ->and($mail->actionUrl)->toBe(route('conversations.show', $this->conversation));
});

test('atidarius pokalbį varpelio pranešimai apie jį pažymimi perskaitytais', function () {
    Messaging::send($this->conversation, $this->client, 'Klausimas');
    $other = Messaging::conversation(Messaging::offer());
    $this->provider->notify(new NewMessage(Messaging::send($other, Messaging::client($other->offer), 'Kitas')));

    expect($this->provider->unreadNotifications()->where('type', NewMessage::class)->count())->toBe(2);

    $this->actingAs($this->provider)->get(route('conversations.show', $this->conversation))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 1));
});

test('paslėptos žinutės pranešimo job\'as išmetamas, o ne kartojamas', function () {
    expect((new NewMessage(Messaging::send($this->conversation, $this->client)))->deleteWhenMissingModels)->toBeTrue();
});

test('pranešimas neperduodamas svetimiems', function () {
    Notification::fake();
    $stranger = User::factory()->create();

    Messaging::send($this->conversation, $this->client, 'Klausimas');

    Notification::assertNotSentTo($stranger, NewMessage::class);
});
