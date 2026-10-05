<?php

namespace App\Support;

use App\Models\Ipo;
use App\Models\IpoFinancial;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Plain-language observations about an IPO, worked out from its own data: what the company
 * does, how the issue is split, how revenue and profit have moved, what the GMP and
 * subscription say, and how the listing went. Shared by the IPO page and the automatic blog
 * posts, so the wording stays the same and nothing is invented.
 */
class IpoInsights
{
    /** SME issue sizes above this (₹ crore) are almost certainly data errors and are not quoted. */
    public const MAX_SME_SIZE_CR = 300;

    /**
     * The page's analysis, grouped into paragraphs (empty ones left out).
     *
     * @return list<string>
     */
    public static function paragraphs(Ipo $ipo): array
    {
        return array_values(array_filter(array_map(
            fn (array $sentences): string => implode(' ', array_filter($sentences)),
            [
                [self::business($ipo), self::structure($ipo), self::promoters($ipo), self::valuation($ipo)],
                self::growth($ipo),
                [self::gmp($ipo), self::subscription($ipo), self::listing($ipo)],
            ],
        )));
    }

    /** "Orient Cables India is a manufacturer of networking cables." from the IPO's description. */
    public static function business(Ipo $ipo): ?string
    {
        $about = Str::squish((string) $ipo->about);
        if (! preg_match('/\bwhich\s+(?:is|are)\s+(.{12,220}?)\.(?:\s|$)/i', $about, $m)) {
            return null;
        }

        return $ipo->name.' is '.rtrim($m[1], ' ,;').'.';
    }

    public static function structure(Ipo $ipo): ?string
    {
        $detail = $ipo->detail;

        return match (true) {
            ! self::plausibleSize($ipo) => null,
            $detail && $detail->fresh_issue_cr && $detail->ofs_cr => 'The '.self::size($ipo).' issue combines a fresh issue of '.self::crore($detail->fresh_issue_cr).', which goes to the company, and an offer for sale (OFS) of '.self::crore($detail->ofs_cr)
                .' by existing shareholders, so about '.round($detail->fresh_issue_cr / ($detail->fresh_issue_cr + $detail->ofs_cr) * 100).'% of the money raised reaches the business.',
            $detail && $detail->fresh_issue_cr && ! $detail->ofs_cr => 'The '.self::size($ipo).' issue is entirely a fresh issue, so all the money goes to the company.',
            $detail && ! $detail->fresh_issue_cr && $detail->ofs_cr => 'The '.self::size($ipo).' issue is entirely an offer for sale, so the money goes to the selling shareholders, not the company.',
            default => 'The issue size is '.self::size($ipo).'.',
        };
    }

    public static function promoters(Ipo $ipo): ?string
    {
        $detail = $ipo->detail;

        return $detail && $detail->promoter_holding_pre && $detail->promoter_holding_post
            ? 'Promoter holding falls from '.self::num($detail->promoter_holding_pre).'% to '.self::num($detail->promoter_holding_post).'% after the issue.'
            : null;
    }

    public static function valuation(Ipo $ipo): ?string
    {
        $detail = $ipo->detail;
        if (! $detail) {
            return null;
        }
        $parts = array_filter([
            $detail->market_cap_cr ? 'a market capitalisation of '.self::crore($detail->market_cap_cr) : null,
            $detail->pe_post ? 'a post-issue P/E of '.self::num($detail->pe_post, 1) : ($detail->pe_pre ? 'a pre-issue P/E of '.self::num($detail->pe_pre, 1) : null),
        ]);

        return $parts === [] ? null : 'At the upper price band the issue values the company at '.implode(' and ', $parts).'.';
    }

    /**
     * Revenue and profit trend over the full financial years in the offer document.
     *
     * @return list<string>
     */
    public static function growth(Ipo $ipo): array
    {
        /** @var Collection<int, IpoFinancial> $years */
        $years = $ipo->financials->sortBy('period_end')->values()->take(-4)
            ->filter(fn (IpoFinancial $f): bool => (bool) preg_match('/^FY\s?\d{2,4}$/i', trim((string) $f->period)))->values();
        if ($years->count() < 2) {
            return [];
        }

        [$first, $last] = [$years->first(), $years->last()];
        $span = $years->count() - 1;
        $sentences = [];
        if ($first->revenue > 0 && $last->revenue > 0) {
            $change = ($last->revenue / $first->revenue - 1) * 100;
            $sentences[] = 'Revenue '.($change >= 0 ? 'rose' : 'fell').' from '.self::crore($first->revenue).' in '.$first->period.' to '.self::crore($last->revenue).' in '.$last->period
                .($span >= 2 ? ', a compound annual '.($change >= 0 ? 'growth' : 'decline').' of '.self::num(abs((($last->revenue / $first->revenue) ** (1 / $span) - 1) * 100), 1).'%' : ' ('.self::percent($change).')').'.';
        }
        if ($first->pat !== null && $last->pat !== null) {
            $sentences[] = match (true) {
                $last->pat < 0 => 'The company reported a loss of '.self::crore(abs($last->pat)).' in '.$last->period.'.',
                $first->pat <= 0 => 'It moved from a '.($first->pat < 0 ? 'loss of '.self::crore(abs($first->pat)) : 'break-even result').' in '.$first->period.' to a profit of '.self::crore($last->pat).' in '.$last->period.'.',
                default => 'Profit after tax '.($last->pat >= $first->pat ? 'grew' : 'fell').' from '.self::crore($first->pat).' to '.self::crore($last->pat).'.',
            };
        }
        if ($last->revenue > 0 && $last->pat !== null && $last->pat > 0) {
            $sentences[] = 'That is a net profit margin of '.self::num($last->pat / $last->revenue * 100, 1).'% in '.$last->period.'.';
        }
        if ($last->net_worth > 0 && $last->borrowings !== null) {
            $sentences[] = 'Borrowings were '.self::num($last->borrowings / $last->net_worth).' times net worth at the end of '.$last->period.'.';
        }

        return $sentences;
    }

    /** What the grey market says before listing (plain text). */
    public static function gmp(Ipo $ipo): ?string
    {
        if ($ipo->status() === 'listed' || ! $ipo->hasGmp() || ! $ipo->price) {
            return null;
        }
        if ($ipo->gmp > 0) {
            return 'The grey market premium (GMP) is '.Ipo::money($ipo->gmp).', '.number_format($ipo->gmpPercent(), 1).'% above the upper price, which points to a listing around '.Ipo::money($ipo->estListingPrice()).'; GMP is unofficial and changes quickly.';
        }

        return $ipo->gmp < 0
            ? 'The grey market premium is negative at -'.Ipo::money(abs($ipo->gmp)).', which suggests a listing below the issue price.'
            : 'The GMP is flat at ₹0, which suggests a listing close to the issue price.';
    }

    public static function subscription(Ipo $ipo): ?string
    {
        if ($ipo->subscription_total === null) {
            return null;
        }
        $parts = array_filter([
            $ipo->subscription_qib !== null ? 'QIB '.self::times($ipo->subscription_qib) : null,
            $ipo->subscription_nii !== null ? 'NII '.self::times($ipo->subscription_nii) : null,
            $ipo->subscription_retail !== null ? 'retail '.self::times($ipo->subscription_retail) : null,
        ]);
        $final = $ipo->close_date && $ipo->subscription_updated_at?->gte($ipo->close_date->copy()->setTime(17, 0));

        return ($final ? 'The issue closed' : ($ipo->subscription_updated_at ? 'By '.$ipo->subscription_updated_at->format('D, j M, g:i A').', the issue was' : 'The issue was'))
            .' subscribed '.self::times($ipo->subscription_total).' overall'
            .($parts !== [] ? ' ('.self::join(array_values($parts)).')' : '').'.';
    }

    /** How the listing went against the issue price and the last GMP. */
    public static function listing(Ipo $ipo): ?string
    {
        $gain = $ipo->listingGainPercent();
        if ($gain === null) {
            return null;
        }

        return implode(' ', array_filter([
            $ipo->name.' listed at '.Ipo::money($ipo->listing_price).($ipo->listing_date ? ' on '.$ipo->listing_date->format('j M Y') : '').', '.self::percent($gain).' against the issue price of '.Ipo::money($ipo->price).'.',
            $ipo->gmpErrorPoints() !== null ? 'The last GMP before listing had pointed to about '.Ipo::money($ipo->price + $ipo->listing_gmp).', so it opened '.number_format(abs($ipo->gmpErrorPoints()), 1).' points '.($ipo->gmpErrorPoints() >= 0 ? 'above' : 'below').' the grey market estimate.' : null,
            $ipo->listing_close ? 'It closed the first day at '.Ipo::money($ipo->listing_close).' ('.self::percent($ipo->listingCloseGainPercent()).').' : null,
        ]));
    }

    public static function plausibleSize(Ipo $ipo): bool
    {
        return $ipo->issue_size > 0 && ($ipo->isMainboard() || $ipo->issue_size <= self::MAX_SME_SIZE_CR);
    }

    public static function size(Ipo $ipo): string
    {
        return self::plausibleSize($ipo) ? self::crore($ipo->issue_size) : '—';
    }

    public static function crore(?float $value): string
    {
        return $value === null ? '—' : ($value < 0 ? '-' : '').'₹'.Ipo::num(abs($value)).' Cr';
    }

    public static function times(?float $value): string
    {
        return $value === null ? '—' : number_format($value, 2).'x';
    }

    public static function percent(float $value): string
    {
        return ($value > 0 ? '+' : '').number_format($value, 1).'%';
    }

    public static function num(float $value, int $decimals = 2): string
    {
        return Ipo::num(round($value, $decimals), $decimals);
    }

    /**
     * @param  list<string>  $parts
     */
    private static function join(array $parts): string
    {
        return count($parts) <= 1 ? (string) ($parts[0] ?? '') : implode(', ', array_slice($parts, 0, -1)).' and '.end($parts);
    }
}
