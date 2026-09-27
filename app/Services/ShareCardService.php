<?php

namespace App\Services;

use App\Models\Ipo;
use GdImage;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Builds the 1200×630 image shown when an IPO page is shared on WhatsApp, X or
 * Telegram: logo, name, price band, GMP and key dates on the brand background.
 *
 * Files are written to public/og/ipo and named after a hash of the data they show,
 * so a GMP or date change produces a fresh card while unchanged ones stay cached.
 */
class ShareCardService
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const FONTS = [
        'display' => 'Fraunces-Bold.ttf',
        'medium' => 'Inter-Medium.ttf',
        'bold' => 'Inter-Bold.ttf',
        'heavy' => 'Inter-ExtraBold.ttf',
    ];

    private const STATUS_COLORS = [
        'open' => [47, 194, 142],
        'upcoming' => [124, 156, 255],
        'closed' => [242, 169, 59],
        'listed' => [148, 163, 200],
    ];

    public function __construct(private LogoService $logos) {}

    public function supported(): bool
    {
        if (! function_exists('imagettftext') || ! function_exists('imagepng')) {
            return false;
        }

        foreach (self::FONTS as $file) {
            if (! is_file($this->fontPath($file))) {
                return false;
            }
        }

        return true;
    }

    public function filename(Ipo $ipo): ?string
    {
        if (! $this->supported()) {
            return null;
        }

        $fingerprint = implode('|', [
            $ipo->name, $ipo->typeLabel(), $ipo->exchangeLabel(), $ipo->priceBand(), $ipo->gmp,
            $ipo->open_date?->toDateString(), $ipo->close_date?->toDateString(), $ipo->listing_date?->toDateString(),
            $ipo->statusLabel(), $ipo->listing_price, $ipo->image_url,
        ]);

        return $ipo->slug.'-'.substr(md5($fingerprint), 0, 10).'.png';
    }

    public function url(Ipo $ipo): ?string
    {
        $file = $this->filename($ipo);

        return $file ? asset('og/ipo/'.$file) : null;
    }

    public function path(string $file): string
    {
        return public_path('og/ipo/'.$file);
    }

    /**
     * Build the card if needed. Returns the absolute path, or null when it can't be made.
     */
    public function ensure(Ipo $ipo): ?string
    {
        $file = $this->filename($ipo);
        if (! $file) {
            return null;
        }

        $path = $this->path($file);
        if (is_file($path)) {
            return $path;
        }

        try {
            $image = $this->render($ipo);

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            foreach (glob($this->path($ipo->slug.'-*.png')) ?: [] as $old) {
                if (preg_match('/^'.preg_quote($ipo->slug, '/').'-[0-9a-f]{10}\.png$/', basename($old))) {
                    @unlink($old);
                }
            }

            $tmp = $path.'.'.getmypid().'.tmp';
            imagepng($image, $tmp, 6);
            imagedestroy($image);
            rename($tmp, $path);

            return $path;
        } catch (Throwable $e) {
            Cache::put('og-fail:'.$file, $e->getMessage(), now()->addMinutes(10));

            return null;
        }
    }

    public function render(Ipo $ipo): GdImage
    {
        $im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($im, true);
        imagesavealpha($im, false);

        $this->background($im);

        $white = imagecolorallocate($im, 255, 255, 255);
        $gold = imagecolorallocate($im, 230, 190, 98);
        $soft = imagecolorallocate($im, 183, 193, 222);
        $muted = imagecolorallocate($im, 143, 155, 189);
        $green = imagecolorallocate($im, 82, 214, 164);
        $red = imagecolorallocate($im, 255, 145, 145);

        // Brand
        $brand = public_path('images/brand/logo-160.webp');
        if (is_file($brand) && ($mark = @imagecreatefromwebp($brand))) {
            imagecopyresampled($im, $mark, 60, 42, 0, 0, 64, 64, imagesx($mark), imagesy($mark));
            imagedestroy($mark);
        }
        $this->text($im, 'display', 26, 138, 86, $gold, 'IPO Darbaar');

        // Status pill (top right)
        $status = mb_strtoupper($ipo->statusLabel());
        [$r, $g, $b] = self::STATUS_COLORS[$ipo->status()] ?? self::STATUS_COLORS['listed'];
        $pillText = imagecolorallocate($im, $r, $g, $b);
        $pillWidth = $this->textWidth('bold', 15, $status) + 44;
        $this->roundedRect($im, self::WIDTH - 60 - $pillWidth, 48, self::WIDTH - 60, 96, 24, imagecolorallocate($im, (int) (10 + $r * 0.16), (int) (22 + $g * 0.16), (int) (51 + $b * 0.16)));
        $this->text($im, 'bold', 15, self::WIDTH - 60 - $pillWidth + 22, 80, $pillText, $status);

        // Company logo tile
        $this->roundedRect($im, 60, 150, 300, 270, 22, $white);
        $this->logo($im, $ipo, 60, 150, 240, 120);

        // Name and sub line
        $nameSize = 44;
        $lines = $this->wrap('display', $nameSize, $ipo->name.' IPO', 820);
        if (count($lines) > 2) {
            $nameSize = 34;
            $lines = array_slice($this->wrap('display', $nameSize, $ipo->name.' IPO', 820), 0, 2);
        }
        $y = count($lines) === 1 ? 222 : 196;
        foreach ($lines as $line) {
            $this->text($im, 'display', $nameSize, 330, $y, $white, $line);
            $y += (int) ($nameSize * 1.45);
        }
        $this->text($im, 'medium', 17, 332, count($lines) === 1 ? 262 : 280, $soft, $ipo->typeLabel().' IPO · '.$ipo->exchangeLabel());

        // Stat boxes
        $gmpValue = $ipo->hasGmp()
            ? ($ipo->gmp > 0 ? '+' : '').'₹'.Ipo::num($ipo->gmp).($ipo->gmpPercent() !== null ? ' ('.number_format($ipo->gmpPercent(), 1).'%)' : '')
            : '—';
        $gmpColor = ! $ipo->hasGmp() ? $white : ($ipo->gmp > 0 ? $green : ($ipo->gmp < 0 ? $red : $white));

        $listing = $ipo->listingGainPercent() !== null
            ? ['Listed at', '₹'.Ipo::num($ipo->listing_price).' ('.($ipo->listingGainPercent() >= 0 ? '+' : '').number_format($ipo->listingGainPercent(), 1).'%)']
            : ['Listing', $ipo->listing_date?->format('j M Y') ?? 'TBA'];

        $boxes = [
            ['Price band', $ipo->priceBand(), $white],
            ['GMP', $gmpValue, $gmpColor],
            ['Subscription', $ipo->open_date ? $ipo->open_date->format('j M').' – '.($ipo->close_date ?? $ipo->open_date)->format('j M') : 'Dates awaited', $white],
            [$listing[0], $listing[1], $white],
        ];
        $boxWidth = 258;
        foreach ($boxes as $i => [$label, $value, $color]) {
            $x = 60 + $i * ($boxWidth + 16);
            $this->roundedRect($im, $x, 330, $x + $boxWidth, 470, 20, imagecolorallocate($im, 23, 38, 82));
            $this->text($im, 'medium', 15, $x + 22, 372, $muted, $label);
            $size = 22;
            while ($size > 14 && $this->textWidth('heavy', $size, $value) > $boxWidth - 44) {
                $size--;
            }
            $this->text($im, 'heavy', $size, $x + 22, 432, $color, $value);
        }

        // Footer
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'ipodarbar';
        $this->text($im, 'bold', 17, 60, 560, $white, 'Live GMP, allotment & listing updates');
        $this->text($im, 'medium', 17, 60, 592, $gold, $host);
        $note = 'GMP is unofficial and indicative only';
        $this->text($im, 'medium', 13, self::WIDTH - 60 - $this->textWidth('medium', 13, $note), 592, $muted, $note);

        return $im;
    }

    private function background(GdImage $im): void
    {
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $t = $y / self::HEIGHT;
            $color = imagecolorallocate($im, (int) (10 + 6 * $t), (int) (22 + 12 * $t), (int) (51 + 28 * $t));
            imageline($im, 0, $y, self::WIDTH, $y, $color);
        }

        $pattern = imagecolorallocatealpha($im, 230, 190, 98, 118);
        for ($x = -56; $x < self::WIDTH + 56; $x += 56) {
            for ($y = -56; $y < self::HEIGHT + 56; $y += 56) {
                imagepolygon($im, [$x + 28, $y, $x + 56, $y + 28, $x + 28, $y + 56, $x, $y + 28], $pattern);
            }
        }

        $gold = imagecolorallocate($im, 200, 150, 46);
        imagefilledrectangle($im, 0, 0, self::WIDTH, 5, $gold);
    }

    private function logo(GdImage $im, Ipo $ipo, int $x, int $y, int $w, int $h): void
    {
        $path = $this->logos->ensure($ipo);
        $thumb = $path ? @imagecreatefromwebp($path) : false;

        if ($thumb) {
            imagecopyresampled($im, $thumb, $x + 10, $y + 5, 0, 0, $w - 20, $h - 10, imagesx($thumb), imagesy($thumb));
            imagedestroy($thumb);

            return;
        }

        $hue = $ipo->hue() / 360;
        [$r, $g, $b] = $this->hslToRgb($hue, 0.55, 0.42);
        $this->roundedRect($im, $x, $y, $x + $w, $y + $h, 22, imagecolorallocate($im, $r, $g, $b));
        $initials = $ipo->initials();
        $size = 40;
        $this->text($im, 'heavy', $size, $x + (int) (($w - $this->textWidth('heavy', $size, $initials)) / 2), $y + 84, imagecolorallocate($im, 255, 255, 255), $initials);
    }

    private function roundedRect(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x1 + $r - 1, $y2 - $r, $color);
        imagefilledrectangle($im, $x2 - $r + 1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
    }

    private function text(GdImage $im, string $font, int $size, int $x, int $y, int $color, string $text): void
    {
        if (imagettftext($im, $size, 0, $x, $y, $color, $this->fontPath(self::FONTS[$font]), $text) === false) {
            throw new RuntimeException('Could not render text with '.$font);
        }
    }

    private function textWidth(string $font, int $size, string $text): int
    {
        $box = imagettfbbox($size, 0, $this->fontPath(self::FONTS[$font]), $text);

        return $box ? abs($box[2] - $box[0]) : 0;
    }

    /**
     * @return array<int, string>
     */
    private function wrap(string $font, int $size, string $text, int $maxWidth): array
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

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hslToRgb(float $h, float $s, float $l): array
    {
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $channel = function (float $t) use ($p, $q): int {
            $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);
            $v = match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };

            return (int) round($v * 255);
        };

        return [$channel($h + 1 / 3), $channel($h), $channel($h - 1 / 3)];
    }

    private function fontPath(string $file): string
    {
        return resource_path('fonts/'.$file);
    }
}
