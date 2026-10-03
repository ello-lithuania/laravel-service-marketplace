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
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * „Žinutės": pokalbių sąrašas, vienas pokalbis ir pokalbio pradžia iš pasiūlymo („Rašyti").
 */
class ConversationController extends Controller
{
    /** Kiek naujausių žinučių rodyti pokalbyje (pokalbiai trumpi; ilgesniems – žr. docs/drafts/etapas-6.md). */
    public const MESSAGES_LIMIT = 100;

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
            'messages' => fn (): array => $this->messages($conversation),
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
     * Naujausios žinutės chronologine tvarka. Paslėptos (soft delete) irgi grąžinamos – vietoj teksto
     * rodom „Žinutė paslėpta", kad pokalbio eiga liktų suprantama.
     *
     * @return list<array<string, mixed>>
     */
    private function messages(Conversation $conversation): array
    {
        $messages = $conversation->messages()
            ->withTrashed()
            ->latest('id')
            ->limit(self::MESSAGES_LIMIT)
            ->get()
            ->reverse()
            // Siuntėjo vardui MessageResource naudoja jau užkrautą pokalbį – be papildomų užklausų
            ->each(fn (Message $message) => $message->setRelation('conversation', $conversation));

        return array_values($messages->map(fn (Message $message): array => MessageResource::make($message)->resolve())->all());
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
