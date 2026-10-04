<?php

use App\Support\AmountInWords;

/*
 * Suma žodžiais lietuviškai (Etapas 9): skaičiai, daiktavardžių formos ir sąskaitos formatas.
 * Dataset'ai – daug atvejų viename teste; Pest kiekvieną parodo atskira eilute.
 * https://pestphp.com/docs/datasets
 */

test('skaičiai žodžiais', function (int $number, string $words) {
    expect(AmountInWords::number($number))->toBe($words);
})->with([
    [0, 'nulis'],
    [1, 'vienas'],
    [2, 'du'],
    [3, 'trys'],
    [4, 'keturi'],
    [5, 'penki'],
    [6, 'šeši'],
    [7, 'septyni'],
    [8, 'aštuoni'],
    [9, 'devyni'],
    [10, 'dešimt'],
    [11, 'vienuolika'],
    [12, 'dvylika'],
    [13, 'trylika'],
    [14, 'keturiolika'],
    [15, 'penkiolika'],
    [16, 'šešiolika'],
    [17, 'septyniolika'],
    [18, 'aštuoniolika'],
    [19, 'devyniolika'],
    [20, 'dvidešimt'],
    [21, 'dvidešimt vienas'],
    [30, 'trisdešimt'],
    [40, 'keturiasdešimt'],
    [45, 'keturiasdešimt penki'],
    [50, 'penkiasdešimt'],
    [60, 'šešiasdešimt'],
    [70, 'septyniasdešimt'],
    [80, 'aštuoniasdešimt'],
    [99, 'devyniasdešimt devyni'],
    [100, 'šimtas'],
    [101, 'šimtas vienas'],
    [110, 'šimtas dešimt'],
    [111, 'šimtas vienuolika'],
    [123, 'šimtas dvidešimt trys'],
    [200, 'du šimtai'],
    [305, 'trys šimtai penki'],
    [999, 'devyni šimtai devyniasdešimt devyni'],
    [1000, 'tūkstantis'],
    [1001, 'tūkstantis vienas'],
    [1250, 'tūkstantis du šimtai penkiasdešimt'],
    [2000, 'du tūkstančiai'],
    [5000, 'penki tūkstančiai'],
    [10000, 'dešimt tūkstančių'],
    [11000, 'vienuolika tūkstančių'],
    [12345, 'dvylika tūkstančių trys šimtai keturiasdešimt penki'],
    [21000, 'dvidešimt vienas tūkstantis'],
    [22000, 'dvidešimt du tūkstančiai'],
    [100000, 'šimtas tūkstančių'],
    [101000, 'šimtas vienas tūkstantis'],
    [111000, 'šimtas vienuolika tūkstančių'],
    [999999, 'devyni šimtai devyniasdešimt devyni tūkstančiai devyni šimtai devyniasdešimt devyni'],
    [1000000, 'milijonas'],
    [1000001, 'milijonas vienas'],
    [1001000, 'milijonas tūkstantis'],
    [2000000, 'du milijonai'],
    [10000000, 'dešimt milijonų'],
    [21500000, 'dvidešimt vienas milijonas penki šimtai tūkstančių'],
    [1000000000, 'milijardas'],
    [3000000000, 'trys milijardai'],
    [-5, 'minus penki'],
]);

test('daiktavardžio forma pagal paskutinius skaitmenis', function (int $number, string $euro, string $cent) {
    expect(AmountInWords::noun($number, AmountInWords::EURO))->toBe($euro)
        ->and(AmountInWords::noun($number, AmountInWords::CENT))->toBe($cent);
})->with([
    [0, 'eurų', 'centų'],
    [1, 'euras', 'centas'],
    [2, 'eurai', 'centai'],
    [9, 'eurai', 'centai'],
    [10, 'eurų', 'centų'],
    [11, 'eurų', 'centų'],
    [15, 'eurų', 'centų'],
    [19, 'eurų', 'centų'],
    [20, 'eurų', 'centų'],
    [21, 'euras', 'centas'],
    [22, 'eurai', 'centai'],
    [100, 'eurų', 'centų'],
    [101, 'euras', 'centas'],
    [111, 'eurų', 'centų'],
    [112, 'eurų', 'centų'],
    [121, 'euras', 'centas'],
    [1000, 'eurų', 'centų'],
    [-1, 'euras', 'centas'],
]);

test('suma eurais sąskaitai: eurai žodžiais, centai – du skaitmenys', function (int $cents, string $words) {
    expect(AmountInWords::eur($cents))->toBe($words);
})->with([
    'nulis' => [0, 'nulis eurų 00 ct'],
    'tik centai' => [45, 'nulis eurų 45 ct'],
    'vienas centas' => [1, 'nulis eurų 01 ct'],
    'vienas euras' => [100, 'vienas euras 00 ct'],
    'du eurai' => [200, 'du eurai 00 ct'],
    'dešimt eurų' => [1000, 'dešimt eurų 00 ct'],
    'vienuolika eurų' => [1100, 'vienuolika eurų 00 ct'],
    'dvidešimt vienas euras' => [2100, 'dvidešimt vienas euras 00 ct'],
    'kreditų paketas' => [2420, 'dvidešimt keturi eurai 20 ct'],
    'prenumerata' => [2690, 'dvidešimt šeši eurai 90 ct'],
    'šimtas dvidešimt trys' => [12345, 'šimtas dvidešimt trys eurai 45 ct'],
    'tūkstantis' => [100000, 'tūkstantis eurų 00 ct'],
    'du tūkstančiai vienas' => [200105, 'du tūkstančiai vienas euras 05 ct'],
    'vienuolika tūkstančių' => [1100099, 'vienuolika tūkstančių eurų 99 ct'],
    'milijonas' => [100000000, 'milijonas eurų 00 ct'],
    'kreditinė sąskaita' => [-2420, 'minus dvidešimt keturi eurai 20 ct'],
]);

test('per dideli skaičiai atmetami, o ne užrašomi klaidingai', function () {
    AmountInWords::number(AmountInWords::MAX + 1);
})->throws(InvalidArgumentException::class);

test('per didelė suma eurais atmetama', function () {
    AmountInWords::eur(PHP_INT_MAX);
})->throws(InvalidArgumentException::class);
