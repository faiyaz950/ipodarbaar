<?php

namespace App\Support;

use App\Models\Ipo;
use App\Services\NewsService;
use Illuminate\Support\Collection;

/**
 * Turns the first mention of each IPO company in a news article into a link to its
 * IPO page. It only touches text outside existing links, so the article's HTML
 * structure is preserved.
 */
class IpoLinker
{
    private const MAX_LINKS = 5;

    /**
     * @param  Collection<int, Ipo>  $ipos  Candidate IPOs (e.g. active and recently listed).
     * @return array{html: string, ipos: Collection<int, Ipo>}
     */
    public static function link(string $html, Collection $ipos): array
    {
        $candidates = $ipos
            ->sortByDesc(fn (Ipo $ipo): int => mb_strlen($ipo->name))
            ->map(fn (Ipo $ipo): array => ['ipo' => $ipo, 'pattern' => NewsService::companyPattern($ipo->name, loose: true)])
            ->filter(fn (array $candidate): bool => $candidate['pattern'] !== null)
            ->values();

        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $insideLink = 0;
        $linked = collect();

        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                if (preg_match('/^<a[\s>]/i', $part)) {
                    $insideLink++;
                } elseif (preg_match('/^<\/a>/i', $part)) {
                    $insideLink = max(0, $insideLink - 1);
                }

                continue;
            }
            if ($insideLink > 0 || $linked->count() >= self::MAX_LINKS) {
                continue;
            }

            // Matches become placeholder tokens first, so later names can't match inside an inserted link.
            $anchors = [];
            foreach ($candidates as $candidate) {
                if ($linked->has($candidate['ipo']->id) || $linked->count() >= self::MAX_LINKS) {
                    continue;
                }

                $part = preg_replace_callback($candidate['pattern'], function (array $match) use ($candidate, &$anchors): string {
                    $anchors[] = '<a href="'.e($candidate['ipo']->url()).'" title="'.e($candidate['ipo']->name).' IPO">'.$match[0].'</a>';

                    return "\u{E000}".(count($anchors) - 1)."\u{E001}";
                }, $part, 1, $count);

                if ($count > 0) {
                    $linked->put($candidate['ipo']->id, $candidate['ipo']);
                }
            }

            $parts[$index] = preg_replace_callback("/\u{E000}(\d+)\u{E001}/u", fn (array $m): string => $anchors[(int) $m[1]], $part);
        }

        return ['html' => implode('', $parts), 'ipos' => $linked->values()];
    }
}
