<?php

namespace App\Services\Admin;

use App\Enums\ComplaintStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\UserRole;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin skydelio (Filament dashboard) skaičiai vienoje vietoje (Etapas 8).
 *
 * Kodėl cache: pilname seed'e (1,9 mln. eilučių) visi skaičiai kartu užtrunka ~0,7 s (matavimai –
 * docs/PERFORMANCE.md), nes dalis jų skaičiuojama per visą lentelę (pvz. pasiūlymai per dieną – 300 000 eilučių).
 * Statistikai tikslumo iki sekundės nereikia, todėl rezultatas laikomas cache kelias minutes. Cache saugomi tik
 * paprasti masyvai (config/cache.php → serializable_classes = false neleidžia saugoti objektų).
 *
 * Kodėl ne papildomi indeksai: kiekvienas indeksas lėtina įrašymą (pasiūlymai, užklausos), o šias užklausas
 * mato tik keli administratoriai kas kelias minutes. Indeksas verta tada, kai užklausa dažna arba lėta vartotojui.
 *
 * Datos DB saugomos UTC, o dienos skaičiuojamos Lietuvos laiku (Europe/Vilnius).
 * https://laravel.com/docs/13.x/cache#retrieving-items-from-the-cache
 */
final class DashboardStats
{
    public const STATS_KEY = 'admin:dashboard:stats:v1';

    public const STATS_TTL_MINUTES = 5;

    public const ACTIVITY_TTL_MINUTES = 10;

    public const TIMEZONE = 'Europe/Vilnius';

    /**
     * Visi suvestinės skaičiai (StatsOverview valdikliui).
     *
     * @return array{
     *     users: array{total: int, client: int, provider: int, admin: int},
     *     registrations: array{last_7_days: int, last_30_days: int, daily: list<int>},
     *     requests: array<string, int>,
     *     offers: array{last_30_days: int, daily: list<int>},
     *     conversion: array{published: int, accepted: int, percent: float},
     *     moderation: array{requests: int, complaints: int, reviews: int, total: int},
     *     revenue: array{last_30_days_cents: int, payments: int}
     * }
     */
    public function summary(): array
    {
        return Cache::remember(self::STATS_KEY, now()->addMinutes(self::STATS_TTL_MINUTES), fn (): array => [
            'users' => $this->usersByRole(),
            'registrations' => $this->registrations(),
            'requests' => $this->requestsByStatus(),
            'offers' => $this->offers(),
            'conversion' => $this->conversion(),
            'moderation' => $this->moderationQueue(),
            'revenue' => $this->revenue(),
        ]);
    }

    /**
     * Užklausos ir pasiūlymai per dieną paskutines $days dienas (ChartWidget valdikliui).
     *
     * @return array{labels: list<string>, requests: list<int>, offers: list<int>}
     */
    public function activity(int $days): array
    {
        return Cache::remember(
            "admin:dashboard:activity:{$days}:v1",
            now()->addMinutes(self::ACTIVITY_TTL_MINUTES),
            fn (): array => [
                'labels' => array_map(
                    fn (CarbonImmutable $day): string => $day->format('m-d'),
                    $this->days($days),
                ),
                'requests' => $this->perDay(ServiceRequest::query()->toBase(), $days),
                'offers' => $this->perDay(Offer::query()->toBase(), $days),
            ],
        );
    }

    public function forget(): void
    {
        Cache::forget(self::STATS_KEY);

        foreach ([7, 30, 90] as $days) {
            Cache::forget("admin:dashboard:activity:{$days}:v1");
        }
    }

    /**
     * @return array{total: int, client: int, provider: int, admin: int}
     */
    private function usersByRole(): array
    {
        $counts = User::query()
            ->toBase()
            ->selectRaw('role, COUNT(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        $byRole = [];

        foreach (UserRole::cases() as $role) {
            $byRole[$role->value] = (int) ($counts[$role->value] ?? 0);
        }

        return [
            'total' => array_sum($byRole),
            'client' => $byRole[UserRole::Client->value],
            'provider' => $byRole[UserRole::Provider->value],
            'admin' => $byRole[UserRole::Admin->value],
        ];
    }

    /**
     * Naujos registracijos. role IN (…) – tam, kad MySQL naudotų indeksą (role, created_at) ir skaičiuotų
     * vien iš jo (be deleted_at sąlygos: vėliau ištrinta paskyra vis tiek buvo užregistruota).
     *
     * @return array{last_7_days: int, last_30_days: int, daily: list<int>}
     */
    private function registrations(): array
    {
        $query = DB::table('users')->whereIn('role', array_column(UserRole::cases(), 'value'));
        $daily = $this->perDay($query, 30);

        return [
            'last_7_days' => array_sum(array_slice($daily, -7)),
            'last_30_days' => array_sum($daily),
            'daily' => array_slice($daily, -14),
        ];
    }

    /**
     * @return array<string, int> būsena → kiekis (visos būsenos, net jei 0)
     */
    private function requestsByStatus(): array
    {
        $counts = ServiceRequest::query()
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];

        foreach (ServiceRequestStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    /**
     * @return array{last_30_days: int, daily: list<int>}
     */
    private function offers(): array
    {
        $daily = $this->perDay(Offer::query()->toBase(), 30);

        return ['last_30_days' => array_sum($daily), 'daily' => array_slice($daily, -14)];
    }

    /**
     * Konversija: kiek paskelbtų per 90 d. užklausų sulaukė priimto pasiūlymo (accepted_offer_id).
     * status IN (…) leidžia MySQL naudoti indeksą (status, published_at).
     *
     * @return array{published: int, accepted: int, percent: float}
     */
    private function conversion(): array
    {
        $row = ServiceRequest::query()
            ->toBase()
            ->whereIn('status', array_map(
                fn (ServiceRequestStatus $status): string => $status->value,
                array_filter(ServiceRequestStatus::cases(), fn (ServiceRequestStatus $status): bool => $status !== ServiceRequestStatus::Pending),
            ))
            ->where('published_at', '>=', now()->subDays(90))
            ->selectRaw('COUNT(*) as published, SUM(CASE WHEN accepted_offer_id IS NOT NULL THEN 1 ELSE 0 END) as accepted')
            ->first();

        $published = (int) ($row->published ?? 0);
        $accepted = (int) ($row->accepted ?? 0);

        return [
            'published' => $published,
            'accepted' => $accepted,
            'percent' => $published > 0 ? round($accepted / $published * 100, 1) : 0.0,
        ];
    }

    /**
     * Kas laukia administratoriaus: užklausos (pending), skundai (nauji ir nagrinėjami), atsiliepimai (pending).
     * Skundų ir atsiliepimų lentelės skaitomos tiesiogiai – jų valdymą kuria Etapas 6.
     *
     * @return array{requests: int, complaints: int, reviews: int, total: int}
     */
    private function moderationQueue(): array
    {
        $requests = ServiceRequest::query()->where('status', ServiceRequestStatus::Pending)->count();
        $complaints = DB::table('complaints')
            ->whereIn('status', [ComplaintStatus::Open->value, ComplaintStatus::InReview->value])
            ->count();
        $reviews = DB::table('reviews')->where('status', ReviewStatus::Pending->value)->count();

        return [
            'requests' => $requests,
            'complaints' => $complaints,
            'reviews' => $reviews,
            'total' => $requests + $complaints + $reviews,
        ];
    }

    /**
     * Pajamos – apmokėti mokėjimai per 30 d. Lentelė skaitoma tiesiogiai (mokėjimų UI kuria Etapas 7).
     *
     * @return array{last_30_days_cents: int, payments: int}
     */
    private function revenue(): array
    {
        $row = DB::table('payments')
            ->where('status', PaymentStatus::Paid->value)
            ->where('paid_at', '>=', now()->subDays(30))
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as total, COUNT(*) as payments')
            ->first();

        return [
            'last_30_days_cents' => (int) ($row->total ?? 0),
            'payments' => (int) ($row->payments ?? 0),
        ];
    }

    /**
     * Įrašų kiekis kiekvieną dieną (pagal created_at, Lietuvos laiku), įskaitant dienas be įrašų (0).
     *
     * @return list<int>
     */
    private function perDay(QueryBuilder $query, int $days): array
    {
        $days = $this->days($days);
        [$expression, $bindings] = $this->localDateExpression();

        $counts = (clone $query)
            ->where('created_at', '>=', $days[0]->startOfDay()->utc())
            ->selectRaw($expression.' as day, COUNT(*) as aggregate', $bindings)
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        return array_map(fn (CarbonImmutable $day): int => (int) ($counts[$day->toDateString()] ?? 0), $days);
    }

    /**
     * Paskutinės $count dienų (įskaitant šiandien) Lietuvos laiku.
     *
     * @return list<CarbonImmutable>
     */
    private function days(int $count): array
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        return array_map(fn (int $offset): CarbonImmutable => $today->subDays($offset), range($count - 1, 0));
    }

    /**
     * UTC laiko žymą paverčia Lietuvos data SQL'e. MySQL ir SQLite sintaksė skiriasi, todėl tikrinam tvarkyklę
     * (DB::getDriverName()). Poslinkis perduodamas kaip parametras (?), o ne įklijuojamas į SQL tekstą.
     * Naudojamas dabartinis poslinkis (+02:00 / +03:00): vasaros laiko keitimo savaitę dienos riba gali pasislinkti
     * valanda – statistikai tai nesvarbu, o laiko zonų lentelių MySQL serveryje nereikia.
     *
     * @return array{0: literal-string, 1: list<string>}
     */
    private function localDateExpression(): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);

        return match (DB::getDriverName()) {
            'mysql', 'mariadb' => ["DATE(CONVERT_TZ(created_at, '+00:00', ?))", [$now->format('P')]],
            'sqlite' => ['date(created_at, ?)', [sprintf('%+d seconds', $now->getOffset())]],
            default => ['DATE(created_at)', []],
        };
    }
}
