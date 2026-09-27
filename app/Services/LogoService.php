<?php

namespace App\Services;

use App\Models\Ipo;
use GdImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Turns the source IPO banner (a ~30 KB, 1280px image with the company logo on a
 * white card in the middle) into a small, tightly-cropped 2:1 logo thumbnail.
 *
 * Thumbnails are written to public/logos so the web server serves them as static
 * files after the first request.
 */
class LogoService
{
    public const WIDTH = 320;

    public const HEIGHT = 160;

    /** Region of the banner that holds the logo, as fractions of width/height. */
    private const REGION = ['x1' => .21, 'x2' => .79, 'y1' => .38, 'y2' => .66];

    public function filename(Ipo $ipo): ?string
    {
        // Without GD (webp support) thumbnails can't be built; the monogram is shown instead.
        if (! $ipo->image_url || ! function_exists('imagewebp')) {
            return null;
        }

        return $ipo->slug.'-'.substr(md5($ipo->image_url), 0, 8).'.webp';
    }

    public function url(Ipo $ipo): ?string
    {
        $file = $this->filename($ipo);

        return $file ? asset('logos/'.$file) : null;
    }

    public function path(string $file): string
    {
        return public_path('logos/'.$file);
    }

    public function exists(Ipo $ipo): bool
    {
        $file = $this->filename($ipo);

        return $file !== null && is_file($this->path($file));
    }

    /**
     * Build the thumbnail if it doesn't exist yet. Returns the absolute path, or null on failure.
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

        // Don't hammer the source for images that recently failed. When this host can't reach
        // the image source, banners arrive through the relay instead (see RelayController).
        $retryKey = 'logo-retry:'.$file;
        if ($this->hasFailed($ipo) || Cache::has($retryKey) || ! config('ipodarbar.ipo_api.pull')) {
            return null;
        }

        try {
            $response = Http::timeout(15)->retry(1, 300, throw: false)->get($ipo->bannerUrl());
            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status());
            }
        } catch (Throwable) {
            // Network hiccup: try again soon.
            Cache::put($retryKey, true, now()->addMinutes(10));

            return null;
        }

        return $this->storeBanner($ipo, $response->body());
    }

    /** Build and save the thumbnail from downloaded banner bytes. Returns the absolute path, or null. */
    public function storeBanner(Ipo $ipo, string $bytes): ?string
    {
        $file = $this->filename($ipo);
        if (! $file) {
            return null;
        }
        $path = $this->path($file);

        try {
            $thumb = $this->make($bytes);
        } catch (UnsupportedBanner) {
            // Old banner layouts never become croppable; the monogram is shown instead.
            Cache::put($this->failKey($file), true, now()->addDays(7));

            return null;
        } catch (Throwable) {
            return null;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        // Remove thumbnails from an older banner of the same IPO.
        foreach (glob($this->path($ipo->slug.'-*.webp')) ?: [] as $old) {
            if (preg_match('/^'.preg_quote($ipo->slug, '/').'-[0-9a-f]{8}\.webp$/', basename($old))) {
                @unlink($old);
            }
        }

        $tmp = $path.'.'.getmypid().'.tmp';
        imagewebp($thumb, $tmp, 82);
        imagedestroy($thumb);
        rename($tmp, $path);

        return $path;
    }

    /** Whether the banner is known to be in a layout that can't be cropped. */
    public function hasFailed(Ipo $ipo): bool
    {
        $file = $this->filename($ipo);

        return $file !== null && Cache::has($this->failKey($file));
    }

    private function failKey(string $file): string
    {
        return 'logo-unsupported:'.$file;
    }

    /** Crop the logo out of banner bytes and return a padded 2:1 thumbnail. */
    public function make(string $bytes): GdImage
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            throw new \RuntimeException('Unreadable image');
        }

        $w = imagesx($src);
        $h = imagesy($src);

        $rx1 = (int) round($w * self::REGION['x1']);
        $rx2 = (int) round($w * self::REGION['x2']);
        $ry1 = (int) round($h * self::REGION['y1']);
        $ry2 = (int) round($h * self::REGION['y2']);

        // Only the current banner template (logo on a white card) can be cropped reliably:
        // the left and right edges of the logo region must be (almost) white.
        $edge = 0;
        $white = 0;
        for ($y = $ry1; $y < $ry2; $y += 3) {
            foreach ([$rx1, $rx2 - 1] as $x) {
                $edge++;
                $white += $this->isWhite(imagecolorat($src, $x, $y)) ? 1 : 0;
            }
        }
        if ($edge === 0 || $white / $edge < .85) {
            imagedestroy($src);
            throw new UnsupportedBanner('Unsupported banner layout');
        }

        // Erase the tip of the red "IPO" ribbon that pokes into the top-right of the region.
        $whitePx = imagecolorallocate($src, 255, 255, 255);
        for ($y = $ry1; $y < (int) ($h * .415); $y++) {
            for ($x = (int) ($w * .6); $x < $rx2; $x++) {
                $rgb = imagecolorat($src, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r > $g + 20 && $r > $b + 20) {
                    imagesetpixel($src, $x, $y, $whitePx);
                }
            }
        }

        // Bounding box of visible "ink" inside the logo region.
        $minX = $rx2;
        $minY = $ry2;
        $maxX = $rx1;
        $maxY = $ry1;
        $step = max(1, (int) floor($w / 640));

        for ($y = $ry1; $y < $ry2; $y += $step) {
            for ($x = $rx1; $x < $rx2; $x += $step) {
                if (! $this->isInk(imagecolorat($src, $x, $y))) {
                    continue;
                }
                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }

        // Nothing found (or suspiciously small): fall back to the whole region.
        if ($maxX - $minX < $w * .03 || $maxY - $minY < $h * .02) {
            [$minX, $minY, $maxX, $maxY] = [$rx1, $ry1, $rx2, $ry2];
        }

        $bw = $maxX - $minX + 1;
        $bh = $maxY - $minY + 1;

        // Fit the logo into the thumbnail with breathing room.
        $pad = .1;
        $scale = min(self::WIDTH * (1 - 2 * $pad) / $bw, self::HEIGHT * (1 - 2 * $pad) / $bh);
        $dw = (int) round($bw * $scale);
        $dh = (int) round($bh * $scale);

        $dst = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled(
            $dst, $src,
            (int) ((self::WIDTH - $dw) / 2), (int) ((self::HEIGHT - $dh) / 2),
            $minX, $minY, $dw, $dh, $bw, $bh
        );
        imagedestroy($src);

        return $dst;
    }

    /** Clearly visible logo pixel: dark enough, or distinctly coloured (ignores faint compression haze). */
    private function isInk(int $rgb): bool
    {
        $c = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
        $min = min($c);

        return $min < 215 || (max($c) - $min > 45);
    }

    private function isWhite(int $rgb): bool
    {
        return (($rgb >> 16) & 0xFF) >= 232 && (($rgb >> 8) & 0xFF) >= 232 && ($rgb & 0xFF) >= 232;
    }

    /**
     * Pre-build thumbnails so visitors never wait on generation. Pauses briefly between
     * downloads to be gentle with the image host.
     *
     * @param  iterable<Ipo>  $ipos
     */
    public function warm(iterable $ipos): int
    {
        $made = 0;
        foreach ($ipos as $ipo) {
            if ($this->exists($ipo)) {
                continue;
            }
            if ($this->ensure($ipo)) {
                $made++;
            }
            Sleep::usleep(150_000);
        }

        return $made;
    }
}
