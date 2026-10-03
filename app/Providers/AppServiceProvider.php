<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\City;
use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\CreditPackage;
use App\Models\CreditTransaction;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use App\Models\Region;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModels();
    }

    /**
     * Eloquent nustatymai (docs/DB_SCHEMA.md 2.13, CLAUDE.md konvencijos).
     */
    protected function configureModels(): void
    {
        // Dev'e ir testuose N+1 klaida (ryšio užkrovimas cikle) meta išimtį, o ne tyliai lėtina puslapį
        Model::preventLazyLoading(! $this->app->isProduction());

        // Polimorfiniuose *_type stulpeliuose – trumpi vardai, o ne klasių pavadinimai.
        // enforce – jei modelio nėra sąraše, gausim klaidą, o ne netyčia įrašytą klasės vardą.
        Relation::enforceMorphMap([
            'user' => User::class,
            'region' => Region::class,
            'city' => City::class,
            'category' => Category::class,
            'provider_profile' => ProviderProfile::class,
            'portfolio_item' => PortfolioItem::class,
            'service_request' => ServiceRequest::class,
            'offer' => Offer::class,
            'conversation' => Conversation::class,
            'message' => Message::class,
            'review' => Review::class,
            'credit_package' => CreditPackage::class,
            'subscription_plan' => SubscriptionPlan::class,
            'subscription' => Subscription::class,
            'credit_transaction' => CreditTransaction::class,
            'payment' => Payment::class,
            'complaint' => Complaint::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
