<?php

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

test('/up – 200, kai DB ir cache veikia', function () {
    expect(Event::hasListeners(DiagnosingHealth::class))->toBeTrue();

    $this->get('/up')->assertOk();
});

test('/up – 500, kai cache neveikia (balansuotojas serverį išjungia)', function () {
    Cache::shouldReceive('put')->andReturnTrue();
    Cache::shouldReceive('pull')->andReturnNull();

    $this->get('/up')->assertStatus(500);
});
