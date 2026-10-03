<?php

namespace Tests\Support;

/**
 * Testinė RSA raktų pora vietoj tikro Paysera rakto: privačiu raktu „pasirašom kaip Paysera" (ss2),
 * o mūsų kodas parašą tikrina viešuoju. Tinklo nereikia.
 */
final class PayseraKeys
{
    /**
     * @return array{private: string, public: string}
     */
    public static function generate(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($key === false || ! openssl_pkey_export($key, $private)) {
            throw new \RuntimeException('Nepavyko sugeneruoti RSA rakto.');
        }

        $details = openssl_pkey_get_details($key);

        return ['private' => (string) $private, 'public' => (string) ($details['key'] ?? '')];
    }

    /**
     * ss2 = base64url(RSA SHA1 parašas).
     */
    public static function ss2(string $data, string $privateKey): string
    {
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA1);

        return strtr(base64_encode((string) $signature), ['+' => '-', '/' => '_']);
    }
}
