<?php

use App\Http\Controllers\Messages\ConversationController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Support\Header;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Messaging;

/**
 * Ilgi pokalbiai (Etapas 9): žinutės puslapiais (Inertia::scroll + cursorPaginate) – atidarius naujausios,
 * senesnės įkeliamos slenkant aukštyn. Šie testai daro tas pačias užklausas, kurias siunčia InfiniteScroll
 * ir usePoll: dalinis perkrovimas (X-Inertia-Partial-Data: messages) su ?cursor=… arba be jo.
 */

/**
 * Pokalbis su $count žinučių (pakaitomis klientas ir teikėjas).
 *
 * @return array{0: Conversation, 1: Offer, 2: list<int>} pokalbis, pasiūlymas ir žinučių id didėjimo tvarka
 */
function conversationWithMessages(int $count): array
{
    $offer = Messaging::offer();
    $conversation = Messaging::conversation($offer);
    $senders = [Messaging::client($offer)->id, Messaging::provider($offer)->id];

    $ids = Message::factory()
        ->count($count)
        ->for($conversation)
        ->sequence(fn ($sequence) => ['sender_id' => $senders[$sequence->index % 2]])
        ->create()
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    return [$conversation, $offer, $ids];
}

/**
 * Dalinis Inertia perkrovimas tik su „messages", kaip InfiniteScroll (su cursor) ar usePoll (be jo).
 *
 * @param  array<string, string>  $headers
 * @return array<string, mixed> Inertia puslapio JSON (props, mergeProps, matchPropsOn, scrollProps…)
 */
function reloadMessages(User $user, Conversation $conversation, ?string $cursor = null, array $headers = []): array
{
    $url = route('conversations.show', $conversation).($cursor === null ? '' : '?cursor='.urlencode($cursor));

    return test()->actingAs($user)
        ->get($url, [
            Header::INERTIA => 'true',
            Header::VERSION => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
            Header::PARTIAL_COMPONENT => 'messages/Show',
            Header::PARTIAL_ONLY => 'messages',
            ...$headers,
        ])
        ->assertOk()
        ->json();
}

/**
 * @param  array<string, mixed>  $page
 * @return list<int>
 */
function messageIds(array $page): array
{
    return array_column($page['props']['messages']['data'], 'id');
}

test('atidarius – naujausios žinutės (nuo naujausios), senesnės pasiekiamos per cursor be pasikartojimų', function () {
    [$conversation, $offer, $ids] = conversationWithMessages(120);
    $client = Messaging::client($offer);
    $perPage = ConversationController::MESSAGES_PER_PAGE;

    $this->actingAs($client)->get(route('conversations.show', $conversation))
        ->assertInertia(fn (Assert $page) => $page
            ->has('messages.data', $perPage)
            ->where('messages.data.0.id', $ids[119])
            ->where('messages.data.'.($perPage - 1).'.id', $ids[120 - $perPage]));

    // Visi puslapiai iš eilės, kaip slenkant aukštyn
    $first = reloadMessages($client, $conversation);
    expect($first['scrollProps']['messages'])->toMatchArray(['pageName' => 'cursor', 'previousPage' => null, 'currentPage' => 1])
        ->and($first['scrollProps']['messages']['nextPage'])->toBeString();

    $pages = [messageIds($first)];
    $cursor = $first['scrollProps']['messages']['nextPage'];

    while ($cursor !== null) {
        $page = reloadMessages($client, $conversation, $cursor);
        $pages[] = messageIds($page);
        $cursor = $page['scrollProps']['messages']['nextPage'];
    }

    expect(array_map('count', $pages))->toBe([50, 50, 20])
        // Visos žinutės lygiai po kartą, nuo naujausios iki seniausios
        ->and(array_merge(...$pages))->toBe(array_reverse($ids));
});

test('puslapio ribos: lygiai 50 žinučių – vienas puslapis, 51 – antrame tik seniausia', function () {
    [$exact, $offer] = conversationWithMessages(50);
    $page = reloadMessages(Messaging::client($offer), $exact);

    expect(messageIds($page))->toHaveCount(50)
        ->and($page['scrollProps']['messages']['nextPage'])->toBeNull();

    [$oneMore, $offer, $ids] = conversationWithMessages(51);
    $client = Messaging::client($offer);
    $first = reloadMessages($client, $oneMore);
    $second = reloadMessages($client, $oneMore, $first['scrollProps']['messages']['nextPage']);

    expect(messageIds($first))->toHaveCount(50)
        ->and(messageIds($second))->toBe([$ids[0]])
        ->and($second['scrollProps']['messages']['nextPage'])->toBeNull();
});

test('naujos žinutės cursor nepaveikia: senesnių puslapis nepasislenka', function () {
    [$conversation, $offer, $ids] = conversationWithMessages(70);
    $client = Messaging::client($offer);
    $cursor = reloadMessages($client, $conversation)['scrollProps']['messages']['nextPage'];

    // Kol skaitoma, ateina naujų žinučių (su ?page=2 dalis žinučių pasikartotų)
    Messaging::send($conversation, Messaging::provider($offer), 'Nauja 1');
    Messaging::send($conversation, Messaging::provider($offer), 'Nauja 2');

    expect(messageIds(reloadMessages($client, $conversation, $cursor)))->toBe(array_reverse(array_slice($ids, 0, 20)));
});

test('sujungimas (merge): polling ir senesni puslapiai jungiami prie data pagal id', function () {
    [$conversation, $offer] = conversationWithMessages(3);
    $client = Messaging::client($offer);

    // usePoll – be ketinimo antraštės: pridėti gale, sutampančius id atnaujinti vietoje
    $poll = reloadMessages($client, $conversation);
    expect($poll['mergeProps'])->toBe(['messages.data'])
        ->and($poll['matchPropsOn'])->toBe(['messages.data.id']);

    // InfiniteScroll pats nurodo, kurioje pusėje jungti (atvirkštiniame režime senesni – „append")
    $prepend = reloadMessages($client, $conversation, null, [Header::INFINITE_SCROLL_MERGE_INTENT => 'prepend']);
    expect($prepend['prependProps'])->toBe(['messages.data'])
        ->and($prepend)->not->toHaveKey('mergeProps');
});

test('senesnių žinučių negauna svetimas vartotojas; administratorius gauna, bet perskaitytumo nekeičia', function () {
    [$conversation, $offer, $ids] = conversationWithMessages(60);
    $cursor = reloadMessages(Messaging::client($offer), $conversation)['scrollProps']['messages']['nextPage'];
    $url = route('conversations.show', $conversation).'?cursor='.urlencode($cursor);

    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->provider()->create())->get($url)->assertForbidden();

    $admin = User::factory()->admin()->create();
    expect(messageIds(reloadMessages($admin, $conversation, $cursor)))->toBe(array_reverse(array_slice($ids, 0, 10)))
        ->and(DB::table('conversation_user')->where('user_id', $admin->id)->exists())->toBeFalse();
});

test('senesnių puslapio įkėlimas perskaitytumo nesumažina', function () {
    [$conversation, $offer, $ids] = conversationWithMessages(60);
    $client = Messaging::client($offer);
    $lastRead = fn (): ?int => $conversation->participants()->find($client->id)?->pivot?->last_read_message_id;

    $cursor = reloadMessages($client, $conversation)['scrollProps']['messages']['nextPage'];
    expect($lastRead())->toBe($ids[59]);

    reloadMessages($client, $conversation, $cursor);
    expect($lastRead())->toBe($ids[59]);

    // Kol pokalbis atidarytas, bet kuri jo užklausa pažymi perskaitytomis ir naujas žinutes (kaip polling'as)
    $reply = Messaging::send($conversation, Messaging::provider($offer), 'Ar dar aktualu?');
    reloadMessages($client, $conversation, $cursor);
    expect($lastRead())->toBe($reply->id);
});

test('žinučių puslapis be N+1: užklausų skaičius nepriklauso nuo žinučių kiekio', function () {
    $queries = function (int $count): int {
        [$conversation, $offer] = conversationWithMessages($count);
        $client = Messaging::client($offer);
        // Pirmas atidarymas pažymi perskaitytu; matuojam antrą (kaip polling'ą), kad abiem atvejais darbas tas pats
        reloadMessages($client, $conversation);

        DB::flushQueryLog();
        DB::enableQueryLog();
        reloadMessages($client, $conversation);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($queries(50))->toBe($queries(2));
});
