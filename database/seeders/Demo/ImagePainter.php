<?php

namespace Database\Seeders\Demo;

use GdImage;
use RuntimeException;

/**
 * Abstraktūs demo paveikslėliai su PHP GD (docs/SEEDING.md 6–7 sk.): spalvų perėjimai, geometrinės figūros
 * ir inicialai. Jokių nuotraukų, žmonių ar failų iš interneto – viskas nupiešiama vietoje.
 *
 * Atsitiktinumas – mt_rand(): kviečiantysis jį „užsėja", todėl ta pati sėkla duoda tuos pačius paveikslėlius.
 * Spalvos – paletė iš 4 spalvų: [tamsi, pagrindinė, šviesi, akcentas].
 */
final class ImagePainter
{
    public const PORTFOLIO_WIDTH = 960;

    public const PORTFOLIO_HEIGHT = 720;

    public const COVER_WIDTH = 1200;

    public const COVER_HEIGHT = 400;

    public const LOGO_SIZE = 400;

    /** Šriftas inicialams: DejaVu Sans turi visas lietuviškas raides (Š, Ž, Č…), o jį jau atsiveža dompdf. */
    private const FONT = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';

    public function __construct(private readonly string $directory)
    {
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Nepavyko sukurti katalogo {$directory}");
        }
    }

    /**
     * Portfolio „nuotrauka" 960×720 (4:3, kaip telefono kameros): sulieti spalvų dėmių fonas ir ryškios figūros.
     *
     * @param  list<string>  $palette
     */
    public function portfolio(array $palette, string $name): string
    {
        $image = $this->softBackground(self::PORTFOLIO_WIDTH, self::PORTFOLIO_HEIGHT, $palette, 7);
        $this->crispShapes($image, $palette);

        return $this->saveJpeg($image, $name);
    }

    /**
     * Viršelis 1200×400 (3:1, kaip profilio puslapyje): fonas ir kelios „bangos".
     *
     * @param  list<string>  $palette
     */
    public function cover(array $palette, string $name): string
    {
        $image = $this->softBackground(self::COVER_WIDTH, self::COVER_HEIGHT, $palette, 5);
        $this->waves($image);

        return $this->saveJpeg($image, $name);
    }

    /**
     * Logotipas 400×400: plokščia spalva, viena geometrinė figūra ir inicialai.
     * PNG, nes plokščios spalvos ir tekstas JPEG formate „sutepami", o PNG juos suspaudžia geriau.
     *
     * @param  list<string>  $palette
     */
    public function logo(string $initials, array $palette, int $shape, string $name): string
    {
        $size = self::LOGO_SIZE;
        $image = $this->canvas($size, $size);
        [$dark, $main, $light] = array_map(fn (string $hex) => $this->rgb($hex), $palette);
        $onDark = $shape % 2 === 0;
        $background = $onDark ? $dark : $main;

        imagefilledrectangle($image, 0, 0, $size, $size, $this->color($image, $background));
        // Figūra – fono atspalvis (šviesesnis ant tamsaus, tamsesnis ant pagrindinės spalvos), ne kita spalva:
        // vienspalvis logotipas atrodo tvarkingiau
        $figure = $this->color($image, $this->mix($background, $onDark ? $light : $dark, 0.3));
        $center = intdiv($size, 2);

        match ($shape % 5) {
            // Didelis apskritimas, išlindęs už kampo
            0 => imagefilledellipse($image, (int) ($size * 0.8), (int) ($size * 0.2), (int) ($size * 1.1), (int) ($size * 1.1), $figure),
            // Įstrižainė: viršutinis kairys trikampis
            1 => imagefilledpolygon($image, [0, 0, $size, 0, 0, $size], $figure),
            // Rombas centre
            2 => imagefilledpolygon($image, [$center, 30, $size - 30, $center, $center, $size - 30, 30, $center], $figure),
            // Žiedai
            3 => $this->rings($image, $center, $center, $figure),
            // Juostos
            default => $this->stripes($image, $size, $size, $figure, 48),
        };

        $this->initials($image, $initials, $size);

        return $this->save($image, $name.'.png', fn (GdImage $img, string $path) => imagepng($img, $path, 9));
    }

    // --- Fonas ------------------------------------------------------------------------------

    /**
     * Mažame paveikslėlyje nupiešiamos spalvų dėmės, sulieiamos ir padidinamos – gaunamas minkštas „bokeh" fonas.
     * Piešti iškart dideliame būtų lėta: PHP ciklas per 700 000 taškų trunka sekundes, o GD funkcijos – milisekundes.
     *
     * @param  list<string>  $palette
     */
    private function softBackground(int $width, int $height, array $palette, int $blobs): GdImage
    {
        $scale = 8;
        $small = $this->canvas(intdiv($width, $scale), intdiv($height, $scale));
        $w = imagesx($small);
        $h = imagesy($small);
        $colors = array_map(fn (string $hex) => $this->rgb($hex), $palette);

        // Įstrižas perėjimas iš tamsios į pagrindinę spalvą (be dėmių per vidurį būtų nuobodu)
        $from = $colors[mt_rand(0, 1)];
        $to = $colors[mt_rand(1, 2)];

        for ($x = 0; $x < $w + $h; $x++) {
            $t = $x / ($w + $h);
            imageline($small, $x, 0, $x - $h, $h, $this->color($small, $this->mix($from, $to, $t)));
        }

        for ($i = 0; $i < $blobs; $i++) {
            $radius = mt_rand(intdiv($h, 4), $h);
            imagefilledellipse(
                $small,
                mt_rand(0, $w),
                mt_rand(0, $h),
                $radius,
                $radius,
                $this->color($small, $colors[mt_rand(0, 3)], mt_rand(30, 80)),
            );
        }

        for ($i = 0; $i < 6; $i++) {
            imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        }

        // Bilinear padidinimas – sklandūs perėjimai be „laiptų"
        $image = imagescale($small, $width, $height, IMG_BILINEAR_FIXED);

        if ($image === false) {
            throw new RuntimeException('Nepavyko padidinti paveikslėlio');
        }

        imagealphablending($image, true);

        return $image;
    }

    // --- Figūros ------------------------------------------------------------------------------

    /**
     * Ryškios, pusiau permatomos figūros ant fono: apskritimai, pasukti kvadratai, taškų tinklelis.
     *
     * @param  list<string>  $palette
     */
    private function crispShapes(GdImage $image, array $palette): void
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $colors = array_map(fn (string $hex) => $this->rgb($hex), $palette);

        for ($i = 0, $count = mt_rand(2, 4); $i < $count; $i++) {
            $color = $this->color($image, $colors[mt_rand(2, 3)], mt_rand(55, 95));
            $diameter = mt_rand(80, 360);

            match (mt_rand(0, 3)) {
                0 => imagefilledellipse($image, mt_rand(0, $w), mt_rand(0, $h), $diameter, $diameter, $color),
                1 => imagefilledpolygon($image, $this->rotatedSquare(mt_rand(0, $w), mt_rand(0, $h), mt_rand(60, 220), mt_rand(0, 89)), $color),
                2 => $this->rings($image, mt_rand(0, $w), mt_rand(0, $h), $color),
                default => $this->dots($image, mt_rand(0, $w - 200), mt_rand(0, $h - 200), $color),
            };
        }

        // Plona balta linija per visą paveikslėlį – „kompozicijos" akcentas
        imagesetthickness($image, 3);
        $y = mt_rand(intdiv($h, 4), intdiv($h * 3, 4));
        imageline($image, 0, $y, $w, $y + mt_rand(-200, 200), $this->color($image, [255, 255, 255], 80));
        imagesetthickness($image, 1);
    }

    private function waves(GdImage $image): void
    {
        $w = imagesx($image);
        $h = imagesy($image);
        imagesetthickness($image, 2);

        for ($i = 0, $count = mt_rand(3, 5); $i < $count; $i++) {
            $base = mt_rand(intdiv($h, 5), intdiv($h * 4, 5));
            $amplitude = mt_rand(15, 60);
            $length = mt_rand(250, 600);
            $phase = mt_rand(0, 628) / 100;
            $color = $this->color($image, [255, 255, 255], mt_rand(55, 95));
            $previous = null;

            for ($x = 0; $x <= $w; $x += 8) {
                $y = (int) ($base + $amplitude * sin($phase + $x / $length * 2 * M_PI));

                if ($previous !== null) {
                    imageline($image, $previous[0], $previous[1], $x, $y, $color);
                }

                $previous = [$x, $y];
            }
        }

        imagesetthickness($image, 1);
    }

    private function rings(GdImage $image, int $x, int $y, int $color): void
    {
        $radius = mt_rand(60, 160);

        // imageellipse nepaiso imagesetthickness, todėl storis – keli gretimi apskritimai
        for ($ring = 0; $ring < 3; $ring++) {
            $r = $radius + $ring * 34;

            for ($t = 0; $t < 6; $t++) {
                imageellipse($image, $x, $y, 2 * $r + $t, 2 * $r + $t, $color);
            }
        }
    }

    private function dots(GdImage $image, int $x, int $y, int $color): void
    {
        for ($row = 0; $row < 6; $row++) {
            for ($col = 0; $col < 6; $col++) {
                imagefilledellipse($image, $x + $col * 34, $y + $row * 34, 10, 10, $color);
            }
        }
    }

    private function stripes(GdImage $image, int $w, int $h, int $color, int $step): void
    {
        for ($x = -$h; $x < $w; $x += 2 * $step) {
            imagefilledpolygon($image, [$x, $h, $x + $step, $h, $x + $step + $h, 0, $x + $h, 0], $color);
        }
    }

    /**
     * @return list<int> kvadrato, pasukto $angle laipsnių, viršūnės
     */
    private function rotatedSquare(int $cx, int $cy, int $half, int $angle): array
    {
        $points = [];

        for ($corner = 0; $corner < 4; $corner++) {
            $a = deg2rad($angle + 45 + 90 * $corner);
            $points[] = (int) ($cx + $half * M_SQRT2 * cos($a));
            $points[] = (int) ($cy + $half * M_SQRT2 * sin($a));
        }

        return $points;
    }

    // --- Inicialai ------------------------------------------------------------------------------

    private function initials(GdImage $image, string $initials, int $size): void
    {
        $font = base_path(self::FONT);
        $white = $this->color($image, [255, 255, 255]);

        if (! is_file($font)) {
            // Atsarginis variantas: GD įtaisytas šriftas (be lietuviškų raidžių), padidintas
            $this->builtinInitials($image, $initials, $size);

            return;
        }

        $fontSize = mb_strlen($initials) > 1 ? 120 : 150;
        $box = imagettfbbox($fontSize, 0, $font, $initials);

        if ($box === false) {
            return;
        }

        // bbox: [kairė apačia x, y, dešinė apačia x, y, dešinė viršus x, y, kairė viršus x, y]
        $x = (int) (($size - ($box[2] - $box[0])) / 2 - $box[0]);
        $y = (int) (($size - ($box[1] - $box[7])) / 2 - $box[7]);

        // Šešėlis – kad inicialai matytųsi ir ant šviesios figūros
        imagettftext($image, $fontSize, 0, $x + 4, $y + 4, $this->color($image, [0, 0, 0], 90), $font, $initials);
        imagettftext($image, $fontSize, 0, $x, $y, $white, $font, $initials);
    }

    private function builtinInitials(GdImage $image, string $initials, int $size): void
    {
        $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT', $initials);
        $font = 5;
        $small = $this->canvas(imagefontwidth($font) * strlen($text) + 2, imagefontheight($font) + 2);
        imagealphablending($small, false);
        imagefilledrectangle($small, 0, 0, imagesx($small), imagesy($small), $this->color($small, [0, 0, 0], 127));
        imagestring($small, $font, 1, 1, $text, $this->color($small, [255, 255, 255]));
        $height = intdiv($size, 3);
        $width = (int) ($height * imagesx($small) / imagesy($small));
        imagecopyresampled($image, $small, intdiv($size - $width, 2), intdiv($size - $height, 2), 0, 0, $width, $height, imagesx($small), imagesy($small));
    }

    // --- Pagalbinės --------------------------------------------------------------------------------

    private function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));

        if ($image === false) {
            throw new RuntimeException('GD nepavyko sukurti paveikslėlio');
        }

        imagealphablending($image, true);

        return $image;
    }

    /**
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $rgb
     * @param  int  $alpha  0 – nepermatoma, 127 – visiškai permatoma
     */
    private function color(GdImage $image, array $rgb, int $alpha = 0): int
    {
        return (int) imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], max(0, min(127, $alpha)));
    }

    /**
     * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}
     */
    private function rgb(string $hex): array
    {
        $value = (int) hexdec(ltrim($hex, '#'));

        return [($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF];
    }

    /**
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $from
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $to
     * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}
     */
    private function mix(array $from, array $to, float $t): array
    {
        return [
            max(0, min(255, (int) round($from[0] + ($to[0] - $from[0]) * $t))),
            max(0, min(255, (int) round($from[1] + ($to[1] - $from[1]) * $t))),
            max(0, min(255, (int) round($from[2] + ($to[2] - $from[2]) * $t))),
        ];
    }

    private function saveJpeg(GdImage $image, string $name): string
    {
        // Interlace – progresyvus JPEG: lėtame ryšyje paveikslėlis ryškėja palaipsniui
        imageinterlace($image, true);

        return $this->save($image, $name.'.jpg', fn (GdImage $img, string $path) => imagejpeg($img, $path, 82));
    }

    /**
     * @param  callable(GdImage, string): bool  $writer
     */
    private function save(GdImage $image, string $fileName, callable $writer): string
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.$fileName;

        if (! $writer($image, $path)) {
            throw new RuntimeException("Nepavyko įrašyti {$path}");
        }

        return $path;
    }
}
