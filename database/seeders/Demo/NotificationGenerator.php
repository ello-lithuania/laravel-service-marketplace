<?php

namespace Database\Seeders\Demo;

use Generator;

/**
 * Pranešimai (varpelis) apie paskutinių 60 dienų įvykius (docs/SEEDING.md 4 sk. „Pranešimai ir skundai").
 *
 * type – būsimų Notification klasių pavadinimai (Etapas 5), data – tai, ką grąžins jų toArray().
 * Suplanuoti pranešimai laikomi keturiuose skaičių masyvuose (ne masyvų masyve) – taip užima ~10 kartų mažiau atminties.
 */
final class NotificationGenerator
{
    private const TYPES = ['NewMatchingRequest', 'NewOffer', 'OfferAccepted', 'NewMessage', 'NewReview'];

    /** @var list<int> */
    private array $times = [];

    /** @var list<int> TYPES indeksas */
    private array $types = [];

    /** @var list<int> */
    private array $users = [];

    /** @var list<int> užklausos, pasiūlymo, žinutės arba atsiliepimo indeksas */
    private array $refs = [];

    public function __construct(private readonly DemoContext $ctx) {}

    public function plan(): void
    {
        $c = $this->ctx;
        $since = $c->now - 60 * DemoContext::DAY;

        // Naujos užklausos – tinkamiems teikėjams (iki 20 vienai užklausai, dažniausiai keliems)
        foreach ($c->reqPublished as $r => $published) {
            if ($published < $since) {
                continue;
            }

            foreach ($c->candidates($c->reqLeaf[$r], $c->reqCity[$r])?->pickUnique($c->skewed(1, 20, 5.0)) ?? [] as $p) {
                $this->add($published + $c->between(60, 30 * DemoContext::MINUTE), 0, $c->providerUserBase + $p, $r);
            }
        }

        foreach ($c->offCreated as $o => $created) {
            $r = $c->offRequest[$o];

            if ($created >= $since) {
                $this->add($created + 5, 1, $c->clientBase + $c->reqClient[$r], $o);
            }

            if ($c->reqAccepted[$r] === $o && $c->reqAcceptTime[$r] >= $since) {
                $this->add($c->reqAcceptTime[$r] + 5, 2, $c->providerUserBase + $c->offProvider[$o], $o);
            }
        }

        foreach ($c->msgTime as $m => $time) {
            if ($time >= $since) {
                $conversation = $c->msgConv[$m] - 1;
                $client = $c->convClientUser[$conversation];
                $this->add($time + 5, 3, $c->msgSender[$m] === $client ? $c->convProviderUser[$conversation] : $client, $m);
            }
        }

        foreach ($c->revCreated as $i => $time) {
            if ($time >= $since && $c->revPublished[$i]) {
                $this->add($time + 5, 4, $c->providerUserBase + $c->revProvider[$i], $i);
            }
        }

        array_multisort($this->times, $this->types, $this->users, $this->refs);
    }

    private function add(int $time, int $type, int $userId, int $ref): void
    {
        $this->times[] = min($time, $this->ctx->now);
        $this->types[] = $type;
        $this->users[] = $userId;
        $this->refs[] = $ref;
    }

    /**
     * @return array<string, int|string>
     */
    private function data(int $type, int $ref): array
    {
        $c = $this->ctx;

        return match ($type) {
            0 => [
                'service_request_id' => $ref + 1,
                'message' => 'Nauja užklausa jūsų srityje: „'.$c->reqTitle[$ref].'"',
            ],
            1 => [
                'offer_id' => $ref + 1,
                'service_request_id' => $c->offRequest[$ref] + 1,
                'message' => $c->provDisplayName[$c->offProvider[$ref]].' atsiuntė pasiūlymą užklausai „'.$c->reqTitle[$c->offRequest[$ref]].'"',
            ],
            2 => [
                'offer_id' => $ref + 1,
                'service_request_id' => $c->offRequest[$ref] + 1,
                'message' => 'Jūsų pasiūlymas užklausai „'.$c->reqTitle[$c->offRequest[$ref]].'" priimtas!',
            ],
            3 => [
                'conversation_id' => $c->msgConv[$ref],
                'message_id' => $ref + 1,
                'message' => 'Gavote naują žinutę',
            ],
            default => [
                'review_id' => $ref + 1,
                'message' => 'Gavote naują atsiliepimą',
            ],
        };
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(): Generator
    {
        $c = $this->ctx;

        foreach ($this->times as $i => $time) {
            // 70 % perskaityti; naujausi (paskutinės 2 d.) – rečiau
            $readChance = $time > $c->now - 2 * DemoContext::DAY ? 0.3 : 0.7;
            $read = $c->chance($readChance) ? min($c->now, $time + $c->between(5 * DemoContext::MINUTE, 3 * DemoContext::DAY)) : 0;

            yield [
                'id' => $c->uuid(),
                'type' => 'App\\Notifications\\'.self::TYPES[$this->types[$i]],
                'notifiable_type' => 'user',
                'notifiable_id' => $this->users[$i],
                'data' => json_encode($this->data($this->types[$i], $this->refs[$i]), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'read_at' => $c->nullableDate($read),
                'created_at' => $c->date($time),
                'updated_at' => $c->date(max($time, $read)),
            ];
        }
    }
}
