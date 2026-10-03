<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Support\NotificationTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pranešimų varpelis ir pranešimų puslapis (lentelė notifications, database kanalas).
 * https://laravel.com/docs/13.x/notifications#accessing-the-notifications
 */
class NotificationController extends Controller
{
    /** Kiek naujausių rodyti varpelio išskleidžiamame sąraše. */
    public const LATEST = 8;

    public function index(Request $request): Response
    {
        $notifications = $this->user($request)->notifications()->paginate(20);

        return Inertia::render('notifications/Index', [
            'notifications' => NotificationResource::collection($notifications),
        ]);
    }

    /**
     * JSON varpeliui: užkraunama tik atidarius sąrašą, o ne su kiekvienu puslapiu (Vue pusėje – useHttp).
     */
    public function latest(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'notifications' => NotificationResource::collection($user->notifications()->limit(self::LATEST)->get())->resolve(),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Paspaudus pranešimą: pažymim perskaitytu ir nukreipiam ten, apie ką jis.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $this->find($request, $notification);
        $record->markAsRead();

        return redirect()->to(NotificationTarget::url($record));
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $this->find($request, $notification)->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        // Vienas UPDATE visiems neperskaitytiems, o ne kiekvienas atskirai
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /**
     * Ieškom tik tarp SAVO pranešimų – svetimo ID atveju 404 (o ne 403, kad neatskleistume, jog toks yra).
     */
    private function find(Request $request, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification */
        return $this->user($request)->notifications()->whereKey($id)->firstOrFail();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
