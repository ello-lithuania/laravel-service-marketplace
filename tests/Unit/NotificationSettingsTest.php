<?php

use App\Enums\UserRole;
use App\Support\NotificationSettings;

test('NULL nustatymai = viskas įjungta', function () {
    $settings = NotificationSettings::fromArray(null);

    expect($settings->toArray())->toBe(NotificationSettings::defaults())
        ->and($settings->channels('new_requests'))->toBe(['mail', 'database']);
});

test('išsaugotos reikšmės perrašo numatytąsias, nežinomi raktai ir ne bool ignoruojami', function () {
    $settings = NotificationSettings::fromArray([
        'new_requests' => ['mail' => false],
        'offer_updates' => ['database' => 'ne'],
        'nezinoma' => ['mail' => false],
    ]);

    expect($settings->wants('new_requests', 'mail'))->toBeFalse()
        ->and($settings->wants('new_requests', 'database'))->toBeTrue()
        ->and($settings->wants('offer_updates', 'database'))->toBeTrue()
        ->and($settings->toArray())->not->toHaveKey('nezinoma')
        ->and($settings->wants('nezinoma', 'mail'))->toBeFalse();
});

test('grupės pagal rolę', function () {
    expect(NotificationSettings::groupsFor(UserRole::Provider))->toBe(['new_requests', 'offer_updates', 'messages', 'reviews', 'billing'])
        ->and(NotificationSettings::groupsFor(UserRole::Client))->toBe(['new_offers', 'request_updates', 'messages', 'reviews'])
        ->and(NotificationSettings::groupsFor(UserRole::Admin))->toBe([]);
});
