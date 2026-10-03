<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Bendri testų nustatymai
|--------------------------------------------------------------------------
|
| Visi Feature testai naudoja Laravel TestCase (HTTP užklausos, actingAs ir t.t.)
| ir RefreshDatabase – prieš kiekvieną testą DB grąžinama į švarią būseną.
| Dokumentacija: https://pestphp.com/docs/configuring-tests
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
