<?php

namespace Database\Seeders\Demo;

use App\Enums\OfferStatus;
use Generator;

/**
 * Pokalbiai, dalyviai ir žinutės (docs/SEEDING.md 4 sk. „Pokalbiai ir žinutės").
 */
final class ConversationGenerator
{
    /** @var list<int> pokalbio pirmos žinutės indeksas */
    private array $firstMessage = [];

    /** @var list<int> */
    private array $messageCount = [];

    public function __construct(private readonly DemoContext $ctx) {}

    public function plan(): void
    {
        $c = $this->ctx;
        $offers = [];

        // Pokalbis kiekvienam priimtam pasiūlymui…
        foreach ($c->reqAccepted as $o) {
            if ($o >= 0) {
                $offers[$o] = true;
            }
        }

        $acceptedCount = count($offers);

        // …ir ≈ 12 000 (scale 1) kitų, kur klientas uždavė klausimą teikėjui
        $extra = (int) round(0.12 * $c->counts['service_requests']);
        $offerTotal = count($c->offStatus);
        $guard = 0;

        while (count($offers) < $acceptedCount + $extra && $guard++ < $extra * 20) {
            $o = $c->between(0, $offerTotal - 1);

            if ($c->offStatus[$o] !== OfferStatus::Withdrawn->value) {
                $offers[$o] = true;
            }
        }

        // Pokalbių id – chronologine tvarka
        $offers = array_keys($offers);
        usort($offers, fn (int $a, int $b) => $c->offCreated[$a] <=> $c->offCreated[$b]);

        $counts = $this->messageCounts($offers);

        foreach ($offers as $i => $o) {
            $r = $c->offRequest[$o];
            $client = $c->clientBase + $c->reqClient[$r];
            $provider = $c->providerUserBase + $c->offProvider[$o];
            $accepted = $c->offStatus[$o] === OfferStatus::Accepted->value;

            $c->convOffer[] = $o;
            $c->convClientUser[] = $client;
            $c->convProviderUser[] = $provider;
            $this->firstMessage[] = count($c->msgTime);
            $this->messageCount[] = $counts[$i];

            // Klausimą dažniausiai užduoda klientas; priimtuose pokalbiuose kartais pradeda teikėjas
            $sender = ! $accepted || $c->chance(0.6) ? $client : $provider;
            $time = $c->workingHours($c->offCreated[$o] + $c->between(10 * DemoContext::MINUTE, DemoContext::DAY));

            for ($m = 0; $m < $counts[$i]; $m++) {
                if ($m > 0) {
                    $previous = $time;
                    $time = $c->workingHours($time + $c->between(5 * DemoContext::MINUTE, 2 * DemoContext::DAY));
                    $sender = $sender === $client ? $provider : $client;

                    if ($time > $c->now) {
                        $time = $previous + 1 + (int) (($c->now - $previous - 1) * $c->rand01());
                    }
                }

                $time = min($time, $c->now);
                $c->msgConv[] = $i + 1;
                $c->msgSender[] = $sender;
                $c->msgTime[] = $time;
            }
        }
    }

    /**
     * Priimtiems – 2–5 žinutės, kitiems – 1–3; suma lygiai tiek, kiek nurodyta.
     *
     * @param  list<int>  $offers
     * @return array<int, int>
     */
    private function messageCounts(array $offers): array
    {
        $c = $this->ctx;
        $counts = [];
        $mins = [];
        $maxs = [];

        foreach ($offers as $i => $o) {
            [$mins[$i], $maxs[$i]] = $c->offStatus[$o] === OfferStatus::Accepted->value ? [2, 5] : [1, 3];
            $counts[$i] = $c->skewed($mins[$i], $maxs[$i], 1.5);
        }

        $target = $c->counts['messages'];
        $sum = array_sum($counts);
        $total = count($counts);
        $guard = 0;

        while ($total > 0 && $sum !== $target && $guard++ < $target * 50) {
            $i = $c->between(0, $total - 1);

            if ($sum < $target && $counts[$i] < $maxs[$i]) {
                $counts[$i]++;
                $sum++;
            } elseif ($sum > $target && $counts[$i] > $mins[$i]) {
                $counts[$i]--;
                $sum--;
            }
        }

        return $counts;
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function conversations(): Generator
    {
        $c = $this->ctx;

        foreach ($c->convOffer as $i => $o) {
            $first = $c->msgTime[$this->firstMessage[$i]];
            $last = $c->msgTime[$this->firstMessage[$i] + $this->messageCount[$i] - 1];

            yield [
                'id' => $i + 1,
                'service_request_id' => $c->offRequest[$o] + 1,
                'offer_id' => $o + 1,
                'last_message_at' => $c->date($last),
                'created_at' => $c->date($first),
                'updated_at' => $c->date($last),
            ];
        }
    }

    /**
     * Senesni pokalbiai perskaityti abiejų; naujausiuose gavėjas dar gali būti neperskaitęs paskutinės žinutės.
     *
     * @return Generator<int, array<string, int|null>>
     */
    public function participants(): Generator
    {
        $c = $this->ctx;

        foreach ($c->convOffer as $i => $o) {
            $lastIndex = $this->firstMessage[$i] + $this->messageCount[$i] - 1;
            $lastId = $lastIndex + 1;
            $lastSender = $c->msgSender[$lastIndex];
            $recent = $c->msgTime[$lastIndex] > $c->now - 7 * DemoContext::DAY;

            foreach ([$c->convClientUser[$i], $c->convProviderUser[$i]] as $user) {
                $read = $lastId;

                if ($recent && $user !== $lastSender && $c->chance(0.5)) {
                    $read = $this->messageCount[$i] > 1 ? $lastId - 1 : null;
                }

                yield [
                    'conversation_id' => $i + 1,
                    'user_id' => $user,
                    'last_read_message_id' => $read,
                ];
            }
        }
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function messages(): Generator
    {
        $c = $this->ctx;

        foreach ($c->msgConv as $m => $conversation) {
            $isClient = $c->msgSender[$m] === $c->convClientUser[$conversation - 1];
            $time = $c->date($c->msgTime[$m]);

            yield [
                'id' => $m + 1,
                'conversation_id' => $conversation,
                'sender_id' => $c->msgSender[$m],
                'body' => $c->text('messages', $isClient ? 'client' : 'provider'),
                'created_at' => $time,
                'updated_at' => $time,
            ];
        }
    }
}
