<?php

namespace App\Services\Catalog;

/**
 * Paieškos tekstas, paruoštas SQL užklausai: išvalytas, suskaidytas į žodžius, žodžiai sutrumpinti iki šaknies.
 *
 * Kodėl šaknis: lietuvių kalba linksniuojama, o nei MySQL FULLTEXT, nei LIKE galūnių nesupranta.
 * „plytelių" nerastų „plytelės", bet šaknis „plytel" randa abi formas (MySQL – kaip prefiksą „plytel*").
 */
final readonly class SearchTerms
{
    /** Trumpesni žodžiai ignoruojami – „ir", „į" nieko nepaieško. */
    public const MIN_WORD_LENGTH = 2;

    /** InnoDB FULLTEXT neindeksuoja trumpesnių žodžių (innodb_ft_min_token_size = 3). */
    public const MIN_FULLTEXT_WORD_LENGTH = 3;

    public const MAX_WORDS = 6;

    public const MAX_LENGTH = 100;

    /**
     * Dažniausios galūnės, ilgiausios pirmos. Tai supaprastintas „stemmer'is": tobulai kalbos nemoka,
     * bet daugumą paslaugų pavadinimų formų suveda į tą pačią šaknį.
     */
    private const ENDINGS = [
        'iuose',
        'iems', 'iams', 'ėmis', 'omis', 'imis', 'umis', 'ioms', 'iais', 'iose', 'iuje',
        'ams', 'ems', 'ims', 'oms', 'ėms', 'ums', 'ose', 'ėse', 'yse', 'oje', 'ėje', 'yje', 'uje',
        'iai', 'ius', 'iui', 'ias', 'iam', 'ais',
        'ių', 'iu', 'as', 'is', 'ys', 'us', 'os', 'ės', 'es', 'ai', 'ei', 'ui', 'ią', 'io', 'ia',
        'ų', 'ą', 'ę', 'į', 'ė', 'a', 'e', 'i', 'o', 'u', 'y', 's',
    ];

    /** Kiek raidžių šaknyje turi likti, kad trumpi žodžiai nepavirstų „bet kuo". */
    private const MIN_STEM_LENGTH = 4;

    /**
     * @param  string  $input  vartotojo įvestas tekstas (rodomas paieškos laukelyje)
     * @param  non-empty-list<string>  $words  išvalyti žodžių kamienai
     */
    private function __construct(public string $input, public array $words) {}

    /**
     * Null, jei tekste nėra nė vieno tinkamo žodžio.
     */
    public static function parse(?string $input): ?self
    {
        $input = mb_substr(trim((string) $input), 0, self::MAX_LENGTH);

        // Paliekam tik raides ir skaitmenis. Taip pašalinami ir MySQL boolean režimo operatoriai
        // (+ - * " ( ) ~ < > @), ir LIKE ženklai (% _), kad vartotojas negalėtų „perrašyti" užklausos.
        $clean = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($input));

        $words = [];

        foreach (explode(' ', $clean) as $word) {
            if (mb_strlen($word) >= self::MIN_WORD_LENGTH) {
                $words[] = self::stem($word);
            }
        }

        $words = array_slice(array_values(array_unique($words)), 0, self::MAX_WORDS);

        return $words === [] ? null : new self($input, $words);
    }

    /**
     * MySQL boolean režimo užklausa: „+plytel* +klijavim*" – kiekvienas žodis privalomas (+),
     * tinka bet kokia jo pabaiga (*). Null, jei nėra žodžių, kuriuos FULLTEXT indeksas apskritai indeksuoja.
     */
    public function booleanQuery(): ?string
    {
        $words = array_filter($this->words, fn (string $word): bool => mb_strlen($word) >= self::MIN_FULLTEXT_WORD_LENGTH);

        return $words === [] ? null : implode(' ', array_map(fn (string $word): string => '+'.$word.'*', $words));
    }

    private static function stem(string $word): string
    {
        foreach (self::ENDINGS as $ending) {
            if (str_ends_with($word, $ending) && mb_strlen($word) - mb_strlen($ending) >= self::MIN_STEM_LENGTH) {
                return mb_substr($word, 0, mb_strlen($word) - mb_strlen($ending));
            }
        }

        return $word;
    }
}
