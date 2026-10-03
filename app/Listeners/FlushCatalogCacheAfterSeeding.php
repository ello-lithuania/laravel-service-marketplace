<?php

namespace App\Listeners;

use App\Services\Catalog\CatalogCache;
use Illuminate\Console\Events\CommandFinished;

/**
 * Seed'ai vykdomi be model events (DatabaseSeeder → WithoutModelEvents), todėl CatalogCacheObserver
 * jų metu nesuveikia. Kad po `php artisan db:seed` ar `migrate:fresh --seed` svetainė nerodytų senų
 * kategorijų iš file ar Redis cache, išvalom katalogo cache pasibaigus šioms komandoms.
 *
 * Laravel šį listener'į randa pats (event discovery: app/Listeners + handle() parametro tipas).
 * https://laravel.com/docs/13.x/events#event-discovery
 */
class FlushCatalogCacheAfterSeeding
{
    private const COMMANDS = ['db:seed', 'migrate:fresh', 'migrate:refresh'];

    public function __construct(private readonly CatalogCache $catalog) {}

    public function handle(CommandFinished $event): void
    {
        if (in_array($event->command, self::COMMANDS, true)) {
            $this->catalog->flush();
        }
    }
}
