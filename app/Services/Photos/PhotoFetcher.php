<?php

namespace App\Services\Photos;

use App\Services\Photos\Exceptions\InvalidPhoto;
use App\Services\Photos\Exceptions\PhotoSearchFailed;
use App\Services\Photos\Exceptions\ProviderUnavailable;
use App\Services\Photos\Providers\StockPhotoProvider;
use Generator;
use Illuminate\Http\Client\ConnectionException;

/**
 * Randa ir atsisiunčia nuotraukas: šaltiniai iš eilės (Pexels → Openverse), kiekvienam – frazės iš eilės,
 * kiekvienai frazei – rezultatai pagal aktualumą, kol atsisiųsta tinkama nuotrauka.
 *
 * photos() – generatorius (yield): nuotrauka atsisiunčiama tik tada, kai jos paprašoma. Kategorijai užtenka
 * pirmos (first()), portfolio rinkiniui imama tiek, kiek reikia – kiti rezultatai net neatsisiunčiami.
 * Tinklo klaidos neatsiunčia komandos: problema užrašoma (problems()) ir bandomas kitas rezultatas.
 */
final class PhotoFetcher
{
    /**
     * Žodžiai, rodantys, kad nuotraukoje greičiausiai yra žmogus (aprašymas ar žymos angliškai).
     */
    private const PEOPLE_WORDS = [
        'man', 'men', 'woman', 'women', 'person', 'people', 'boy', 'boys', 'girl', 'girls', 'child', 'children',
        'kid', 'kids', 'baby', 'guy', 'guys', 'lady', 'ladies', 'couple', 'family', 'portrait', 'face', 'faces',
        'selfie', 'smiling', 'smile', 'worker', 'workers', 'he', 'she', 'his', 'her', 'businessman', 'businesswoman',
        'plumber', 'electrician', 'mechanic', 'carpenter', 'builder', 'technician', 'hairdresser', 'barber',
        'teacher', 'student', 'students', 'driver', 'bride', 'groom', 'crowd', 'customer',
    ];

    /** @var array<string, string> šaltinis => kodėl daugiau nebenaudojamas (limitas, raktas) */
    private array $unavailable = [];

    /** @var list<string> */
    private array $problems = [];

    /**
     * @param  list<StockPhotoProvider>  $providers  pirmenybės tvarka
     */
    public function __construct(
        private readonly array $providers,
        private readonly PhotoDownloader $downloader,
    ) {}

    /**
     * @param  list<string>  $queries
     * @param  array<string, true>  $exclude  jau naudojamų nuotraukų stock_id
     * @return Generator<int, FetchedPhoto>
     */
    public function photos(array $queries, PhotoSpec $spec, array $exclude = []): Generator
    {
        $this->problems = [];
        $seen = $exclude;

        foreach ($this->providers as $provider) {
            $providerSpec = $provider->avoidsPeople() ? $spec->withoutPeople() : $spec;

            foreach ($queries as $query) {
                if (isset($this->unavailable[$provider->label()])) {
                    break;
                }

                try {
                    $candidates = $provider->search($query, $providerSpec);
                } catch (ProviderUnavailable $e) {
                    $this->unavailable[$provider->label()] = $e->getMessage();
                    $this->problems[] = $e->getMessage();
                    break;
                } catch (PhotoSearchFailed|ConnectionException $e) {
                    $this->problems[] = "{$provider->label()} „{$query}\": ".self::short($e);

                    // SSL sertifikatų klaida pati nepraeis (Windows PHP be cacert.pem) – šaltinio toliau nebandom
                    if ($e instanceof ConnectionException && preg_match('/cURL error (60|77)/', $e->getMessage())) {
                        $this->unavailable[$provider->label()] = "{$provider->label()}: nepavyksta patikrinti HTTPS sertifikato (cURL error 60)";

                        break;
                    }

                    continue;
                }

                foreach ($candidates as $candidate) {
                    if (isset($seen[$candidate->stockId()]) || ! $this->suits($candidate, $providerSpec)) {
                        continue;
                    }

                    $seen[$candidate->stockId()] = true;

                    try {
                        $file = $this->downloader->download($candidate->downloadUrl, $providerSpec);
                    } catch (InvalidPhoto|ConnectionException $e) {
                        $this->problems[] = "{$provider->label()} {$candidate->id}: ".self::short($e);

                        continue;
                    }

                    yield new FetchedPhoto($candidate, $file);
                }
            }
        }
    }

    /**
     * @param  list<string>  $queries
     * @param  array<string, true>  $exclude
     */
    public function first(array $queries, PhotoSpec $spec, array $exclude = []): ?FetchedPhoto
    {
        foreach ($this->photos($queries, $spec, $exclude) as $photo) {
            return $photo;
        }

        return null;
    }

    /**
     * Paskutinio photos()/first() kvietimo problemos (tinklo klaidos, netinkami failai) – komanda jas parodo.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        return $this->problems;
    }

    /**
     * @return array<string, string>
     */
    public function unavailable(): array
    {
        return $this->unavailable;
    }

    /**
     * Ar visi šaltiniai jau nebenaudojami – tada toliau bandyti nėra prasmės.
     */
    public function exhausted(): bool
    {
        return count($this->unavailable) >= count($this->providers);
    }

    /**
     * Euristika: aprašyme ar žymose yra žodis „man", „woman", „people"… Tikslumas ribotas (aprašymas ne visada
     * pasako, kas nuotraukoje), todėl demo portfolio frazės ir taip parinktos rodyti daiktus, ne žmones.
     */
    public static function mentionsPeople(string $text): bool
    {
        $words = preg_split('/[^a-z]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_intersect($words, self::PEOPLE_WORDS) !== [];
    }

    private function suits(StockPhoto $candidate, PhotoSpec $spec): bool
    {
        // Matmenys žinomi iš API – per mažos net neatsisiunčiam (atsisiuntus tikrinama dar kartą)
        if (($candidate->width !== null && $candidate->width < $spec->minWidth)
            || ($candidate->height !== null && $candidate->height < $spec->minHeight)) {
            return false;
        }

        return ! ($spec->avoidPeople && self::mentionsPeople($candidate->description));
    }

    private static function short(\Throwable $e): string
    {
        // cURL klaidos ilgos (su visu URL) – užtenka pradžios
        return mb_strimwidth($e->getMessage(), 0, 160, '…');
    }
}
