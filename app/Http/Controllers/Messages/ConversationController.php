<?php

namespace App\Http\Controllers\Messages;

use App\Actions\Messages\MarkConversationRead;
use App\Actions\Messages\StartConversation;
use App\Http\Controllers\Controller;
use App\Http\Resources\Messages\ConversationListItemResource;
use App\Http\Resources\Messages\ConversationResource;
use App\Http\Resources\Messages\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\User;
use App\Services\Messaging\UnreadMessages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * „Žinutės": pokalbių sąrašas, vienas pokalbis ir pokalbio pradžia iš pasiūlymo („Rašyti").
 */
class ConversationController extends Controller
{
    /** Kiek žinučių įkeliama vienu kartu: atidarius – naujausios, slenkant aukštyn – po tiek pat senesnių. */
    public const MESSAGES_PER_PAGE = 50;

    /**
     * Mano pokalbiai: per pivot conversation_user (indeksas user_id, conversation_id), naujausi viršuje.
     * Tušti pokalbiai (be žinučių) nerodomi.
     */
    public function index(Request $request, UnreadMessages $unread): Response
    {
        $user = $this->user($request);

        $conversations = $user->conversations()
            ->whereNotNull('conversations.last_message_at')
            ->with([
                'latestMessage',
                // Ištrintos paskyros (soft delete) pokalbis lieka – be SoftDeletingScope, tas pats kas withTrashed()
                'offer.providerProfile' => fn (Relation $query) => $query->withoutGlobalScope(SoftDeletingScope::class),
                'offer.serviceRequest.client' => fn (Relation $query) => $query->withoutGlobalScope(SoftDeletingScope::class),
            ])
            // Neperskaitytų skaičius kiekvienam pokalbiui – koreliuota subužklausa toje pačioje SQL užklausoje.
            // conversation_user jau prijungta belongsToMany ryšio, todėl sąlyga gali naudoti last_read_message_id
            ->withCount(['messages as unread_count' => fn (Builder $query) => $unread->onlyUnread($query, $user)])
            ->orderByDesc('conversations.last_message_at')
            ->orderByDesc('conversations.id')
            ->paginate(20);

        return Inertia::render('messages/Index', [
            'conversations' => ConversationListItemResource::collection($conversations),
        ]);
    }

    /**
     * Pokalbis. Atidarius (ir kiekvieno automatinio atnaujinimo metu) pažymimas perskaitytu.
     * Props – closure: dalinis perkrovimas su only: ['messages'] skaičiuoja tik žinutes.
     *
     * Žinutės – Inertia::scroll() (begalinis slinkimas, InfiniteScroll komponentas Show.vue): pirmas puslapis –
     * naujausios žinutės, ?cursor=… – senesnės. Naršyklė puslapius sujungia (merge), o matchOn('data.id')
     * neleidžia dubliuotis: polling'as kas 10 s vėl gauna naujausių puslapį, ir jau rodomos žinutės
     * atnaujinamos vietoje (pvz. paslėpta), o naujos – pridedamos.
     */
    public function show(Request $request, Conversation $conversation, MarkConversationRead $markRead): Response
    {
        $conversation->load([
            'participants',
            'offer.providerProfile' => fn (Relation $query) => $query->withoutGlobalScope(SoftDeletingScope::class),
            'offer.serviceRequest.client' => fn (Relation $query) => $query->withoutGlobalScope(SoftDeletingScope::class),
        ]);

        Gate::authorize('view', $conversation);
        $user = $this->user($request);

        if ($conversation->hasParticipant($user)) {
            $markRead->handle($conversation, $user);
        }

        return Inertia::render('messages/Show', [
            'conversation' => fn (): array => ConversationResource::make($conversation)->resolve(),
            'messages' => Inertia::scroll(fn (): CursorPaginator => $this->messages($conversation))->matchOn('data.id'),
            'can' => function () use ($user, $conversation): array {
                $permission = Gate::forUser($user)->inspect('sendMessage', $conversation);

                return ['send' => $permission->allowed(), 'reason' => $permission->denied() ? $permission->message() : null];
            },
        ]);
    }

    /**
     * „Rašyti" pasiūlymo puslapyje: suranda arba sukuria pokalbį ir nukreipia į jį.
     * POST, o ne GET: užklausa gali sukurti įrašą DB (GET turi būti saugus – nieko nekeisti).
     */
    public function store(Offer $offer, StartConversation $start): RedirectResponse
    {
        Gate::authorize('start', [Conversation::class, $offer]);

        return to_route('conversations.show', $start->handle($offer));
    }

    /**
     * Vienas žinučių puslapis nuo naujausių (id mažėjančiai). Rodymo tvarką (seniausios viršuje) sudėlioja Show.vue.
     * Paslėptos (soft delete) irgi grąžinamos – vietoj teksto rodom „Žinutė paslėpta", kad pokalbio eiga liktų suprantama.
     *
     * Cursor, o ne puslapio numeris (?page=2): kol skaitai senas žinutes, ateina naujų, ir „2 puslapis" pasislinktų –
     * dalis žinučių pasikartotų. Cursor reiškia „žinutės, kurių id < X", todėl naujos žinutės jo nepaveikia,
     * o užklausa naudoja indeksą (conversation_id, id) be OFFSET.
     *
     * @return CursorPaginator<int, array<mixed>>
     */
    private function messages(Conversation $conversation): CursorPaginator
    {
        return $conversation->messages()
            ->withTrashed()
            // Priedai (medialibrary) – viena užklausa visoms puslapio žinutėms
            ->with('media')
            ->orderByDesc('id')
            ->cursorPaginate(self::MESSAGES_PER_PAGE)
            ->through(function (Message $message) use ($conversation): array {
                // Siuntėjo vardui MessageResource naudoja jau užkrautą pokalbį – be papildomų užklausų
                $message->setRelation('conversation', $conversation);

                return MessageResource::make($message)->resolve();
            });
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
