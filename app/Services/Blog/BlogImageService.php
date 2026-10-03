<?php

namespace App\Services\Blog;

use App\Models\Ipo;
use App\Services\LogoService;
use App\Support\GdCanvas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Draws the images for automatic blog posts: a branded cover with the day's company logos,
 * a GMP bar chart and a week-at-a-glance chart. Files go to uploads/blog/auto as WebP.
 */
class BlogImageService
{
    public const WIDTH = 1600;

    private const COVER_HEIGHT = 900;

    public function __construct(private LogoService $logos) {}

    public function supported(): bool
    {
        return GdCanvas::supported();
    }

    /**
     * @param  list<array{0: string, 1: string}>  $tiles  [big value, label] pairs, up to four.
     * @param  Collection<int, Ipo>  $ipos  Companies whose logos are shown along the bottom.
     * @return array{path: string, width: int, height: int}|null
     */
    public function cover(string $name, string $kicker, string $title, string $subtitle, array $tiles, Collection $ipos): ?array
    {
        return $this->draw($name, self::COVER_HEIGHT, function (GdCanvas $c) use ($kicker, $title, $subtitle, $tiles, $ipos): void {
            $c->gradient('#050B1F', '#1A2F66');
            $c->jaali(114);
            $c->rect(0, 0, self::WIDTH, 6, $c->color('#C8962E'));

            $kicker = mb_strtoupper($kicker);
            $pillWidth = $c->textWidth('bold', 21, $kicker) + 48;
            $c->outlinedRect(90, 74, 90 + $pillWidth, 124, 25, $c->color('#E6BE62', 40), $c->color('#14244F'));
            $c->text('bold', 21, 114, 108, $c->color('#E6BE62'), $kicker);

            $size = 72;
            $lines = $c->wrap('display', $size, $title, 1420);
            while (count($lines) > 2 && $size > 48) {
                $size -= 4;
                $lines = $c->wrap('display', $size, $title, 1420);
            }
            $y = 230;
            foreach (array_slice($lines, 0, 2) as $line) {
                $c->text('display', $size, 90, $y, $c->color('#FFFFFF'), $line);
                $y += (int) ($size * 1.25);
            }
            $c->text('medium', 30, 92, $y + 6, $c->color('#C9D1E8'), $c->clip('medium', 30, $subtitle, 1420));

            $tiles = array_slice($tiles, 0, 4);
            if ($tiles !== []) {
                $gap = 22;
                $width = (int) ((1420 - $gap * (count($tiles) - 1)) / count($tiles));
                $top = $y + 50;
                foreach ($tiles as $i => [$value, $label]) {
                    $x = 90 + $i * ($width + $gap);
                    $c->roundedRect($x, $top, $x + $width, $top + 150, 22, $c->color('#FFFFFF', 116));
                    $c->roundedRect($x + 1, $top + 1, $x + $width - 1, $top + 149, 21, $c->color('#16285A', 30));
                    $valueSize = $c->fit('display', 54, $value, $width - 48, 30);
                    $c->text('display', $valueSize, $x + 26, $top + 76, $c->color('#F6DE9C'), $value);
                    $c->text('medium', 22, $x + 26, $top + 120, $c->color('#B7C1DE'), $c->clip('medium', 22, $label, $width - 48));
                }
            }

            $this->logoRow($c, $ipos, 90, 700);

            $brand = public_path('images/brand/logo-160.webp');
            if (is_file($brand)) {
                $c->picture($brand, 90, 812, 52, 52);
            }
            $x = 156 + $c->text('display', 30, 156, 850, $c->color('#FFFFFF'), 'IPO ');
            $c->text('display', 30, $x, 850, $c->color('#E6BE62'), 'Darbaar');
            $c->textRight('medium', 24, 1510, 848, $c->color('#9AA6C7'), $this->host().'/blog');
        });
    }

    /**
     * Horizontal bars of GMP as a percentage of the upper price band.
     *
     * @param  Collection<int, Ipo>  $ipos
     * @return array{path: string, width: int, height: int}|null
     */
    public function gmpChart(string $name, string $title, string $subtitle, Collection $ipos, string $note): ?array
    {
        $ipos = $ipos->filter(fn (Ipo $ipo): bool => $ipo->gmpPercent() !== null)->values();
        if ($ipos->isEmpty()) {
            return null;
        }

        $rowHeight = 78;
        $height = 250 + $ipos->count() * $rowHeight + 110;

        return $this->draw($name, $height, function (GdCanvas $c) use ($title, $subtitle, $ipos, $note, $rowHeight, $height): void {
            $this->chartFrame($c, $title, $subtitle, $note, $height);

            $max = max(1, $ipos->max(fn (Ipo $ipo): float => abs($ipo->gmpPercent())));
            $track = [600, 1260];
            foreach ($ipos as $i => $ipo) {
                $y = 220 + $i * $rowHeight;
                $this->logoTile($c, $ipo, 80, $y + 4, 56, '#F1F3F8');
                $c->text('bold', 25, 154, $y + 42, $c->color('#0A1633'), $c->clip('bold', 25, $ipo->name, 420));
                $c->text('medium', 19, 154, $y + 68, $c->color('#737D96'), $ipo->typeLabel().' · '.$ipo->priceBand());

                $percent = $ipo->gmpPercent();
                $c->roundedRect($track[0], $y + 16, $track[1], $y + 52, 10, $c->color('#EEF1F7'));
                $length = (int) (($track[1] - $track[0]) * abs($percent) / $max);
                if ($length > 0) {
                    $c->roundedRect($track[0], $y + 16, $track[0] + max(20, $length), $y + 52, 10, $c->color($percent < 0 ? '#D13A3A' : ($i === 0 ? '#C8962E' : '#243C7A')));
                }
                $value = ($ipo->gmp < 0 ? '-₹' : '₹').Ipo::num(abs($ipo->gmp)).'  ·  '.($percent > 0 ? '+' : '').number_format($percent, 1).'%';
                $c->textRight('bold', 25, 1520, $y + 44, $c->color($percent > 0 ? '#0A8F62' : ($percent < 0 ? '#D13A3A' : '#737D96')), $value);
            }
        });
    }

    /**
     * For each listing: the gain the GMP pointed to next to the actual listing gain, on a shared
     * scale with a zero line, so misses in either direction are easy to see.
     *
     * @param  Collection<int, Ipo>  $ipos  Listed IPOs with a listing price.
     * @return array{path: string, width: int, height: int}|null
     */
    public function gmpVsActualChart(string $name, string $title, string $subtitle, Collection $ipos, string $note): ?array
    {
        $ipos = $ipos->filter(fn (Ipo $ipo): bool => $ipo->listingGainPercent() !== null)->take(12)->values();
        if ($ipos->isEmpty()) {
            return null;
        }

        $rowHeight = 92;
        $height = 290 + $ipos->count() * $rowHeight + 100;

        return $this->draw($name, $height, function (GdCanvas $c) use ($title, $subtitle, $ipos, $note, $rowHeight, $height): void {
            $this->chartFrame($c, $title, $subtitle, $note, $height);

            $legend = 80;
            foreach ([['#C8962E', 'GMP estimate'], ['#0A8F62', 'Actual listing gain'], ['#D13A3A', 'Listed below issue price']] as [$color, $label]) {
                $c->roundedRect($legend, 196, $legend + 22, 218, 6, $c->color($color));
                $legend += 34 + $c->text('medium', 21, $legend + 34, 215, $c->color('#3E4862'), $label) + 40;
            }

            $values = $ipos->flatMap(fn (Ipo $ipo): array => [$ipo->gmpEstimatePercent() ?? 0.0, $ipo->listingGainPercent()]);
            $min = min(0.0, (float) $values->min());
            $max = max(0.0, (float) $values->max(), 1.0);
            [$left, $right] = [560, 1300];
            $x = fn (float $v): int => (int) round($left + ($v - $min) / ($max - $min) * ($right - $left));
            $zero = $x(0.0);

            foreach ($ipos as $i => $ipo) {
                $y = 262 + $i * $rowHeight;
                $this->logoTile($c, $ipo, 80, $y + 12, 56, '#F1F3F8');
                $c->text('bold', 24, 154, $y + 40, $c->color('#0A1633'), $c->clip('bold', 24, $ipo->name, 380));
                $c->text('medium', 19, 154, $y + 68, $c->color('#737D96'), $ipo->typeLabel().' · issue '.Ipo::money($ipo->price));

                $c->rect($left, $y + 8, $right, $y + 76, $c->color('#F6F7FA'));
                $c->rect($zero - 1, $y + 4, $zero + 1, $y + 80, $c->color('#B8C0D3'));
                $bars = [[$ipo->gmpEstimatePercent(), '#C8962E', 14], [$ipo->listingGainPercent(), $ipo->listingGainPercent() < 0 ? '#D13A3A' : '#0A8F62', 46]];
                foreach ($bars as [$value, $color, $offset]) {
                    if ($value === null) {
                        continue;
                    }
                    $end = $x((float) $value);
                    $c->roundedRect(min($zero, $end), $y + $offset, max($zero, $end, min($zero, $end) + 4), $y + $offset + 22, 6, $c->color($color));
                }

                $gmp = $ipo->gmpEstimatePercent();
                $c->textRight('bold', 21, 1520, $y + 34, $c->color('#87600F'), 'GMP '.($gmp === null ? '—' : ($gmp > 0 ? '+' : '').number_format($gmp, 1).'%'));
                $actual = $ipo->listingGainPercent();
                $c->textRight('bold', 21, 1520, $y + 66, $c->color($actual < 0 ? '#D13A3A' : '#0A8F62'), 'Listed '.($actual > 0 ? '+' : '').number_format($actual, 1).'%');
            }
        });
    }

    /**
     * Opening, closing and listing counts for each weekday.
     *
     * @param  list<array{day: string, date: string, opening: int, closing: int, listing: int}>  $days
     * @return array{path: string, width: int, height: int}|null
     */
    public function weekChart(string $name, string $title, string $subtitle, array $days, string $note): ?array
    {
        $height = 760;

        return $this->draw($name, $height, function (GdCanvas $c) use ($title, $subtitle, $days, $note, $height): void {
            $this->chartFrame($c, $title, $subtitle, $note, $height);

            $gap = 20;
            $width = (int) ((1440 - $gap * (count($days) - 1)) / max(1, count($days)));
            $rows = [
                ['opening', 'Opening', '#E8EEFE', '#2F5BEA'],
                ['closing', 'Last day', '#FBF3DF', '#87600F'],
                ['listing', 'Listing', '#E3F5EE', '#0A8F62'],
            ];
            foreach ($days as $i => $day) {
                $x = 80 + $i * ($width + $gap);
                $c->outlinedRect($x, 210, $x + $width, 560, 22, $c->color('#E3E7F0'), $c->color('#F8F9FC'));
                $c->text('bold', 27, $x + 24, 262, $c->color('#0A1633'), $day['day']);
                $c->text('medium', 21, $x + 24, 296, $c->color('#737D96'), $day['date']);

                if ($day['opening'] + $day['closing'] + $day['listing'] === 0) {
                    $c->text('medium', 22, $x + 24, 420, $c->color('#9AA6C7'), 'No IPO events');

                    continue;
                }
                foreach ($rows as $r => [$key, $label, $bg, $fg]) {
                    $y = 330 + $r * 72;
                    $c->text('medium', 22, $x + 24, $y + 38, $c->color('#3E4862'), $label);
                    $value = (string) $day[$key];
                    $c->roundedRect($x + $width - 92, $y + 6, $x + $width - 22, $y + 54, 12, $c->color($bg));
                    $c->text('display', 30, $x + $width - 57 - (int) ($c->textWidth('display', 30, $value) / 2), $y + 42, $c->color($fg), $value);
                }
            }

            $lx = 80;
            foreach ([['#2F5BEA', 'Opening for bids'], ['#C8962E', 'Last day to apply'], ['#0A8F62', 'Listing on the exchanges']] as [$color, $label]) {
                $c->roundedRect($lx, 598, $lx + 22, 620, 6, $c->color($color));
                $lx += 34 + $c->text('medium', 21, $lx + 34, 617, $c->color('#3E4862'), $label) + 40;
            }
        });
    }

    /**
     * @param  callable(GdCanvas): void  $paint
     * @return array{path: string, width: int, height: int}|null
     */
    private function draw(string $name, int $height, callable $paint): ?array
    {
        if (! $this->supported()) {
            return null;
        }

        $path = 'blog/auto/'.$name.'.webp';
        $canvas = new GdCanvas(self::WIDTH, $height);
        try {
            $paint($canvas);
            $canvas->saveWebp(Storage::disk('uploads')->path($path));
        } catch (Throwable $e) {
            report($e);

            return null;
        } finally {
            $canvas->destroy();
        }

        return ['path' => $path, 'width' => self::WIDTH, 'height' => $height];
    }

    private function chartFrame(GdCanvas $c, string $title, string $subtitle, string $note, int $height): void
    {
        $c->fill('#FFFFFF');
        $c->rect(0, 0, self::WIDTH, 6, $c->color('#C8962E'));
        $c->text('display', 44, 80, 104, $c->color('#0A1633'), $c->clip('display', 44, $title, 1440));
        $c->text('medium', 24, 80, 152, $c->color('#3E4862'), $c->clip('medium', 24, $subtitle, 1440));

        $y = $height - 74;
        $c->rect(80, $y, 1520, $y + 2, $c->color('#E3E7F0'));
        $brand = public_path('images/brand/logo-160.webp');
        if (is_file($brand)) {
            $c->picture($brand, 80, $y + 20, 38, 38);
        }
        $x = 130 + $c->text('bold', 21, 130, $y + 47, $c->color('#0A1633'), 'IPO Darbaar');
        $c->text('medium', 19, $x + 14, $y + 47, $c->color('#737D96'), $c->clip('medium', 19, '·  '.$note, 1200 - $x));
        $c->textRight('medium', 19, 1520, $y + 47, $c->color('#737D96'), $this->host());
    }

    /**
     * @param  Collection<int, Ipo>  $ipos
     */
    private function logoRow(GdCanvas $c, Collection $ipos, int $x, int $y): void
    {
        foreach ($ipos->take(9) as $ipo) {
            $this->logoTile($c, $ipo, $x, $y, 92);
            $x += 108;
        }
    }

    /** The company's logo on a white tile, or its initials when there is no logo. */
    private function logoTile(GdCanvas $c, Ipo $ipo, int $x, int $y, int $size, string $tile = '#FFFFFF'): void
    {
        $c->roundedRect($x, $y, $x + $size, $y + $size, (int) ($size / 5), $c->color($tile));
        $path = null;
        try {
            $path = $this->logos->ensure($ipo);
        } catch (Throwable) {
            // Fall back to initials below.
        }
        $pad = (int) ($size / 9);
        if ($path && $c->picture($path, $x + $pad, $y + $pad, $size - 2 * $pad, $size - 2 * $pad)) {
            return;
        }

        $c->roundedRect($x, $y, $x + $size, $y + $size, (int) ($size / 5), $c->color('#243C7A'));
        $font = (int) ($size * 0.34);
        $initials = $ipo->initials();
        $c->text('heavy', $font, $x + (int) (($size - $c->textWidth('heavy', $font, $initials)) / 2), $y + (int) ($size * 0.62), $c->color('#F6DE9C'), $initials);
    }

    private function host(): string
    {
        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'ipodarbaar.in');
    }
}
