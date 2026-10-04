<?php

namespace App\Services\Privacy;

use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\ProviderProfile;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Surenka visus vartotojo duomenis BDAR eksportui (15 str. – teisė susipažinti, 20 str. – perkeliamumas).
 *
 * Skaitoma tiesiai iš lentelių (query builder), o ne per Eloquent ryšius: duomenų daug (aktyvus teikėjas – tūkstančiai
 * pasiūlymų), modelių objektų nereikia, o preventLazyLoading neleistų netyčinių N+1. Kiekvienai lentelei – viena
 * užklausa su tik reikalingais stulpeliais. Raktai – lietuviški (be diakritikų), nes failą skaito pats žmogus.
 * Slaptažodžio hash'as, remember_token ir vidiniai techniniai laukai neeksportuojami.
 */
final class UserDataExporter
{
    /**
     * @return array<string, mixed>
     */
    public function collect(User $user): array
    {
        $profile = ProviderProfile::withTrashed()->where('user_id', $user->id)->first();

        return [
            'eksportuota' => now()->toIso8601String(),
            'paskyra' => $this->account($user),
            'teikejo_profilis' => $profile === null ? null : $this->providerProfile($profile),
            'uzklausos' => $this->serviceRequests($user),
            'pasiulymai' => $profile === null ? [] : $this->offers($profile),
            'pokalbiai' => $this->conversations($user),
            'atsiliepimai' => [
                'parasyti' => $this->reviews('author_id', $user->id),
                'gauti' => $profile === null ? [] : $this->reviews('provider_profile_id', $profile->id),
            ],
            'mokejimai' => $this->rows(DB::table('payments')->where('user_id', $user->id)->orderBy('id'), [
                'uuid', 'gateway', 'amount_cents', 'currency', 'status', 'paid_at', 'invoice_number', 'created_at',
            ]),
            // --- Etapas 9b: grąžinimai ir kreditinės sąskaitos (kas grąžino – nerodom) ---
            'grazinimai' => $this->rows(
                DB::table('refunds')
                    ->join('payments', 'payments.id', '=', 'refunds.payment_id')
                    ->where('payments.user_id', $user->id)
                    ->orderBy('refunds.id'),
                ['payments.uuid as mokejimas', 'refunds.amount_cents', 'refunds.reason', 'refunds.credits_reversed',
                    'refunds.credits_shortfall', 'refunds.credit_note_number', 'refunds.created_at'],
            ),
            'kreditu_operacijos' => $profile === null ? [] : $this->rows(
                DB::table('credit_transactions')->where('provider_profile_id', $profile->id)->orderBy('id'),
                ['amount', 'balance_after', 'type', 'description', 'created_at'],
            ),
            'prenumeratos' => $profile === null ? [] : $this->rows(
                DB::table('subscriptions')
                    ->join('subscription_plans', 'subscription_plans.id', '=', 'subscriptions.subscription_plan_id')
                    ->where('subscriptions.provider_profile_id', $profile->id)
                    ->orderBy('subscriptions.id'),
                ['subscription_plans.name as planas', 'subscriptions.status', 'subscriptions.starts_at', 'subscriptions.ends_at',
                    'subscriptions.cancelled_at', 'subscriptions.auto_renew'],
            ),
            'pranesimai' => $this->notifications($user),
            'skundai' => $this->rows(DB::table('complaints')->where('reporter_id', $user->id)->orderBy('id'), [
                'reportable_type', 'reason', 'description', 'status', 'resolution_note', 'created_at', 'resolved_at',
            ]),
            'failai' => $this->media($user, $profile)->map(fn (Media $media): array => [
                'kolekcija' => $media->collection_name,
                'failas' => $this->archivePath($media),
                'dydis_baitais' => $media->size,
                'ikelta' => $media->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * Visos vartotojo įkeltos nuotraukos (archyvui): avataras, logotipas, viršelis, darbų, užklausų nuotraukos,
     * žinučių priedai.
     *
     * @return Collection<int, Media>
     */
    public function media(User $user, ?ProviderProfile $profile = null): Collection
    {
        $profile ??= ProviderProfile::withTrashed()->where('user_id', $user->id)->first();

        $owners = [
            'user' => [$user->id],
            'provider_profile' => $profile === null ? [] : [$profile->id],
            'portfolio_item' => $profile === null ? [] : DB::table('portfolio_items')->where('provider_profile_id', $profile->id)->pluck('id')->all(),
            'service_request' => DB::table('service_requests')->where('client_id', $user->id)->pluck('id')->all(),
            'message' => DB::table('messages')->where('sender_id', $user->id)->pluck('id')->all(),
        ];

        return Media::query()
            ->where(function ($query) use ($owners): void {
                foreach ($owners as $type => $ids) {
                    if ($ids !== []) {
                        $query->orWhere(fn ($inner) => $inner->where('model_type', $type)->whereIn('model_id', $ids));
                    }
                }
            })
            ->orderBy('id')
            ->get()
            ->toBase();
    }

    /**
     * Kelias archyve: nuotraukos/{kolekcija}/{id}-{failo vardas}. ID – kad vienodi vardai nesutaptų.
     */
    public function archivePath(Media $media): string
    {
        return 'nuotraukos/'.$media->collection_name.'/'.$media->id.'-'.$media->file_name;
    }

    /**
     * @return array<string, mixed>
     */
    private function account(User $user): array
    {
        return [
            'id' => $user->id,
            'role' => $user->role->label(),
            'vardas' => $user->first_name,
            'pavarde' => $user->last_name,
            'el_pastas' => $user->email,
            'el_pastas_patvirtintas' => $this->date($user->email_verified_at),
            'telefonas' => $user->phone,
            'miestas' => $user->city_id === null ? null : DB::table('cities')->where('id', $user->city_id)->value('name'),
            'pranesimu_nustatymai' => $user->notification_settings,
            'uzregistruota' => $this->date($user->created_at),
            'paskutini_karta_matytas' => $this->date($user->last_seen_at),
            'uzblokuota' => $this->date($user->banned_at),
            'blokavimo_priezastis' => $user->ban_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function providerProfile(ProviderProfile $profile): array
    {
        return [
            'tipas' => $profile->type->label(),
            'pavadinimas' => $profile->display_name,
            'adresas' => $profile->slug,
            'antraste' => $profile->headline,
            'aprasymas' => $profile->description,
            'bazinis_miestas' => DB::table('cities')->where('id', $profile->city_id)->value('name'),
            'imones_kodas' => $profile->company_code,
            'pvm_kodas' => $profile->vat_code,
            'svetaine' => $profile->website,
            'patirtis_metais' => $profile->years_experience,
            'visa_lietuva' => $profile->serves_whole_country,
            'busena' => $profile->status->label(),
            'patikrintas' => $this->date($profile->verified_at),
            'kreditu_likutis' => $profile->credits_balance,
            'reitingas' => (float) $profile->rating_avg,
            'atsiliepimu' => $profile->reviews_count,
            'atliktu_darbu' => $profile->completed_jobs_count,
            'sukurta' => $this->date($profile->created_at),
            'paslaugos' => $this->rows(
                DB::table('category_provider_profile')
                    ->join('categories', 'categories.id', '=', 'category_provider_profile.category_id')
                    ->where('category_provider_profile.provider_profile_id', $profile->id)
                    ->orderBy('categories.name'),
                ['categories.name as paslauga', 'category_provider_profile.price_from_cents', 'category_provider_profile.price_unit'],
            ),
            'zonos' => DB::table('city_provider_profile')
                ->join('cities', 'cities.id', '=', 'city_provider_profile.city_id')
                ->where('city_provider_profile.provider_profile_id', $profile->id)
                ->orderBy('cities.name')
                ->pluck('cities.name')
                ->all(),
            'atlikti_darbai' => $this->rows(
                DB::table('portfolio_items')->where('provider_profile_id', $profile->id)->orderBy('sort_order'),
                ['id', 'title', 'description', 'completed_date', 'created_at'],
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serviceRequests(User $user): array
    {
        return array_map(function (array $row): array {
            $row['status'] = ServiceRequestStatus::tryFrom((string) $row['status'])?->label() ?? $row['status'];

            return $row;
        }, $this->rows(
            DB::table('service_requests')
                ->join('categories', 'categories.id', '=', 'service_requests.category_id')
                ->join('cities', 'cities.id', '=', 'service_requests.city_id')
                ->where('service_requests.client_id', $user->id)
                ->orderBy('service_requests.id'),
            ['service_requests.id', 'service_requests.title', 'service_requests.description', 'service_requests.address',
                'categories.name as paslauga', 'cities.name as miestas', 'service_requests.budget_min_cents',
                'service_requests.budget_max_cents', 'service_requests.start_preference', 'service_requests.start_date',
                'service_requests.status', 'service_requests.offers_count', 'service_requests.created_at',
                'service_requests.published_at', 'service_requests.completed_at', 'service_requests.cancelled_at',
                'service_requests.cancellation_reason'],
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function offers(ProviderProfile $profile): array
    {
        return array_map(function (array $row): array {
            $row['status'] = OfferStatus::tryFrom((string) $row['status'])?->label() ?? $row['status'];

            return $row;
        }, $this->rows(
            DB::table('offers')
                ->join('service_requests', 'service_requests.id', '=', 'offers.service_request_id')
                ->where('offers.provider_profile_id', $profile->id)
                ->orderBy('offers.id'),
            ['offers.id', 'service_requests.title as uzklausa', 'offers.message', 'offers.price_cents', 'offers.price_type',
                'offers.duration_text', 'offers.start_date', 'offers.status', 'offers.credits_spent', 'offers.created_at',
                'offers.viewed_at', 'offers.responded_at'],
        ));
    }

    /**
     * Pokalbiai, kuriuose vartotojas dalyvauja. Pašnekovo vardas – kaip jis rodomas svetainėje („Jonas P."):
     * tai ir jo asmens duomenys, todėl jų neatskleidžiam daugiau nei matė pats vartotojas.
     *
     * @return list<array<string, mixed>>
     */
    private function conversations(User $user): array
    {
        $conversationIds = DB::table('conversation_user')->where('user_id', $user->id)->pluck('conversation_id')->all();

        if ($conversationIds === []) {
            return [];
        }

        $titles = DB::table('conversations')
            ->leftJoin('service_requests', 'service_requests.id', '=', 'conversations.service_request_id')
            ->whereIn('conversations.id', $conversationIds)
            ->pluck('service_requests.title', 'conversations.id');

        $senders = DB::table('messages')
            ->join('users', 'users.id', '=', 'messages.sender_id')
            ->whereIn('messages.conversation_id', $conversationIds)
            ->where('messages.sender_id', '!=', $user->id)
            ->distinct()
            ->get(['users.id', 'users.first_name', 'users.last_name'])
            ->mapWithKeys(fn (object $sender): array => [$sender->id => $this->publicName($sender->first_name, $sender->last_name)]);

        return array_values(DB::table('messages')
            ->whereIn('conversation_id', $conversationIds)
            ->whereNull('deleted_at')
            ->orderBy('conversation_id')
            ->orderBy('id')
            ->get(['conversation_id', 'sender_id', 'body', 'created_at'])
            ->groupBy('conversation_id')
            ->map(fn (Collection $messages, int|string $conversationId): array => [
                'uzklausa' => $titles[$conversationId] ?? null,
                'zinutes' => $messages->map(fn (object $message): array => [
                    'autorius' => match (true) {
                        $message->sender_id === null => 'Sistema',
                        (int) $message->sender_id === $user->id => 'Jūs',
                        default => $senders[$message->sender_id] ?? 'Pašnekovas',
                    },
                    'tekstas' => $message->body,
                    'laikas' => $this->date($message->created_at),
                ])->values()->all(),
            ])
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reviews(string $column, int $id): array
    {
        return $this->rows(DB::table('reviews')->where($column, $id)->orderBy('id'), [
            'id', 'rating', 'comment', 'provider_reply', 'status', 'published_at', 'provider_replied_at', 'created_at',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function notifications(User $user): array
    {
        return array_values(DB::table('notifications')
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->orderBy('created_at')
            ->get(['type', 'data', 'read_at', 'created_at'])
            ->map(function (object $row): array {
                $data = json_decode((string) $row->data, true);

                return [
                    'tipas' => class_basename((string) $row->type),
                    'tekstas' => is_array($data) ? ($data['message'] ?? null) : null,
                    'perskaityta' => $this->date($row->read_at),
                    'gauta' => $this->date($row->created_at),
                ];
            })
            ->all());
    }

    /**
     * Užklausos rezultatas kaip masyvų sąrašas; datos – ISO 8601 (UTC).
     *
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function rows(Builder $query, array $columns): array
    {
        $rows = [];

        foreach ($query->get($columns) as $row) {
            $values = [];

            foreach ((array) $row as $key => $value) {
                $key = (string) $key;
                $values[$key] = is_string($value) && str_ends_with($key, '_at') ? $this->date($value) : $value;
            }

            $rows[] = $values;
        }

        return $rows;
    }

    private function date(DateTimeInterface|string|null $value): ?string
    {
        return $value === null ? null : Carbon::parse($value, 'UTC')->toIso8601String();
    }

    private function publicName(string $firstName, string $lastName): string
    {
        return $lastName === '' ? $firstName : trim($firstName.' '.mb_substr($lastName, 0, 1).'.');
    }
}
