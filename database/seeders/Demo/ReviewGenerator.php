<?php

namespace Database\Seeders\Demo;

use App\Enums\ReviewStatus;
use App\Enums\ServiceRequestStatus;
use Generator;

/**
 * Atsiliepimai: patvirtinti (po atliktos užklausos) ir pakvietimo (docs/SEEDING.md 4 sk. „Atsiliepimai").
 */
final class ReviewGenerator
{
    /** @var list<array{time: int, request: int, provider: int, author: int}> */
    private array $planned = [];

    public function __construct(private readonly DemoContext $ctx) {}

    public function plan(): void
    {
        $c = $this->ctx;
        $total = $c->counts['reviews'];

        $completed = array_keys(array_filter($c->reqStatus, fn (string $s) => $s === ServiceRequestStatus::Completed->value));
        shuffle($completed);
        $verified = array_slice($completed, 0, min($total, (int) round(count($completed) * (float) config('seeding.verified_review_ratio'))));

        foreach ($verified as $r) {
            $this->planned[] = [
                'time' => min($c->now, $c->reqCompleted[$r] + $c->between(DemoContext::HOUR, 14 * DemoContext::DAY)),
                'request' => $r,
                'provider' => $c->offProvider[$c->reqAccepted[$r]],
                'author' => $c->reqClient[$r],
            ];
        }

        // Pakvietimo atsiliepimai: teikėjas pakviečia savo ankstesnius klientus (be užklausos platformoje)
        $active = array_keys(array_filter($c->provStatus, fn (string $s) => $s === 'active'));
        $providerPicker = new WeightedPicker($active, array_map(fn (int $p) => sqrt($c->provActivity[$p]), $active));
        $clients = count($c->clientCity);

        for ($i = count($this->planned); $i < $total; $i++) {
            $p = $providerPicker->pick();
            $time = min($c->now, $c->growthTime($c->provCreated[$p] + DemoContext::DAY, $c->now));
            $author = $c->between(0, $clients - 1);

            for ($try = 0; $try < 10 && $c->clientCreated[$author] > $time; $try++) {
                $author = $c->between(0, $clients - 1);
            }

            if ($c->clientCreated[$author] > $time) {
                $time = min($c->now, $c->clientCreated[$author] + $c->between(DemoContext::HOUR, 7 * DemoContext::DAY));
            }

            $this->planned[] = ['time' => $time, 'request' => -1, 'provider' => $p, 'author' => $author];
        }

        // id – chronologine tvarka
        usort($this->planned, fn (array $a, array $b) => $a['time'] <=> $b['time']);
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(): Generator
    {
        $c = $this->ctx;
        $statuses = [ReviewStatus::Published, ReviewStatus::Hidden, ReviewStatus::Pending];

        foreach ($this->planned as $i => $review) {
            // „J" formos pasiskirstymas: 5★ 62 %, 4★ 22 %, 3★ 7 %, 2★ 3 %, 1★ 6 %
            $rating = 5 - $c->pickIndex([0.62, 0.22, 0.07, 0.03, 0.06]);
            $status = $statuses[$c->pickIndex([0.97, 0.02, 0.01])];
            $time = $review['time'];
            $replied = $status !== ReviewStatus::Pending && $c->chance(0.2)
                ? min($c->now, $time + $c->between(DemoContext::HOUR, 10 * DemoContext::DAY))
                : 0;

            $c->revProvider[] = $review['provider'];
            $c->revCreated[] = $time;
            $c->revPublished[] = $status === ReviewStatus::Published;

            yield [
                'id' => $i + 1,
                'service_request_id' => $review['request'] >= 0 ? $review['request'] + 1 : null,
                'provider_profile_id' => $review['provider'] + 1,
                'author_id' => $c->clientBase + $review['author'],
                'rating' => $rating,
                'comment' => $c->sentences('reviews', 'by_rating', $c->between(1, 2), $rating),
                'provider_reply' => $replied > 0 ? $c->text('reviews', 'replies') : null,
                'provider_replied_at' => $c->nullableDate($replied),
                'status' => $status->value,
                'published_at' => $status === ReviewStatus::Pending ? null : $c->date($time),
                'created_at' => $c->date($time),
                'updated_at' => $c->date(max($time, $replied)),
            ];
        }
    }
}
