<?php

namespace App\Services\Site;

use App\Models\ProviderProfile;
use App\Models\Review;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pradžios puslapio „gyvi" duomenys (Etapas 10): platformos skaičiai ir naujausi 5★ atsiliepimai.
 * Etapas 11: ir teikėjų skaičius kiekvienoje 1 lygio srityje.
 *
 * Jie keičiasi lėtai, o tikslumas iki minutės nesvarbus, todėl cache – 1 val. (Cache::remember su TTL), ne
 * rememberForever su observer'iu: kitaip kiekvienas naujas atsiliepimas ar užbaigtas darbas valytų cache.
 * Cache saugom tik skaliarus masyvuose (config/cache.php → serializable_classes = false).
 *
 * https://laravel.com/docs/13.x/cache#retrieve-store
 */
final class SiteHighlights
{
    public const STATS_KEY = 'site:stats:v1';

    public const TESTIMONIALS_KEY = 'site:testimonials:v1';

    public const CATEGORY_PROVIDERS_KEY = 'site:category-providers:v1';

    private const TTL_SECONDS = 3600;

    private const TESTIMONIALS = 6;

    /** Trumpi „Viskas gerai" atsiliepimai pradžios puslapyje neįtikina – rodom tik išsamesnius. */
    private const MIN_COMMENT_LENGTH = 40;

    /**
     * @return array{providers: int, reviews: int, rating_avg: float|null, completed_jobs: int}
     */
    public function stats(): array
    {
        return Cache::remember(self::STATS_KEY, self::TTL_SECONDS, self::loadStats(...));
    }

    /**
     * @return list<array{id: int, rating: int, comment: string, author_name: string, published_at: string|null, provider: array{name: string, slug: string}, category: string|null, city: string|null}>
     */
    public function testimonials(): array
    {
        return Cache::remember(self::TESTIMONIALS_KEY, self::TTL_SECONDS, self::loadTestimonials(...));
    }

    /**
     * Etapas 11: kiek aktyvių teikėjų dirba kiekvienoje 1 lygio srityje (pradžios puslapio kategorijų kortelės).
     *
     * @return array<int, int> 1 lygio kategorijos id => teikėjų skaičius
     */
    public function categoryProviderCounts(): array
    {
        return Cache::remember(self::CATEGORY_PROVIDERS_KEY, self::TTL_SECONDS, self::loadCategoryProviderCounts(...));
    }

    public function forget(): void
    {
        Cache::forget(self::STATS_KEY);
        Cache::forget(self::TESTIMONIALS_KEY);
        Cache::forget(self::CATEGORY_PROVIDERS_KEY);
    }

    /**
     * Viena agregato užklausa iš denormalizuotų teikėjų skaitliukų (docs/DB_SCHEMA.md 2.5: rating_avg, reviews_count,
     * completed_jobs_count), o ne COUNT/AVG per visą reviews ir service_requests lenteles.
     * Vidurkis – svertinis: teikėjas su 40 atsiliepimų sveria daugiau nei teikėjas su vienu.
     *
     * @return array{providers: int, reviews: int, rating_avg: float|null, completed_jobs: int}
     */
    public static function loadStats(): array
    {
        $row = ProviderProfile::query()
            ->active()
            ->toBase()
            ->selectRaw('COUNT(*) as providers')
            ->selectRaw('COALESCE(SUM(reviews_count), 0) as reviews')
            ->selectRaw('COALESCE(SUM(completed_jobs_count), 0) as completed_jobs')
            ->selectRaw('COALESCE(SUM(rating_avg * reviews_count), 0) as rating_sum')
            ->first();

        $reviews = (int) ($row->reviews ?? 0);

        return [
            'providers' => (int) ($row->providers ?? 0),
            'reviews' => $reviews,
            'rating_avg' => $reviews > 0 ? round((float) $row->rating_sum / $reviews, 1) : null,
            'completed_jobs' => (int) ($row->completed_jobs ?? 0),
        ];
    }

    /**
     * Teikėjas renkasi 1–3 lygio kategorijas (category_provider_profile), o kortelė rodo 1 lygį, todėl kiekviena
     * pasirinkta kategorija SQL'e „pakeliama" iki šaknies: 1 lygis – ji pati, 2 lygis – tėvas, 3 lygis – tėvo tėvas
     * (medis ne gilesnis nei 3 lygiai, docs/DB_SCHEMA.md 2.2). COUNT(DISTINCT …): teikėjas, pasirinkęs kelias tos
     * pačios srities paslaugas, skaičiuojamas vieną kartą. CASE ir GROUP BY pagal alias veikia ir MySQL, ir SQLite.
     *
     * @return array<int, int>
     */
    public static function loadCategoryProviderCounts(): array
    {
        $rows = DB::table('category_provider_profile as pivot')
            ->join('categories as category', 'category.id', '=', 'pivot.category_id')
            ->leftJoin('categories as parent', 'parent.id', '=', 'category.parent_id')
            // Subužklausa, o ne JOIN su provider_profiles: active() scope'o stulpeliai lieka be lentelės prefikso
            ->whereIn('pivot.provider_profile_id', ProviderProfile::query()->active()->select('id'))
            ->selectRaw('CASE WHEN category.parent_id IS NULL THEN category.id WHEN parent.parent_id IS NULL THEN parent.id ELSE parent.parent_id END as root_id')
            ->selectRaw('COUNT(DISTINCT pivot.provider_profile_id) as providers')
            ->groupBy('root_id')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->root_id] = (int) $row->providers;
        }

        return $counts;
    }

    /**
     * Naujausi paskelbti 5★ atsiliepimai su komentaru – tik apie aktyvius teikėjus.
     * ORDER BY id (pirminis raktas), ne published_at: MySQL eina indeksu nuo galo ir sustoja radęs 6 tinkamus.
     *
     * @return list<array{id: int, rating: int, comment: string, author_name: string, published_at: string|null, provider: array{name: string, slug: string}, category: string|null, city: string|null}>
     */
    public static function loadTestimonials(): array
    {
        $reviews = Review::query()
            ->published()
            ->where('rating', 5)
            ->whereNotNull('comment')
            // LENGTH veikia ir MySQL, ir SQLite (MySQL skaičiuoja baitus – minimaliam ilgiui to pakanka)
            ->whereRaw('LENGTH(comment) >= ?', [self::MIN_COMMENT_LENGTH])
            ->whereHas('providerProfile', fn ($query) => $query->active())
            ->with([
                // Ištrintos paskyros atsiliepimas lieka (vardas anonimizuotas) – kaip teikėjo profilyje
                'author' => fn (Relation $query) => $query
                    ->withoutGlobalScope(SoftDeletingScope::class)
                    ->select(['id', 'first_name', 'last_name']),
                'providerProfile:id,display_name,slug',
                'serviceRequest:id,category_id,city_id',
                'serviceRequest.category:id,name',
                'serviceRequest.city:id,name',
            ])
            ->orderByDesc('id')
            ->limit(self::TESTIMONIALS)
            ->get();

        return array_values($reviews->map(fn (Review $review): array => [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => (string) $review->comment,
            'author_name' => $review->author->public_name,
            'published_at' => $review->published_at?->toIso8601String(),
            'provider' => [
                'name' => $review->providerProfile->display_name,
                'slug' => $review->providerProfile->slug,
            ],
            // Pagal pakvietimą (be užklausos) atsiliepimas neturi kategorijos ir miesto
            'category' => $review->serviceRequest?->category?->name,
            'city' => $review->serviceRequest?->city?->name,
        ])->all());
    }
}
