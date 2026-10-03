<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * /up sveikatos patikra (Etapas 8). Laravel /up maršrutas paleidžia įvykį DiagnosingHealth: jei kuris nors
 * listener'is išmeta išimtį, /up grąžina 500 ir apkrovos balansuotojas (load balancer) ar stebėjimo paslauga
 * (UptimeRobot, Better Stack…) žino, kad serveris neveikia.
 *
 * Tikrinam tai, be ko svetainė tikrai neveikia: DB ryšį ir cache (Redis). Eilės (queue) atsilikimo čia
 * sąmoningai netikrinam: užsikimšusi eilė neturi išmesti serverio iš balansuotojo – tam skirta atskira
 * stebėsena (php artisan queue:monitor, docs/DEPLOYMENT.md).
 * Laravel listener'į randa pats pagal handle() parametro tipą (event discovery).
 * https://laravel.com/docs/13.x/deployment#the-health-route
 */
class CheckApplicationHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::connection()->select('select 1');

        $key = 'health:'.Str::random(8);
        Cache::put($key, 'ok', 10);
        $value = Cache::pull($key);

        if ($value !== 'ok') {
            throw new RuntimeException('Cache neveikia: įrašyta reikšmė negrąžinta.');
        }
    }
}
