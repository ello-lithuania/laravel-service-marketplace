<?php

namespace App\Http\Middleware;

use App\Http\Resources\AuthUserResource;
use App\Services\Messaging\UnreadMessages;
use Illuminate\Http\Request;
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
        ];
    }
}
