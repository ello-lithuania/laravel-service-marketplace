<?php

namespace App\Notifications\Concerns;

use App\Models\User;

/**
 * Etapas 9c: pranešimas, kurio vartotojas nustatymuose išjungti negali – paskyros ir saugumo žinia
 * (pvz. administratorius paslėpė profilį). Kaip ComplaintResolved ir DataExportReady: varpelis visada,
 * laiškas – tik patvirtintu el. pašto adresu (gal jis net ne šio žmogaus).
 *
 * Trait'as perrašo BaseNotification::via(), todėl users.notification_settings neskaitomi.
 */
trait IgnoresNotificationSettings
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->hasVerifiedEmail() ? ['database'] : ['mail', 'database'];
    }

    /**
     * Grupės nustatymuose nėra – via() jos neskaito.
     */
    public function settingsGroup(): string
    {
        return 'account';
    }
}
