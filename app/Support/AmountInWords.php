<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Suma žodžiais lietuviškai sąskaitoms faktūroms (Etapas 9): 12345 ct → „šimtas dvidešimt trys eurai 45 ct".
 *
 * Formatas – toks, kokį naudoja dauguma Lietuvos apskaitos programų: eurai žodžiais, centai skaitmenimis
 * (visada du: „05 ct", „00 ct"). Centai skaitmenimis trumpesni ir aiškesni, o sumą sunkiau „pataisyti"
 * žodžiais užrašytą eurų dalį. Neigiama suma (kreditinė sąskaita) – su žodžiu „minus".
 *
 * Lietuvių kalbos taisyklės, kurias čia įgyvendinam:
 *   - daiktavardžio forma priklauso nuo paskutinių skaitmenų: 1, 21, 101 → „euras" (vns. vardininkas);
 *     2–9, 22–29 → „eurai" (dgs. vardininkas); 0, 10–20, 30, 100, 111 → „eurų" (dgs. kilmininkas);
 *   - 11–19 („vienuolika", „dvylika"…) visada su kilmininku: „vienuolika tūkstančių";
 *   - „šimtas", „tūkstantis", „milijonas" be žodžio „vienas": 1000 → „tūkstantis", bet 21 000 →
 *     „dvidešimt vienas tūkstantis";
 *   - visi žodžiai vyriškos giminės (euras, centas, tūkstantis, milijonas – vyriškos), todėl „vienas", „du",
 *     o ne „viena", „dvi".
 *
 * Be paketų: logika trumpa, o savo klasę lengva ištestuoti (tests/Unit/AmountInWordsTest.php).
 */
final class AmountInWords
{
    /** Formos: [vienaskaitos vardininkas, daugiskaitos vardininkas, daugiskaitos kilmininkas]. */
    public const EURO = ['euras', 'eurai', 'eurų'];

    public const CENT = ['centas', 'centai', 'centų'];

    /** Didžiausias skaičius, kurį mokam užrašyti (999 milijardai…). Sąskaitoms – su didžiule atsarga. */
    public const MAX = 999_999_999_999;

    private const UNITS = ['', 'vienas', 'du', 'trys', 'keturi', 'penki', 'šeši', 'septyni', 'aštuoni', 'devyni'];

    private const TEENS = [
        'dešimt', 'vienuolika', 'dvylika', 'trylika', 'keturiolika',
        'penkiolika', 'šešiolika', 'septyniolika', 'aštuoniolika', 'devyniolika',
    ];

    private const TENS = [
        '', '', 'dvidešimt', 'trisdešimt', 'keturiasdešimt',
        'penkiasdešimt', 'šešiasdešimt', 'septyniasdešimt', 'aštuoniasdešimt', 'devyniasdešimt',
    ];

    /** Tūkstančių laipsniai: indeksas = kelinta triženklė grupė iš dešinės. */
    private const SCALES = [
        1 => ['tūkstantis', 'tūkstančiai', 'tūkstančių'],
        2 => ['milijonas', 'milijonai', 'milijonų'],
        3 => ['milijardas', 'milijardai', 'milijardų'],
    ];

    /**
     * Suma eurais sąskaitai: 2420 → „dvidešimt keturi eurai 20 ct", -2420 → „minus dvidešimt keturi eurai 20 ct".
     */
    public static function eur(int $cents): string
    {
        if (abs($cents) > self::MAX * 100 + 99) {
            throw new InvalidArgumentException('Per didelė suma: '.$cents);
        }

        if ($cents < 0) {
            return 'minus '.self::eur(-$cents);
        }

        $euros = intdiv($cents, 100);

        return sprintf('%s %s %02d ct', self::number($euros), self::noun($euros, self::EURO), $cents % 100);
    }

    /**
     * Sveikasis skaičius žodžiais (vyriška giminė): 0 → „nulis", 21 000 → „dvidešimt vienas tūkstantis".
     */
    public static function number(int $number): string
    {
        if (abs($number) > self::MAX) {
            throw new InvalidArgumentException('Per didelis skaičius: '.$number);
        }

        if ($number < 0) {
            return 'minus '.self::number(-$number);
        }

        if ($number === 0) {
            return 'nulis';
        }

        $words = [];

        // Skaidom į triženkles grupes iš dešinės: 1 234 567 → [567, 234, 1]
        for ($scale = 0; $number > 0; $scale++, $number = intdiv($number, 1000)) {
            $group = $number % 1000;

            if ($group === 0) {
                continue;
            }

            $part = match (true) {
                $scale === 0 => self::belowThousand($group),
                // „tūkstantis", „milijonas" – be „vienas"
                $group === 1 => self::SCALES[$scale][0],
                default => self::belowThousand($group).' '.self::noun($group, self::SCALES[$scale]),
            };

            array_unshift($words, $part);
        }

        return implode(' ', $words);
    }

    /**
     * Daiktavardžio forma pagal skaičių: noun(21, EURO) → „euras", noun(3, EURO) → „eurai", noun(11, EURO) → „eurų".
     *
     * @param  array{0: string, 1: string, 2: string}  $forms  [vns. vardininkas, dgs. vardininkas, dgs. kilmininkas]
     */
    public static function noun(int $number, array $forms): string
    {
        $number = abs($number);
        $lastTwo = $number % 100;
        $last = $number % 10;

        return match (true) {
            $last === 0, $lastTwo >= 11 && $lastTwo <= 19 => $forms[2],
            $last === 1 => $forms[0],
            default => $forms[1],
        };
    }

    /**
     * 1–999 žodžiais: 123 → „šimtas dvidešimt trys", 200 → „du šimtai".
     */
    private static function belowThousand(int $number): string
    {
        $hundreds = intdiv($number, 100);
        $rest = $number % 100;
        $words = [];

        if ($hundreds > 0) {
            $words[] = $hundreds === 1 ? 'šimtas' : self::UNITS[$hundreds].' šimtai';
        }

        if ($rest >= 10 && $rest <= 19) {
            $words[] = self::TEENS[$rest - 10];
        } else {
            if ($rest >= 20) {
                $words[] = self::TENS[intdiv($rest, 10)];
            }

            if ($rest % 10 > 0) {
                $words[] = self::UNITS[$rest % 10];
            }
        }

        return implode(' ', $words);
    }
}
