<?php

namespace App\Models;

use App\Services\LogoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Ipo extends Model
{
    public const STATUSES = [
        'open' => 'Open Now',
        'upcoming' => 'Upcoming',
        'closed' => 'Listing Soon',
        'listed' => 'Listed',
    ];

    /** API-backed columns an admin may override (and lock against the sync). */
    public const LOCKABLE = [
        'name', 'type', 'exchange', 'price_min', 'price', 'lot_size', 'issue_size',
        'gmp', 'open_date', 'close_date', 'listing_date',
    ];

    /** IPOs with no dates are treated as "upcoming (dates TBA)" for this many days after they appear. */
    private const TBA_WINDOW_DAYS = 45;

    /** Closed IPOs with no listing date stay in "listing soon" for this many days. */
    private const CLOSED_WINDOW_DAYS = 10;

    protected $guarded = [];

    protected $casts = [
        'price' => 'float',
        'price_min' => 'float',
        'gmp' => 'float',
        'issue_size' => 'float',
        'lot_size' => 'integer',
        'open_date' => 'date',
        'close_date' => 'date',
        'listing_date' => 'date',
        'is_published' => 'boolean',
        'source_created_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'subscription_retail' => 'float',
        'subscription_nii' => 'float',
        'subscription_qib' => 'float',
        'subscription_total' => 'float',
        'subscription_updated_at' => 'datetime',
        'listing_price' => 'float',
        'locked_fields' => 'array',
    ];

    private ?string $resolvedStatus = null;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function gmpHistory(): HasMany
    {
        return $this->hasMany(IpoGmpHistory::class)->orderBy('date');
    }

    public function isLocked(string $field): bool
    {
        return in_array($field, $this->locked_fields ?? [], true);
    }

    /* ------------------------------------------------------------------
     | Scopes
     * ------------------------------------------------------------------ */

    public static function today(): string
    {
        return now()->toDateString();
    }

    public function scopeOpen(Builder $q): Builder
    {
        $today = self::today();

        return $q->whereNotNull('open_date')
            ->whereDate('open_date', '<=', $today)
            ->where(fn ($w) => $w->whereDate('close_date', '>=', $today)
                ->orWhere(fn ($n) => $n->whereNull('close_date')->whereDate('open_date', '=', $today)));
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        $today = self::today();
        $tba = now()->subDays(self::TBA_WINDOW_DAYS);

        return $q->where(fn ($w) => $w->whereDate('open_date', '>', $today)
            ->orWhere(fn ($n) => $n->whereNull('open_date')->where('source_created_at', '>=', $tba)));
    }

    /** Subscription closed; allotment / listing pending (includes listing day). */
    public function scopeClosed(Builder $q): Builder
    {
        $today = self::today();
        $window = now()->subDays(self::CLOSED_WINDOW_DAYS)->toDateString();

        return $q->whereDate('close_date', '<', $today)
            ->where(fn ($w) => $w->whereDate('listing_date', '>=', $today)
                ->orWhere(fn ($n) => $n->whereNull('listing_date')->whereDate('close_date', '>=', $window)));
    }

    public function scopeListed(Builder $q): Builder
    {
        $today = self::today();
        $window = now()->subDays(self::CLOSED_WINDOW_DAYS)->toDateString();
        $tba = now()->subDays(self::TBA_WINDOW_DAYS);

        return $q->where(fn ($w) => $w->whereDate('listing_date', '<', $today)
            ->orWhere(fn ($n) => $n->whereNull('listing_date')->whereDate('close_date', '<', $window))
            ->orWhere(fn ($n) => $n->whereNull('open_date')->whereNull('listing_date')
                ->where(fn ($c) => $c->whereNull('source_created_at')->orWhere('source_created_at', '<', $tba))));
    }

    /** Anything not yet listed: upcoming, open or awaiting listing. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->where(fn ($s) => $s->upcoming())
            ->orWhere(fn ($s) => $s->open())
            ->orWhere(fn ($s) => $s->closed()));
    }

    public function scopeInStatus(Builder $q, ?string $status): Builder
    {
        return match ($status) {
            'open' => $q->open()->orderBy('close_date')->orderBy('name'),
            'upcoming' => $q->upcoming()->orderByRaw('open_date is null')->orderBy('open_date')->orderBy('name'),
            'closed' => $q->closed()->orderByRaw('listing_date is null')->orderBy('listing_date')->orderBy('name'),
            'listed' => $q->listed()->orderByRaw('listing_date is null')->orderByDesc('listing_date')->orderByDesc('api_id'),
            default => $q->orderByRaw('open_date is null')->orderByDesc('open_date')->orderByDesc('api_id'),
        };
    }

    public function scopeOfType(Builder $q, ?string $type): Builder
    {
        return in_array($type, ['mainboard', 'sme'], true) ? $q->where('type', $type) : $q;
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $q : $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%');
    }

    /* ------------------------------------------------------------------
     | Derived values
     * ------------------------------------------------------------------ */

    public function status(): string
    {
        if ($this->resolvedStatus !== null) {
            return $this->resolvedStatus;
        }

        $today = now()->startOfDay();
        $open = $this->open_date;
        $close = $this->close_date ?? $open;
        $listing = $this->listing_date;

        if (! $open) {
            $recent = $this->source_created_at && $this->source_created_at->gte(now()->subDays(self::TBA_WINDOW_DAYS));

            return $this->resolvedStatus = ($recent && ! $listing) ? 'upcoming' : 'listed';
        }

        return $this->resolvedStatus = match (true) {
            $open->gt($today) => 'upcoming',
            $close->gte($today) => 'open',
            $listing && $listing->gte($today) => 'closed',
            ! $listing && $close->gte($today->copy()->subDays(self::CLOSED_WINDOW_DAYS)) => 'closed',
            default => 'listed',
        };
    }

    public function statusLabel(): string
    {
        if ($this->status() === 'closed' && $this->listing_date?->isToday()) {
            return 'Listing Today';
        }

        return self::STATUSES[$this->status()];
    }

    /** Short, human countdown like "Closes in 2 days". */
    public function countdown(): ?string
    {
        $today = now()->startOfDay();

        $phrase = function (string $verb, ?Carbon $date) use ($today): ?string {
            if (! $date) {
                return null;
            }
            $days = (int) $today->diffInDays($date->copy()->startOfDay(), false);

            return match (true) {
                $days === 0 => "{$verb} today",
                $days === 1 => "{$verb} tomorrow",
                $days > 1 => "{$verb} in {$days} days",
                default => null,
            };
        };

        return match ($this->status()) {
            'upcoming' => $this->open_date ? $phrase('Opens', $this->open_date) : 'Dates awaited',
            'open' => $phrase('Closes', $this->close_date ?? $this->open_date),
            'closed' => $this->listing_date ? $phrase('Lists', $this->listing_date) : 'Allotment awaited',
            default => $this->listing_date ? 'Listed '.$this->listing_date->format('j M Y') : null,
        };
    }

    public function isMainboard(): bool
    {
        return $this->type === 'mainboard';
    }

    public function typeLabel(): string
    {
        return $this->isMainboard() ? 'Mainboard' : 'SME';
    }

    public function exchangeLabel(): string
    {
        return $this->exchange ?: ($this->isMainboard() ? 'BSE, NSE' : 'SME Platform');
    }

    public function priceBand(): string
    {
        if (! $this->price) {
            return 'Awaited';
        }
        if ($this->price_min && $this->price_min < $this->price) {
            return '₹'.self::num($this->price_min).' – '.self::num($this->price);
        }

        return '₹'.self::num($this->price);
    }

    public function hasGmp(): bool
    {
        return $this->gmp !== null;
    }

    public function estListingPrice(): ?float
    {
        return ($this->price && $this->gmp !== null) ? $this->price + $this->gmp : null;
    }

    public function gmpPercent(): ?float
    {
        return ($this->price && $this->gmp !== null) ? round($this->gmp / $this->price * 100, 2) : null;
    }

    public function lotAmount(): ?float
    {
        return ($this->price && $this->lot_size) ? $this->price * $this->lot_size : null;
    }

    /** Estimated profit per lot based on GMP. */
    public function gmpLotProfit(): ?float
    {
        return ($this->gmp !== null && $this->lot_size) ? $this->gmp * $this->lot_size : null;
    }

    /**
     * Investor-category lot limits (retail ≤ ₹2L, sHNI ≤ ₹10L, bHNI above).
     *
     * @return array<int, array{category:string, lots:int, shares:int, amount:float}>|null
     */
    public function lotTable(): ?array
    {
        $lot = $this->lotAmount();
        if (! $lot) {
            return null;
        }

        $retailMax = config('ipodarbar.limits.retail_max');
        $shniMax = config('ipodarbar.limits.shni_max');

        // SME issues: individual investors apply for exactly 2 lots; HNIs start from 3 lots.
        $retailMinLots = $this->isMainboard() ? 1 : 2;
        $retailMaxLots = $this->isMainboard() ? max(1, (int) floor($retailMax / $lot)) : 2;
        $shniMinLots = $retailMaxLots + 1;
        $shniMaxLots = max($shniMinLots, (int) floor($shniMax / $lot));
        $bhniMinLots = $shniMaxLots + 1;

        $rows = [
            ['category' => 'Retail (Min)', 'lots' => $retailMinLots],
            ['category' => 'Retail (Max)', 'lots' => $retailMaxLots],
            ['category' => 'S-HNI (Min)', 'lots' => $shniMinLots],
            ['category' => 'S-HNI (Max)', 'lots' => $shniMaxLots],
            ['category' => 'B-HNI (Min)', 'lots' => $bhniMinLots],
        ];

        return array_map(fn ($r) => $r + [
            'shares' => $r['lots'] * $this->lot_size,
            'amount' => $r['lots'] * $lot,
        ], $rows);
    }

    /**
     * T+3 listing timeline. Dates after close are tentative estimates unless supplied.
     *
     * @return array<int, array{label:string, date:?Carbon, tentative:bool, done:bool, current:bool}>
     */
    public function timeline(): array
    {
        $close = $this->close_date;
        $allot = $close ? self::addWorkingDays($close, 1) : null;
        $refund = $close ? self::addWorkingDays($close, 2) : null;

        $steps = [
            ['label' => 'IPO Opens', 'date' => $this->open_date, 'tentative' => false],
            ['label' => 'IPO Closes', 'date' => $close, 'tentative' => false],
            ['label' => 'Basis of Allotment', 'date' => $allot, 'tentative' => true],
            ['label' => 'Refunds Initiated', 'date' => $refund, 'tentative' => true],
            ['label' => 'Shares Credited to Demat', 'date' => $refund, 'tentative' => true],
            ['label' => 'Listing Date', 'date' => $this->listing_date, 'tentative' => false],
        ];

        $today = now()->startOfDay();
        $currentFound = false;

        foreach ($steps as $i => $step) {
            $done = $step['date'] && $step['date']->lt($today);
            $isCurrent = ! $currentFound && $step['date'] && $step['date']->gte($today);
            if ($isCurrent) {
                $currentFound = true;
            }
            $steps[$i]['done'] = $done;
            $steps[$i]['current'] = $isCurrent;
        }

        return $steps;
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim(preg_replace('/[^A-Za-z0-9 ]/', ' ', $this->name))) ?: [];
        $letters = collect($words)->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');

        return strtoupper($letters ?: 'IPO');
    }

    /** Stable hue per company so monograms look varied but consistent. */
    public function hue(): int
    {
        return crc32($this->name) % 360;
    }

    /** Small cropped logo thumbnail (generated on first request, then served statically). */
    public function logoUrl(): ?string
    {
        return app(LogoService::class)->url($this);
    }

    /** Original full-size banner from the data source. */
    public function bannerUrl(): ?string
    {
        return $this->image_url ? str_replace(' ', '%20', $this->image_url) : null;
    }

    public function url(): string
    {
        return route('ipos.show', $this);
    }

    public function calculatorUrl(): string
    {
        return route('calculators.show', ['ipo-gmp']).'?'.http_build_query(array_filter([
            'price' => $this->price,
            'gmp' => $this->gmp,
            'lot' => $this->lot_size,
        ], fn ($v) => $v !== null));
    }

    public static function num(float|int|null $value, int $decimals = 2): string
    {
        if ($value === null) {
            return '—';
        }
        $formatted = self::indianFormat((float) $value, $decimals);

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }

    /** Indian digit grouping: 12,34,567.89 */
    public static function indianFormat(float $value, int $decimals = 2): string
    {
        $negative = $value < 0;
        $parts = explode('.', number_format(abs($value), $decimals, '.', ''));
        $int = $parts[0];
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        if ($rest !== '') {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int = $rest.','.$last3;
        }
        $out = $int.(isset($parts[1]) ? '.'.$parts[1] : '');

        return ($negative ? '-' : '').$out;
    }

    public static function money(?float $value): string
    {
        return $value === null ? '—' : '₹'.self::num($value);
    }

    public static function addWorkingDays(Carbon $date, int $days): Carbon
    {
        $d = $date->copy();
        while ($days > 0) {
            $d->addDay();
            if (! $d->isWeekend()) {
                $days--;
            }
        }

        return $d;
    }

    /** Actual listing-day gain, when the listing price has been recorded. */
    public function listingGain(): ?float
    {
        return ($this->listing_price && $this->price) ? $this->listing_price - $this->price : null;
    }

    public function listingGainPercent(): ?float
    {
        return ($this->listing_price && $this->price) ? round(($this->listing_price - $this->price) / $this->price * 100, 2) : null;
    }

    public function hasSubscription(): bool
    {
        return $this->subscription_total !== null || $this->subscription_retail !== null
            || $this->subscription_nii !== null || $this->subscription_qib !== null;
    }

    /** @return array<int, array{label:string, value:float}> */
    public function subscriptionRows(): array
    {
        return array_values(array_filter([
            ['label' => 'Retail (RII)', 'value' => $this->subscription_retail],
            ['label' => 'NII / HNI', 'value' => $this->subscription_nii],
            ['label' => 'QIB', 'value' => $this->subscription_qib],
            ['label' => 'Total', 'value' => $this->subscription_total],
        ], fn ($r) => $r['value'] !== null));
    }

    /** @return array{name:string, url:string}|null */
    public function registrarInfo(): ?array
    {
        if (! $this->registrar) {
            return null;
        }

        return collect(config('ipodarbar.registrars'))->firstWhere('name', $this->registrar)
            ?? ['name' => $this->registrar, 'url' => null];
    }

    public function metaDescription(): string
    {
        if ($this->about) {
            return Str::limit(Str::squish($this->about), 158);
        }

        $parts = ["{$this->name} {$this->typeLabel()} IPO"];
        if ($this->open_date) {
            $parts[] = 'opens '.$this->open_date->format('j M')
                .($this->close_date ? '–'.$this->close_date->format('j M Y') : '');
        }
        if ($this->price) {
            $parts[] = 'price band '.$this->priceBand();
        }

        return Str::limit(implode(', ', $parts).'. Check live GMP, lot size, subscription, allotment and listing date.', 158);
    }
}
