<?php

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * A small drawing surface on top of GD for generated blog images. Everything is drawn at
 * twice the final size and scaled down on save, which smooths rounded corners and bars.
 * Coordinates and font sizes are given in final-image pixels.
 */
class GdCanvas
{
    private const FONTS = [
        'display' => 'Fraunces-Bold.ttf',
        'medium' => 'Inter-Medium.ttf',
        'bold' => 'Inter-Bold.ttf',
        'heavy' => 'Inter-ExtraBold.ttf',
    ];

    private const SCALE = 2;

    public GdImage $im;

    public function __construct(public readonly int $width, public readonly int $height)
    {
        $this->im = imagecreatetruecolor($width * self::SCALE, $height * self::SCALE);
        imagealphablending($this->im, true);
        imagesavealpha($this->im, false);
    }

    public static function supported(): bool
    {
        if (! function_exists('imagettftext') || ! function_exists('imagewebp')) {
            return false;
        }

        foreach (self::FONTS as $file) {
            if (! is_file(resource_path('fonts/'.$file))) {
                return false;
            }
        }

        return true;
    }

    /** "#0A1633" with an optional alpha from 0 (opaque) to 127 (transparent). */
    public function color(string $hex, int $alpha = 0): int
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return imagecolorallocatealpha($this->im, $r, $g, $b, $alpha);
    }

    public function fill(string $hex): void
    {
        imagefilledrectangle($this->im, 0, 0, $this->width * self::SCALE, $this->height * self::SCALE, $this->color($hex));
    }

    /** Vertical gradient between two colours over the whole canvas. */
    public function gradient(string $top, string $bottom): void
    {
        [$r1, $g1, $b1] = sscanf(ltrim($top, '#'), '%02x%02x%02x');
        [$r2, $g2, $b2] = sscanf(ltrim($bottom, '#'), '%02x%02x%02x');
        $h = $this->height * self::SCALE;
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($this->im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            imageline($this->im, 0, $y, $this->width * self::SCALE, $y, $c);
        }
    }

    /** The diamond lattice used across the site's dark panels. */
    public function jaali(int $alpha = 112): void
    {
        $color = $this->color('#E6BE62', $alpha);
        imagesetthickness($this->im, self::SCALE);
        $step = 56 * self::SCALE;
        for ($x = -$step; $x < ($this->width + 56) * self::SCALE; $x += $step) {
            for ($y = -$step; $y < ($this->height + 56) * self::SCALE; $y += $step) {
                $h = (int) ($step / 2);
                imagepolygon($this->im, [$x + $h, $y, $x + $step, $y + $h, $x + $h, $y + $step, $x, $y + $h], $color);
            }
        }
        imagesetthickness($this->im, 1);
    }

    public function rect(int $x1, int $y1, int $x2, int $y2, int $color): void
    {
        $s = self::SCALE;
        imagefilledrectangle($this->im, $x1 * $s, $y1 * $s, $x2 * $s - 1, $y2 * $s - 1, $color);
    }

    public function roundedRect(int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        $s = self::SCALE;
        [$x1, $y1, $x2, $y2] = [$x1 * $s, $y1 * $s, $x2 * $s, $y2 * $s];
        $r = min($radius * $s, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2));
        if ($r <= 0) {
            imagefilledrectangle($this->im, $x1, $y1, $x2 - 1, $y2 - 1, $color);

            return;
        }
        imagefilledrectangle($this->im, $x1 + $r, $y1, $x2 - $r - 1, $y2 - 1, $color);
        imagefilledrectangle($this->im, $x1, $y1 + $r, $x1 + $r - 1, $y2 - $r - 1, $color);
        imagefilledrectangle($this->im, $x2 - $r, $y1 + $r, $x2 - 1, $y2 - $r - 1, $color);
        imagefilledellipse($this->im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($this->im, $x2 - $r - 1, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($this->im, $x1 + $r, $y2 - $r - 1, $r * 2, $r * 2, $color);
        imagefilledellipse($this->im, $x2 - $r - 1, $y2 - $r - 1, $r * 2, $r * 2, $color);
    }

    /** A rounded outline: an outer shape in $border with an inner one in $inside. */
    public function outlinedRect(int $x1, int $y1, int $x2, int $y2, int $radius, int $border, int $inside, int $width = 2): void
    {
        $this->roundedRect($x1, $y1, $x2, $y2, $radius, $border);
        $this->roundedRect($x1 + $width, $y1 + $width, $x2 - $width, $y2 - $width, max(0, $radius - $width), $inside);
    }

    /** Draws text with its baseline at $y and returns its width. */
    public function text(string $font, int $size, int $x, int $y, int $color, string $text): int
    {
        $s = self::SCALE;
        if (imagettftext($this->im, $size * $s, 0, $x * $s, $y * $s, $color, $this->font($font), $text) === false) {
            throw new RuntimeException('Could not render text with '.$font);
        }

        return $this->textWidth($font, $size, $text);
    }

    public function textRight(string $font, int $size, int $right, int $y, int $color, string $text): void
    {
        $this->text($font, $size, $right - $this->textWidth($font, $size, $text), $y, $color, $text);
    }

    public function textWidth(string $font, int $size, string $text): int
    {
        $box = imagettfbbox($size * self::SCALE, 0, $this->font($font), $text);

        return $box ? (int) ceil(abs($box[2] - $box[0]) / self::SCALE) : 0;
    }

    /** The largest size (down to $min) at which the text fits in $maxWidth. */
    public function fit(string $font, int $size, string $text, int $maxWidth, int $min = 12): int
    {
        while ($size > $min && $this->textWidth($font, $size, $text) > $maxWidth) {
            $size--;
        }

        return $size;
    }

    /** Shortens text with an ellipsis until it fits. */
    public function clip(string $font, int $size, string $text, int $maxWidth): string
    {
        if ($this->textWidth($font, $size, $text) <= $maxWidth) {
            return $text;
        }
        while (mb_strlen($text) > 1 && $this->textWidth($font, $size, $text.'…') > $maxWidth) {
            $text = rtrim(mb_substr($text, 0, -1));
        }

        return $text.'…';
    }

    /**
     * @return list<string>
     */
    public function wrap(string $font, int $size, string $text, int $maxWidth): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if ($line !== '' && $this->textWidth($font, $size, $candidate) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    /** Places an image file inside a box, keeping its proportions and centring it. */
    public function picture(string $path, int $x, int $y, int $w, int $h): bool
    {
        $source = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'webp' => @imagecreatefromwebp($path),
            'png' => @imagecreatefrompng($path),
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            default => false,
        };
        if (! $source) {
            return false;
        }

        $sw = imagesx($source);
        $sh = imagesy($source);
        $ratio = min($w / $sw, $h / $sh);
        $dw = (int) round($sw * $ratio);
        $dh = (int) round($sh * $ratio);
        $s = self::SCALE;
        imagecopyresampled($this->im, $source, ($x + (int) (($w - $dw) / 2)) * $s, ($y + (int) (($h - $dh) / 2)) * $s, 0, 0, $dw * $s, $dh * $s, $sw, $sh);
        imagedestroy($source);

        return true;
    }

    /** Scales down to the final size and writes a WebP file. */
    public function saveWebp(string $path, int $quality = 84): void
    {
        $out = imagecreatetruecolor($this->width, $this->height);
        imagecopyresampled($out, $this->im, 0, 0, 0, 0, $this->width, $this->height, $this->width * self::SCALE, $this->height * self::SCALE);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $tmp = $path.'.'.getmypid().'.tmp';
        imagewebp($out, $tmp, $quality);
        imagedestroy($out);
        rename($tmp, $path);
    }

    public function destroy(): void
    {
        imagedestroy($this->im);
    }

    private function font(string $name): string
    {
        return resource_path('fonts/'.self::FONTS[$name]);
    }
}
