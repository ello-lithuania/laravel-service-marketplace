<?php

namespace App\Services\Photos\Providers;

use Illuminate\Support\Sleep;

/**
 * Pauzė tarp API užklausų, kad neviršytume nemokamo limito (Openverse be rakto – ~20 per minutę).
 * Sleep, o ne sleep(): testuose Sleep::fake() pauzes tik užfiksuoja, o ne iš tikrųjų laukia.
 */
trait ThrottlesRequests
{
    private ?float $lastRequestAt = null;

    protected function throttle(int $pauseMs): void
    {
        if ($this->lastRequestAt !== null && $pauseMs > 0) {
            $wait = $pauseMs - (int) round((microtime(true) - $this->lastRequestAt) * 1000);

            if ($wait > 0) {
                Sleep::for($wait)->milliseconds();
            }
        }

        $this->lastRequestAt = microtime(true);
    }
}
