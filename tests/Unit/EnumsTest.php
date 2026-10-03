<?php

use Filament\Support\Contracts\HasLabel;

/**
 * Visi app/Enums enum'ai: kiekviena reikšmė turi lietuvišką pavadinimą
 * ir telpa į DB stulpelį string(20) (žr. docs/DB_SCHEMA.md 6 sk.).
 */
dataset('enums', function () {
    return collect(glob(__DIR__.'/../../app/Enums/*.php'))
        ->map(fn (string $file) => 'App\\Enums\\'.basename($file, '.php'))
        ->all();
});

test('enum turi lietuviškus pavadinimus ir trumpas reikšmes', function (string $enum) {
    expect($enum::cases())->not->toBeEmpty();

    foreach ($enum::cases() as $case) {
        expect($case)->toBeInstanceOf(HasLabel::class)
            ->and($case->label())->toBeString()->not->toBeEmpty()
            ->and($case->getLabel())->toBe($case->label())
            ->and(strlen($case->value))->toBeLessThanOrEqual(20);
    }
})->with('enums');
