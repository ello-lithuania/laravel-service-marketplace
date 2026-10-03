<?php

use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus as Status;

/**
 * Būsenų mašinos (docs/STATES.md): leidžiami perėjimai ir galutinės būsenos.
 */
test('užklausos leidžiami perėjimai atitinka STATES.md diagramą', function (Status $from, Status $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'pending → open' => [Status::Pending, Status::Open, true],
    'pending → cancelled' => [Status::Pending, Status::Cancelled, true],
    'pending → in_progress' => [Status::Pending, Status::InProgress, false],
    'open → in_progress' => [Status::Open, Status::InProgress, true],
    'open → cancelled' => [Status::Open, Status::Cancelled, true],
    'open → expired' => [Status::Open, Status::Expired, true],
    'open → completed' => [Status::Open, Status::Completed, false],
    'in_progress → completed' => [Status::InProgress, Status::Completed, true],
    'in_progress → cancelled' => [Status::InProgress, Status::Cancelled, true],
    'in_progress → open' => [Status::InProgress, Status::Open, false],
    'completed → open' => [Status::Completed, Status::Open, false],
    'expired → open' => [Status::Expired, Status::Open, false],
]);

test('galutinės užklausos būsenos – completed, cancelled, expired', function () {
    $final = array_values(array_filter(Status::cases(), fn (Status $status) => $status->isFinal()));

    expect($final)->toBe([Status::Completed, Status::Cancelled, Status::Expired]);
});

test('pasiūlymas keičiasi tik iš pending', function () {
    foreach (OfferStatus::cases() as $to) {
        expect(OfferStatus::Pending->canTransitionTo($to))->toBe($to !== OfferStatus::Pending);
    }

    foreach ([OfferStatus::Accepted, OfferStatus::Declined, OfferStatus::Withdrawn] as $final) {
        expect($final->isFinal())->toBeTrue()
            ->and($final->canTransitionTo(OfferStatus::Pending))->toBeFalse();
    }
});
