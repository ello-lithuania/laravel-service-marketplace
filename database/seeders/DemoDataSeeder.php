<?php

namespace Database\Seeders;

use Database\Seeders\Demo\ClientGenerator;
use Database\Seeders\Demo\ComplaintGenerator;
use Database\Seeders\Demo\ConversationGenerator;
use Database\Seeders\Demo\CounterSync;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\MediaGenerator;
use Database\Seeders\Demo\MonetizationGenerator;
use Database\Seeders\Demo\NotificationGenerator;
use Database\Seeders\Demo\OfferGenerator;
use Database\Seeders\Demo\ProviderGenerator;
use Database\Seeders\Demo\ReviewGenerator;
use Database\Seeders\Demo\ServiceRequestGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dideli testiniai duomenys (docs/SEEDING.md). Kiekiai – config/seeding.php × SEED_SCALE.
 *
 * Pirma viskas suplanuojama atmintyje (kas, kada, kam), tada įrašoma į DB FK tvarka masiniais INSERT.
 * Taip galima, pvz., teikėjo registracijos datą parinkti pagal jo pirmą pasiūlymą.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('users')->where('role', '!=', 'admin')->exists()) {
            throw new RuntimeException('Demo duomenys kuriami tik tuščioje DB: php artisan migrate:fresh --seed');
        }

        $started = microtime(true);
        $scale = (float) config('seeding.scale');
        $this->tuneConnection();
        $ctx = new DemoContext($scale, fn (string $line) => $this->command->line($line));

        $this->command->info(sprintf('Demo duomenys, SEED_SCALE=%s', $scale));

        $providers = new ProviderGenerator($ctx);
        $clients = new ClientGenerator($ctx);
        $providers->plan();
        $clients->plan();

        $requests = new ServiceRequestGenerator($ctx);
        $requests->plan();

        $offers = new OfferGenerator($ctx);
        $offers->plan();
        $clients->assignDates();

        $this->step('Vartotojai ir teikėjai', $started, function () use ($ctx, $clients, $providers) {
            $ctx->insert('users', $clients->users());
            $ctx->insert('users', $providers->users());
            $ctx->insert('provider_profiles', $providers->profiles());
            $ctx->insert('category_provider_profile', $providers->categoryPivot());
            $ctx->insert('city_provider_profile', $providers->cityPivot());
            $ctx->insert('portfolio_items', $providers->portfolio());
        });

        $this->step('Užklausos ir pasiūlymai', $started, function () use ($ctx, $requests, $offers) {
            $ctx->insert('service_requests', $requests->rows());
            $ctx->insert('offers', $offers->rows());
            $offers->linkAccepted();
        });

        $this->step('Pokalbiai ir žinutės', $started, function () use ($ctx) {
            $conversations = new ConversationGenerator($ctx);
            $conversations->plan();
            $ctx->insert('conversations', $conversations->conversations());
            $ctx->insert('conversation_user', $conversations->participants());
            $ctx->insert('messages', $conversations->messages());
        });

        $this->step('Atsiliepimai', $started, function () use ($ctx) {
            $reviews = new ReviewGenerator($ctx);
            $reviews->plan();
            $ctx->insert('reviews', $reviews->rows());
        });

        $this->step('Prenumeratos, mokėjimai, kreditai', $started, fn () => (new MonetizationGenerator($ctx))->run());

        $this->step('Pranešimai ir skundai', $started, function () use ($ctx) {
            $notifications = new NotificationGenerator($ctx);
            $notifications->plan();
            $ctx->insert('notifications', $notifications->rows());
            $ctx->insert('complaints', (new ComplaintGenerator($ctx))->rows());
        });

        $this->step('Skaitliukai', $started, fn () => CounterSync::run());

        // Paskutinis žingsnis: renkantis, kam duoti paveikslėlių, reikia jau suskaičiuotų atsiliepimų (CounterSync)
        if (config('seeding.media')) {
            $this->step('Paveikslėliai (SEED_MEDIA)', $started, fn () => (new MediaGenerator($ctx))->run());
        }

        $this->command->info(sprintf('Baigta per %.1f s, atminties pikas %d MB', microtime(true) - $started, memory_get_peak_usage(true) / 1_048_576));
    }

    /**
     * Seed'ui – greitis svarbiau už patikimumą: jei kompiuteris užlūš, DB vis tiek bus kuriama iš naujo.
     */
    private function tuneConnection(): void
    {
        // Pilnas mastelis atmintyje laiko ~1,8 mln. eilučių ryšius skaičių masyvuose
        ini_set('memory_limit', '2048M');

        // Testuose seed'as vyksta RefreshDatabase transakcijos viduje, o ten PRAGMA keisti negalima
        if (DB::getDriverName() === 'sqlite' && DB::transactionLevel() === 0) {
            // Nelaukti, kol kiekvienas įrašas pasieks diską, ir laikyti daugiau DB puslapių atmintyje (256 MB)
            DB::statement('PRAGMA synchronous = OFF');
            DB::statement('PRAGMA cache_size = -262144');
        }
    }

    private function step(string $title, float $started, callable $callback): void
    {
        $this->command->line(sprintf('<comment>%s</comment> (%.1f s)', $title, microtime(true) - $started));
        $callback();
    }
}
