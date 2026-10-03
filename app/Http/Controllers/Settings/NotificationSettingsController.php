<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationSettingsUpdateRequest;
use App\Models\User;
use App\Support\NotificationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nustatymai → „Pranešimai": kuriuos pranešimus gauti el. paštu ir varpelyje (users.notification_settings).
 */
class NotificationSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $settings = NotificationSettings::for($user)->toArray();

        return Inertia::render('settings/Notifications', [
            'groups' => array_map(fn (string $key): array => [
                'key' => $key,
                'label' => NotificationSettings::GROUPS[$key]['label'],
                'description' => NotificationSettings::GROUPS[$key]['description'],
                'channels' => $settings[$key],
            ], NotificationSettings::groupsFor($user->role)),
            'emailVerified' => $user->hasVerifiedEmail(),
        ]);
    }

    public function update(NotificationSettingsUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'notification_settings' => $request->mergedSettings(NotificationSettings::for($user)->toArray()),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('notifications.settings_saved')]);

        return to_route('notification-settings.edit');
    }
}
