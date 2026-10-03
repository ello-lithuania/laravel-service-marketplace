<?php

use App\Services\Catalog\SearchTerms;

test('tuščias ar per trumpas tekstas paieškos nesukuria', function (?string $input) {
    expect(SearchTerms::parse($input))->toBeNull();
})->with([
    'null' => [null],
    'tarpai' => ['   '],
    'vienos raidės žodžiai' => ['a ž'],
    'tik ženklai' => ['%%% +*()'],
]);

test('žodžiai sutrumpinami iki šaknies, kad tiktų kitos linksnių formos', function (string $input, array $words) {
    expect(SearchTerms::parse($input)?->words)->toBe($words);
})->with([
    'kilmininkas' => ['Plytelių klijavimas', ['plytel', 'klijavim']],
    'galininkas ir vietininkas' => ['plyteles vonioje', ['plytel', 'voni']],
    'didžiosios raidės ir galūnės' => ['SANTECHNIKAS Kaune', ['santechnik', 'kaun']],
    'trumpas žodis lieka' => ['IT pagalba', ['it', 'pagalb']],
    'dublikatai pašalinami' => ['langai langų', ['lang']],
]);

test('MySQL boolean režimo ženklai ir LIKE simboliai pašalinami', function () {
    $terms = SearchTerms::parse('+dažymas -"vidaus" (sienų)* 100%_');

    expect($terms?->words)->toBe(['dažym', 'vida', 'sien', '100'])
        ->and($terms?->input)->toBe('+dažymas -"vidaus" (sienų)* 100%_');
});

test('boolean užklausoje – tik FULLTEXT indeksuojami žodžiai su prefikso ženklu', function () {
    expect(SearchTerms::parse('IT kompiuterių remontas')?->booleanQuery())->toBe('+kompiuter* +remont*')
        ->and(SearchTerms::parse('IT')?->booleanQuery())->toBeNull();
});

test('žodžių skaičius ir teksto ilgis apriboti', function () {
    $terms = SearchTerms::parse(str_repeat('žodis ', 50));
    expect($terms?->words)->toBe(['žodi']);

    $many = SearchTerms::parse('vienas du trys keturi penki šeši septyni aštuoni');
    expect($many?->words)->toHaveCount(SearchTerms::MAX_WORDS)
        ->and(mb_strlen((string) SearchTerms::parse(str_repeat('a', 300))?->input))->toBe(SearchTerms::MAX_LENGTH);
});
