<?php

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
use Illuminate\Database\Eloquent\Model;

test('kiekviena factory sukuria įrašą duomenų bazėje', function (string $model) {
    /** @var class-string<Model> $model */
    $record = $model::factory()->create();

    expect($record->exists)->toBeTrue()
        ->and($model::query()->count())->toBeGreaterThanOrEqual(1);
})->with([
    User::class, Region::class, City::class, Category::class, ProviderProfile::class,
    PortfolioItem::class, ServiceRequest::class, Offer::class, Conversation::class, Message::class,
    Review::class, CreditPackage::class, SubscriptionPlan::class, Subscription::class,
    CreditTransaction::class, Payment::class, Complaint::class,
]);

test('užklausų būsenos atitinka docs/STATES.md taisykles', function () {
    $pending = ServiceRequest::factory()->pending()->create();
    $expired = ServiceRequest::factory()->expired()->create();
    $completed = ServiceRequest::factory()->completed()->create()->load('acceptedOffer');

    expect($pending->published_at)->toBeNull()
        ->and($expired->expires_at->isPast())->toBeTrue()
        ->and($completed->completed_at)->not->toBeNull()
        ->and($completed->acceptedOffer->status->value)->toBe('accepted')
        ->and($completed->acceptedOffer->service_request_id)->toBe($completed->id);
});

test('kategorijos leaf() sukuria 3 lygių medį', function () {
    $leaf = Category::factory()->leaf()->create()->load('parent.parent');

    expect($leaf->depth)->toBe(3)
        ->and($leaf->isLeaf())->toBeTrue()
        ->and($leaf->parent->depth)->toBe(2)
        ->and($leaf->parent->parent->depth)->toBe(1)
        ->and($leaf->parent->parent->parent_id)->toBeNull();
});
