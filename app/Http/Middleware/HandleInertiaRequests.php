<?php

namespace App\Http\Middleware;

use App\Enums\SitePhotoKey;
use App\Http\Resources\AuthUserResource;
use App\Services\Catalog\CachedCategory;
use App\Services\Catalog\CachedCity;
use App\Services\Catalog\CatalogCache;
use App\Services\Messaging\UnreadMessages;
use App\Services\Site\SitePhotos;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // Tik reikalingi laukai, ne visas modelis (Etapas 3). Closure – skaičiuojama tik kai reikia
                'user' => fn (): ?array => $request->user() === null
                    ? null
                    : AuthUserResource::make($request->user())->resolve($request),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Etapas 5: neperskaitytų pranešimų skaičius varpeliui. Closure – vykdoma tik kai prop'o reikia
            // (dalinis perkrovimas su „only" jo neskaičiuoja); COUNT naudoja indeksą (notifiable_type, notifiable_id).
            'notifications' => fn (): ?array => $request->user() === null ? null : [
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
            // --- Etapas 6: neperskaitytų žinučių skaičius meniu ženkleliui ---
            // Vardas „inbox", ne „messages": puslapio prop'as tokiu pat vardu (pokalbio žinutės) jį perrašytų.
            // Closure – skaičiuojama tik kai reikia; vienas COUNT su JOIN (UnreadMessages::total).
            'inbox' => fn (): ?array => $request->user() === null ? null : [
                'unread_count' => app(UnreadMessages::class)->total($request->user()),
            ],
            // --- Etapas 10: viešos dalies poraštė (sritys, miestai) ir prisijungimo puslapių nuotrauka ---
            // Inertia::once – naršyklė gauna vieną kartą ir prisimena; vėlesni perėjimai jo nebeskaičiuoja ir nesiunčia.
            // Duomenys iš cache (CatalogCache, SitePhotos), katalogo puslapiuose – jau užkrauti tos pačios užklausos metu.
            // Paskyros puslapiai (auth middleware) poraštės neturi, todėl jiems neskaičiuojam visai.
            ...($this->isAccountPage($request) ? [] : ['site' => Inertia::once(fn (): array => $this->siteLayout())]),
        ];
    }

    /**
     * Paskyros sritis – maršrutai su „auth" middleware (AppLayout). Visa kita – vieša dalis ir prisijungimas.
     */
    private function isAccountPage(Request $request): bool
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{categories: list<array{id: int, name: string, slug: string}>, cities: list<array{name: string, slug: string, name_locative: string}>, auth_photo: array{url: string, alt: string|null}|null}
     */
    private function siteLayout(): array
    {
        $catalog = app(CatalogCache::class);

        return [
            'categories' => array_map(fn (CachedCategory $root): array => $root->toLink(), $catalog->categories()->roots()),
            'cities' => array_map(fn (CachedCity $city): array => $city->toOption(), $catalog->geography()->popularCities(8)),
            'auth_photo' => app(SitePhotos::class)->forPage(SitePhotoKey::Auth)[SitePhotoKey::Auth->value],
        ];
    }
}
