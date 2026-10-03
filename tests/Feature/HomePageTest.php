<?php

use Inertia\Testing\AssertableInertia as Assert;

test('pradžios puslapis atidaromas', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/Home'));
});
