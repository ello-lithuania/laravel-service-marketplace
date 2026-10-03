<?php

namespace App\Support;

/**
 * Pinigų formatavimas laiškams ir admin panelei. DB pinigai saugomi sveikais centais (docs/DB_SCHEMA.md 2.7).
 */
final class Money
{
    /**
     * 125000 → „1 250 €", 2490 → „24,90 €" (lietuviškas formatas: kablelis ir tarpas tūkstančiams).
     */
    public static function format(int $cents): string
    {
        $decimals = $cents % 100 === 0 ? 0 : 2;

        return number_format($cents / 100, $decimals, ',', ' ').' €';
    }

    /**
     * Biudžeto intervalas: „100 € – 300 €", „nuo 100 €", „iki 300 €" arba „nenurodytas".
     */
    public static function range(?int $minCents, ?int $maxCents): string
    {
        return match (true) {
            $minCents !== null && $maxCents !== null => self::format($minCents).' – '.self::format($maxCents),
            $minCents !== null => 'nuo '.self::format($minCents),
            $maxCents !== null => 'iki '.self::format($maxCents),
            default => 'nenurodytas',
        };
    }
}
