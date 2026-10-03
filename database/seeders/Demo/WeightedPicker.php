<?php

namespace Database\Seeders\Demo;

/**
 * Svertinis atsitiktinis parinkimas: kuo didesnis svoris, tuo dažniau parenkama reikšmė.
 * Sukaupti svoriai + dvejetainė paieška – O(log n) vienam parinkimui.
 */
final class WeightedPicker
{
    /** @var list<float> */
    private array $cumulative = [];

    private float $total = 0.0;

    /**
     * @param  list<int>  $values
     * @param  list<float|int>  $weights
     */
    public function __construct(private readonly array $values, array $weights)
    {
        foreach ($weights as $weight) {
            $this->total += max(0.0, (float) $weight);
            $this->cumulative[] = $this->total;
        }
    }

    public function count(): int
    {
        return count($this->values);
    }

    public function pick(): int
    {
        $target = mt_rand() / mt_getrandmax() * $this->total;
        $low = 0;
        $high = count($this->cumulative) - 1;

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);

            if ($this->cumulative[$mid] < $target) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $this->values[$low];
    }

    /**
     * k skirtingų reikšmių (be pasikartojimų).
     *
     * @return list<int>
     */
    public function pickUnique(int $k): array
    {
        $n = count($this->values);
        $k = min($k, $n);

        // Kai reikia didelės dalies – paprasčiau sumaišyti visus (svoriai tada nesvarbūs)
        if ($k * 2 >= $n) {
            $values = $this->values;
            shuffle($values);

            return array_slice($values, 0, $k);
        }

        $picked = [];

        while (count($picked) < $k) {
            $picked[$this->pick()] = true;
        }

        return array_keys($picked);
    }
}
