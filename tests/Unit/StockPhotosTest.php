<?php

use App\Services\Photos\Exceptions\InvalidPhoto;
use App\Services\Photos\PhotoCredit;
use App\Services\Photos\PhotoDownloader;
use App\Services\Photos\PhotoFetcher;
use App\Services\Photos\PhotoSpec;
use Tests\Support\StockPhotos;

/*
 * Etapas 10: nuotraukų atsisiuntimo „smulkmenos" be Laravel aplikacijos – grynos funkcijos.
 */

test('PhotoCredit: tekstai be HTML, nuorodos tik http(s)', function () {
    $credit = new PhotoCredit(
        author: '  <script>x</script>Jonas   Jonaitis ',
        authorUrl: 'javascript:alert(1)',
        source: 'Flickr',
        sourceUrl: 'https://www.flickr.com/photos/jonas/1',
        license: 'CC BY 2.0',
        licenseUrl: 'ftp://example.org/license',
        title: str_repeat('Labai ilgas pavadinimas ', 20),
    );

    expect($credit->author)->toBe('xJonas Jonaitis')
        ->and($credit->authorUrl)->toBeNull()
        ->and($credit->sourceUrl)->toBe('https://www.flickr.com/photos/jonas/1')
        ->and($credit->licenseUrl)->toBeNull()
        ->and(mb_strlen((string) $credit->title))->toBeLessThanOrEqual(161)
        ->and($credit->summary())->toBe('xJonas Jonaitis · Flickr · CC BY 2.0');
});

test('PhotoCredit::fromArray: be šaltinio ar licencijos – null (sugadintas įrašas)', function () {
    expect(PhotoCredit::fromArray(['author' => 'A', 'license' => 'CC0 1.0']))->toBeNull()
        ->and(PhotoCredit::fromArray(['source' => 'Pexels', 'license' => 'Pexels License'])?->author)->toBeNull()
        ->and(PhotoCredit::fromArray(['source' => 'Pexels', 'license' => 'Pexels License'])?->summary())
        ->toBe('Autorius nenurodytas · Pexels · Pexels License');
});

test('žmonių euristika: žodžiai, ne raidžių junginiai', function (string $text, bool $people) {
    expect(PhotoFetcher::mentionsPeople($text))->toBe($people);
})->with([
    ['Man fixing a pipe under the sink', true],
    ["Woman's hands painting a wall", true],
    ['Group of people in the garden', true],
    ['Manicure with red nail polish', false],
    ['Renovated bathroom with grey tiles', false],
    ['Human-made stone wall in Germany', false],
]);

test('PhotoDownloader::validate: tikras JPEG tinka, GIF ir per maža nuotrauka – ne', function () {
    $downloader = new PhotoDownloader('test-agent');
    $spec = new PhotoSpec(minWidth: 800, minHeight: 600, maxDimension: 1600, avoidPeople: false);

    $photo = $downloader->validate(StockPhotos::jpeg(1000, 700), $spec);
    expect($photo->mimeType)->toBe('image/jpeg')
        ->and($photo->extension())->toBe('jpg')
        ->and([$photo->width, $photo->height])->toBe([1000, 700]);

    $gif = imagecreatetruecolor(1000, 700);
    ob_start();
    imagegif($gif);
    $gifBytes = (string) ob_get_clean();

    expect(fn () => $downloader->validate($gifBytes, $spec))->toThrow(InvalidPhoto::class, 'ne JPEG, PNG ar WEBP')
        ->and(fn () => $downloader->validate(StockPhotos::jpeg(640, 480), $spec))->toThrow(InvalidPhoto::class, 'per maža')
        ->and(fn () => $downloader->validate('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', $spec))
        ->toThrow(InvalidPhoto::class);
});
