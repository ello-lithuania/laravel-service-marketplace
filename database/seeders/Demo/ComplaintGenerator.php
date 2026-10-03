<?php

namespace Database\Seeders\Demo;

use App\Enums\ComplaintReason as Reason;
use App\Enums\ComplaintStatus as Status;
use Generator;

/**
 * Skundai: 50 % dėl atsiliepimų, 25 % dėl užklausų, 15 % dėl žinučių, 10 % dėl profilių.
 */
final class ComplaintGenerator
{
    /** @var array<string, list<string>> */
    private const DESCRIPTIONS = [
        'spam' => ['Atrodo kaip reklama, ne tikra užklausa.', 'Tas pats tekstas kartojamas daug kartų.'],
        'fraud' => ['Prašo sumokėti avansą į asmeninę sąskaitą.', 'Įtariu sukčiavimą.'],
        'offensive' => ['Įžeidžiantis tonas.', 'Netinkami žodžiai.'],
        'fake_review' => ['Šis žmogus niekada nebuvo mano klientas.', 'Atsiliepimas parašytas konkurento.'],
        'wrong_info' => ['Nurodyta neteisinga informacija.', 'Kategorija neatitinka aprašymo.'],
        'other' => ['Prašau patikrinti.', 'Kažkas negerai.'],
    ];

    /** @var list<string> */
    private const NOTES = ['Patikrinta, turinys paslėptas.', 'Pažeidimo nerasta.', 'Vartotojas įspėtas.', 'Susisiekta su abiem pusėmis.'];

    public function __construct(private readonly DemoContext $ctx) {}

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(): Generator
    {
        $c = $this->ctx;
        $total = max(1, (int) round(3_000 * $c->scale));
        $statuses = [Status::Resolved, Status::Rejected, Status::Open, Status::InReview];
        $active = array_keys(array_filter($c->provStatus, fn (string $s) => $s === 'active'));
        $published = array_keys(array_filter($c->reqPublished, fn (int $time) => $time > 0));
        $planned = [];

        for ($i = 0; $i < $total; $i++) {
            $kind = $c->pickIndex([0.50, 0.25, 0.15, 0.10]);

            [$type, $id, $reporter, $since, $reason] = match (true) {
                $kind === 0 && $c->revCreated !== [] => $this->review(),
                $kind === 2 && $c->msgTime !== [] => $this->message(),
                $kind === 3 => [
                    'provider_profile', ($p = $c->pickOne($active)) + 1, $c->clientBase + $c->between(0, count($c->clientCity) - 1),
                    $c->provCreated[$p], [Reason::Fraud, Reason::WrongInfo, Reason::Offensive, Reason::Other][$c->pickIndex([0.3, 0.4, 0.2, 0.1])],
                ],
                default => [
                    'service_request', ($r = $c->pickOne($published)) + 1, $c->providerUserBase + $c->pickOne($active),
                    $c->reqPublished[$r], [Reason::Spam, Reason::WrongInfo, Reason::Fraud, Reason::Other][$c->pickIndex([0.4, 0.3, 0.2, 0.1])],
                ],
            };

            $created = min($c->now, $c->growthTime($since + DemoContext::HOUR, min($c->now, $since + 30 * DemoContext::DAY)));
            $status = $statuses[$c->pickIndex([0.60, 0.25, 0.10, 0.05])];
            $planned[] = compact('type', 'id', 'reporter', 'created', 'reason', 'status');
        }

        usort($planned, fn (array $a, array $b) => $a['created'] <=> $b['created']);

        foreach ($planned as $i => $complaint) {
            $created = $complaint['created'];
            $status = $complaint['status'];
            $closed = in_array($status, [Status::Resolved, Status::Rejected], true);
            $resolved = $closed ? min($c->now, $created + $c->between(DemoContext::HOUR, 7 * DemoContext::DAY)) : 0;

            yield [
                'id' => $i + 1,
                'reporter_id' => $complaint['reporter'],
                'reportable_type' => $complaint['type'],
                'reportable_id' => $complaint['id'],
                'reason' => $complaint['reason']->value,
                'description' => $c->chance(0.7) ? $c->pickOne(self::DESCRIPTIONS[$complaint['reason']->value]) : null,
                'status' => $status->value,
                'handled_by_id' => $status === Status::Open || $c->adminIds === [] ? null : $c->pickOne($c->adminIds),
                'resolution_note' => $closed ? $c->pickOne(self::NOTES) : null,
                'resolved_at' => $c->nullableDate($resolved),
                'created_at' => $c->date($created),
                'updated_at' => $c->date(max($created, $resolved)),
            ];
        }
    }

    /**
     * Dažniausiai skundžiasi pats teikėjas dėl jam parašyto atsiliepimo.
     *
     * @return array{0: string, 1: int, 2: int, 3: int, 4: Reason}
     */
    private function review(): array
    {
        $c = $this->ctx;
        $i = $c->between(0, count($c->revCreated) - 1);
        $reporter = $c->chance(0.7)
            ? $c->providerUserBase + $c->revProvider[$i]
            : $c->clientBase + $c->between(0, count($c->clientCity) - 1);
        $reason = [Reason::FakeReview, Reason::Offensive, Reason::Spam, Reason::Other][$c->pickIndex([0.5, 0.25, 0.15, 0.1])];

        return ['review', $i + 1, $reporter, $c->revCreated[$i], $reason];
    }

    /**
     * Skundžiasi žinutės gavėjas.
     *
     * @return array{0: string, 1: int, 2: int, 3: int, 4: Reason}
     */
    private function message(): array
    {
        $c = $this->ctx;
        $m = $c->between(0, count($c->msgTime) - 1);
        $conversation = $c->msgConv[$m] - 1;
        $client = $c->convClientUser[$conversation];
        $reporter = $c->msgSender[$m] === $client ? $c->convProviderUser[$conversation] : $client;
        $reason = [Reason::Spam, Reason::Offensive, Reason::Fraud][$c->pickIndex([0.4, 0.35, 0.25])];

        return ['message', $m + 1, $reporter, $c->msgTime[$m], $reason];
    }
}
