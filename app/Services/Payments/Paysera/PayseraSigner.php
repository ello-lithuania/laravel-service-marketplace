<?php

namespace App\Services\Payments\Paysera;

use App\Exceptions\InvalidPaymentCallbackException;

/**
 * Paysera (WebToPay) duomenų kodavimas ir parašai – be SDK paketo, pagal https://developers.paysera.com/en/checkout/basic
 *
 * Užklausa į Paysera:  data = base64url(http_build_query(parametrai)),  sign = md5(data + slaptažodis).
 * Callback'as iš Paysera: tas pats „data" + parašai:
 *   - ss1 = md5(data + slaptažodis) – žino tik Paysera ir mes;
 *   - ss2 = RSA (SHA1) parašas Paysera privačiu raktu – tikrinamas Paysera VIEŠUOJU raktu. Patikimesnis:
 *     net nutekėjus mūsų slaptažodžiui, be Paysera privataus rakto ss2 suklastoti neįmanoma.
 * base64url: „+" → „-", „/" → „_", kad reikšmė saugiai keliautų URL'e.
 */
final readonly class PayseraSigner
{
    public function __construct(private string $password) {}

    /**
     * @param  array<string, scalar|null>  $params
     */
    public function encode(array $params): string
    {
        return strtr(base64_encode(http_build_query($params, '', '&')), ['+' => '-', '/' => '_']);
    }

    /**
     * @return array<string, string>
     *
     * @throws InvalidPaymentCallbackException
     */
    public function decode(string $data): array
    {
        $query = base64_decode(strtr($data, ['-' => '+', '_' => '/']), true);

        if ($query === false) {
            throw new InvalidPaymentCallbackException('Netinkamas Paysera „data" parametras.');
        }

        parse_str($query, $params);

        // Paliekam tik paprastas tekstines reikšmes (parse_str gali grąžinti ir masyvus)
        $result = [];

        foreach ($params as $key => $value) {
            if (is_string($value)) {
                $result[(string) $key] = $value;
            }
        }

        return $result;
    }

    /**
     * ss1 / sign: md5(data + slaptažodis).
     */
    public function sign(string $data): string
    {
        return md5($data.$this->password);
    }

    /**
     * hash_equals, o ne ===: lyginimo laikas nepriklauso nuo to, kiek simbolių sutapo (apsauga nuo „timing" atakų).
     */
    public function verifySs1(string $data, string $ss1): bool
    {
        return $this->password !== '' && hash_equals($this->sign($data), $ss1);
    }

    /**
     * ss2: RSA SHA1 parašas, tikrinamas Paysera viešuoju raktu (PEM – raktas arba sertifikatas).
     */
    public function verifySs2(string $data, string $ss2, string $publicKeyPem): bool
    {
        $signature = base64_decode(strtr($ss2, ['-' => '+', '_' => '/']), true);
        $key = openssl_pkey_get_public($publicKeyPem);

        if ($signature === false || $key === false) {
            return false;
        }

        return openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA1) === 1;
    }
}
