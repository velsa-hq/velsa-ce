<?php

namespace App\Support;

/**
 * Deterministic mesh-gradient identity image: a given seed always yields the
 * same SVG. Default visual until a real photo is uploaded.
 */
class IdentityImage
{
    private const COLS = 6;

    private const ROWS = 4;

    public static function svg(string $seed, int $width = 1200, int $height = 800): string
    {
        $state = self::seedState($seed);
        $rand = static function () use (&$state): float {
            // xorshift32, fast and deterministic
            $state ^= ($state << 13) & 0xFFFFFFFF;
            $state ^= ($state >> 17);
            $state ^= ($state << 5) & 0xFFFFFFFF;
            $state &= 0xFFFFFFFF;

            return $state / 0xFFFFFFFF;
        };

        // two-stop palette: dark anchor + lighter accent, kept muted
        $hueA = (int) floor($rand() * 360);
        $hueB = $hueA + 20 + (int) floor($rand() * 60);
        $satA = 0.18 + $rand() * 0.10;
        $satB = $satA * (0.88 + $rand() * 0.12);
        // narrow lightness band keeps the mesh soft; hue carries the variation
        $lightDark = 0.38 + $rand() * 0.05;
        $lightLight = 0.54 + $rand() * 0.08;

        // gradient axis direction, varied per entity
        $axisX = $rand();
        $axisY = 1.0 - $axisX;

        // border points pinned to edges so triangles cover the full canvas
        $cellW = $width / self::COLS;
        $cellH = $height / self::ROWS;
        $points = [];
        for ($r = 0; $r <= self::ROWS; $r++) {
            for ($c = 0; $c <= self::COLS; $c++) {
                $x = $c * $cellW;
                $y = $r * $cellH;
                if ($c > 0 && $c < self::COLS) {
                    $x += ($rand() - 0.5) * $cellW * 0.72;
                }
                if ($r > 0 && $r < self::ROWS) {
                    $y += ($rand() - 0.5) * $cellH * 0.72;
                }
                $points[$r][$c] = [$x, $y];
            }
        }

        $polys = [];
        for ($r = 0; $r < self::ROWS; $r++) {
            for ($c = 0; $c < self::COLS; $c++) {
                $tl = $points[$r][$c];
                $tr = $points[$r][$c + 1];
                $bl = $points[$r + 1][$c];
                $br = $points[$r + 1][$c + 1];

                // alternate the split diagonal for a less regular look
                if ((($r + $c) & 1) === 0) {
                    $tris = [[$tl, $tr, $br], [$tl, $br, $bl]];
                } else {
                    $tris = [[$tl, $tr, $bl], [$tr, $br, $bl]];
                }

                foreach ($tris as $tri) {
                    $cx = ($tri[0][0] + $tri[1][0] + $tri[2][0]) / 3;
                    $cy = ($tri[0][1] + $tri[1][1] + $tri[2][1]) / 3;
                    $t = ($cx / $width) * $axisX + ($cy / $height) * $axisY;
                    $t = max(0.0, min(1.0, $t));

                    $hue = $hueA + ($hueB - $hueA) * $t;
                    $sat = $satA + ($satB - $satA) * $t;
                    $light = $lightDark + ($lightLight - $lightDark) * $t;
                    // per-facet lightness jitter defines the low-poly edges
                    $light = max(0.12, min(0.72, $light + ($rand() - 0.5) * 0.028));

                    $fill = self::hslToHex($hue, $sat, $light);
                    $pts = sprintf(
                        '%s,%s %s,%s %s,%s',
                        self::n($tri[0][0]), self::n($tri[0][1]),
                        self::n($tri[1][0]), self::n($tri[1][1]),
                        self::n($tri[2][0]), self::n($tri[2][1]),
                    );
                    // stroke == fill closes the hairline seams between facets
                    $polys[] = '<polygon points="'.$pts.'" fill="'.$fill.'" stroke="'.$fill.'" stroke-width="1"/>';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" '
            .'width="'.$width.'" height="'.$height.'" preserveAspectRatio="xMidYMid slice" role="img">'
            .implode('', $polys)
            .'</svg>';
    }

    // non-zero 32-bit xorshift seed from an arbitrary string
    private static function seedState(string $seed): int
    {
        $state = crc32($seed) & 0xFFFFFFFF;

        return $state === 0 ? 0x1A2B3C4D : $state;
    }

    // one decimal to keep the SVG small
    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }

    // h in degrees, s/l in 0..1
    private static function hslToHex(float $h, float $s, float $l): string
    {
        $h = fmod($h, 360.0) / 360.0;
        if ($h < 0) {
            $h += 1.0;
        }

        if ($s <= 0.0) {
            $v = (int) round($l * 255);

            return sprintf('#%02x%02x%02x', $v, $v, $v);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $r = self::hueToChannel($p, $q, $h + 1 / 3);
        $g = self::hueToChannel($p, $q, $h);
        $b = self::hueToChannel($p, $q, $h - 1 / 3);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($r * 255),
            (int) round($g * 255),
            (int) round($b * 255),
        );
    }

    private static function hueToChannel(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }
}
