<?php

use App\Enums\PaymentStatus as Status;

/**
 * Mokėjimo būsenų perėjimai (Etapas 9, docs/DB_SCHEMA.md → payments): grąžinti galima tik apmokėtą,
 * grąžintas – galutinis.
 */
test('mokėjimo leidžiami perėjimai atitinka DB_SCHEMA lentelę', function (Status $from, Status $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'pending → paid' => [Status::Pending, Status::Paid, true],
    'pending → failed' => [Status::Pending, Status::Failed, true],
    'pending → cancelled' => [Status::Pending, Status::Cancelled, true],
    'pending → refunded' => [Status::Pending, Status::Refunded, false],
    'failed → paid' => [Status::Failed, Status::Paid, true],
    'cancelled → paid' => [Status::Cancelled, Status::Paid, true],
    'failed → refunded' => [Status::Failed, Status::Refunded, false],
    'paid → refunded' => [Status::Paid, Status::Refunded, true],
    'paid → cancelled' => [Status::Paid, Status::Cancelled, false],
    'refunded → refunded' => [Status::Refunded, Status::Refunded, false],
    'refunded → paid' => [Status::Refunded, Status::Paid, false],
]);

test('grąžintas mokėjimas – galutinė būsena', function () {
    expect(Status::Refunded->allowedTransitions())->toBe([]);
});
