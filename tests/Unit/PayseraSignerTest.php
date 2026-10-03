<?php

use App\Exceptions\InvalidPaymentCallbackException;
use App\Services\Payments\Paysera\PayseraSigner;
use Tests\Support\PayseraKeys;

/*
 * Paysera duomenų kodavimas ir parašai (be Laravel – gryna PHP logika).
 * ss2 tikrinamas su testuose sugeneruota RSA raktų pora: privačiu raktu „pasirašom kaip Paysera",
 * viešuoju – tikrinam, kaip tai daro mūsų kodas.
 */

test('data – base64url užkoduoti parametrai, decode grąžina juos atgal', function () {
    $signer = new PayseraSigner('slaptas');
    $params = ['projectid' => '123', 'orderid' => 'abc', 'paytext' => 'Kreditų paketas „30" ąčęėįšųūž', 'amount' => 2690];

    $data = $signer->encode($params);

    expect($data)->not->toContain('+')->not->toContain('/')
        ->and($signer->decode($data))->toBe(['projectid' => '123', 'orderid' => 'abc', 'paytext' => 'Kreditų paketas „30" ąčęėįšųūž', 'amount' => '2690']);
});

test('sign ir ss1 = md5(data + slaptažodis)', function () {
    $signer = new PayseraSigner('slaptas');
    $data = $signer->encode(['orderid' => 'abc']);

    expect($signer->sign($data))->toBe(md5($data.'slaptas'))
        ->and($signer->verifySs1($data, md5($data.'slaptas')))->toBeTrue()
        ->and($signer->verifySs1($data, md5($data.'kitas')))->toBeFalse()
        ->and($signer->verifySs1($data.'x', md5($data.'slaptas')))->toBeFalse()
        // Be slaptažodžio ss1 niekada nelaikomas teisingu
        ->and((new PayseraSigner(''))->verifySs1($data, md5($data)))->toBeFalse();
});

test('ss2: teisingas RSA SHA1 parašas priimamas, pakeisti duomenys ar kitas raktas – ne', function () {
    $keys = PayseraKeys::generate();
    $other = PayseraKeys::generate();
    $signer = new PayseraSigner('slaptas');
    $data = $signer->encode(['orderid' => 'abc', 'status' => '1']);
    $ss2 = PayseraKeys::ss2($data, $keys['private']);

    expect($signer->verifySs2($data, $ss2, $keys['public']))->toBeTrue()
        ->and($signer->verifySs2($signer->encode(['orderid' => 'abc', 'status' => '0']), $ss2, $keys['public']))->toBeFalse()
        ->and($signer->verifySs2($data, $ss2, $other['public']))->toBeFalse()
        ->and($signer->verifySs2($data, 'ne-parašas', $keys['public']))->toBeFalse()
        ->and($signer->verifySs2($data, $ss2, 'ne raktas'))->toBeFalse();
});

test('netinkamas data meta išimtį', function () {
    (new PayseraSigner('slaptas'))->decode('***');
})->throws(InvalidPaymentCallbackException::class);
