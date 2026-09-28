<?php

namespace App\Services;

use App\Models\Ipo;
use App\Models\IpoGmpHistory;
use App\Support\PageCache;
use App\Support\SchedulerHeartbeat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function Illuminate\Support\defer;

class IpoSyncService
{
    public const SYNCED_AT_KEY = 'ipo:synced_at';

    public function __construct(private IpoStatsService $stats) {}

    /**
     * Pull IPOs from the API into the local database.
     *
     * @param  int|null  $maxPages  null = every page (full sync)
     * @return array{fetched:int, pages:int, total:int}
     */
    public function sync(?int $maxPages = null, ?callable $onPage = null): array
    {
        $pageSize = (int) config('ipodarbar.ipo_api.page_size', 50);
        $offset = 0;
        $pages = 0;
        $fetched = 0;
        $total = 0;

        do {
            $payload = $this->fetchPage($offset, $pageSize);
            $rows = $payload['data'] ?? [];
            $total = (int) ($payload['pagination']['total'] ?? $total);

            $this->store($rows);

            $fetched += count($rows);
            $offset += $pageSize;
            $pages++;
            $hasMore = (bool) ($payload['pagination']['has_more'] ?? false);

            if ($onPage) {
                $onPage($pages, $fetched, $total);
            }
        } while ($hasMore && count($rows) > 0 && ($maxPages === null || $pages < $maxPages));

        $this->markSynced();

        return ['fetched' => $fetched, 'pages' => $pages, 'total' => $total];
    }

    /**
     * Store one page of API rows pushed by the relay instead of pulled from the API.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function import(array $rows): int
    {
        $rows = array_values(array_filter($rows, 'is_array'));
        $this->store($rows);
        $this->markSynced();

        return count($rows);
    }

    protected function markSynced(): void
    {
        Cache::forever(self::SYNCED_AT_KEY, now()->toIso8601String());
        $this->stats->flush();
        Cache::forget('layout:ticker');
        PageCache::flush();
    }

    /** Quick refresh of the most recent IPOs (GMP/dates change most there). */
    public function syncRecent(): array
    {
        return $this->sync((int) config('ipodarbar.ipo_api.recent_pages', 3));
    }

    public static function lastSyncedAt(): ?Carbon
    {
        $value = Cache::get(self::SYNCED_AT_KEY);

        return $value ? Carbon::parse($value) : null;
    }

    /**
     * Fallback for when cron isn't running: an empty table is filled right away and a
     * stale one is refreshed after the response has been sent. While the scheduler is
     * alive it keeps the data fresh, so page requests never trigger a sync.
     */
    public function ensureFresh(): void
    {
        if (SchedulerHeartbeat::isAlive()) {
            return;
        }

        $staleAfter = (int) config('ipodarbar.ipo_api.stale_after', 20);
        $last = self::lastSyncedAt();

        if ($last && $last->gt(now()->subMinutes($staleAfter))) {
            return;
        }

        $run = function () {
            Cache::lock('ipo:sync-lock', 120)->get(function () {
                try {
                    $this->syncRecent();
                } catch (Throwable $e) {
                    // Back off so a failing API isn't hit on every request.
                    Cache::put(self::SYNCED_AT_KEY, now()->subMinutes(
                        max(0, (int) config('ipodarbar.ipo_api.stale_after', 20) - 5)
                    )->toIso8601String());
                    Log::warning('IPO sync failed: '.$e->getMessage());
                }
            });
        };

        if (! Ipo::query()->exists()) {
            $run();

            return;
        }

        defer($run);
    }

    protected function fetchPage(int $offset, int $limit): array
    {
        $key = config('ipodarbar.ipo_api.key');
        if (! $key) {
            throw new RuntimeException('IPO_API_KEY is not configured.');
        }

        $response = Http::timeout(30)
            ->retry(2, 750, throw: false)
            ->acceptJson()
            ->get(config('ipodarbar.ipo_api.url'), [
                'limit' => $limit,
                'offset' => $offset,
                'key' => $key,
            ]);

        $response->throw();
        $json = $response->json();

        if (! is_array($json) || ($json['status'] ?? null) !== 'success') {
            throw new RuntimeException('Unexpected IPO API response at offset '.$offset);
        }

        return $json;
    }

    /** @param array<int, array<string, mixed>> $rows */
    protected function store(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $records = [];
        foreach ($rows as $row) {
            $record = self::normalize($row);
            if ($record) {
                $records[$record['api_id']] = $record;
            }
        }
        if ($records === []) {
            return;
        }

        // Guarantee unique slugs: a slug already owned by another API record gets the id appended.
        $owners = Ipo::query()->whereIn('slug', array_column($records, 'slug'))->pluck('api_id', 'slug');
        $seen = [];
        foreach ($records as $apiId => $record) {
            $slug = $record['slug'];
            $owner = $owners[$slug] ?? null;
            if (($owner !== null && (int) $owner !== $apiId) || isset($seen[$slug])) {
                $slug = $slug.'-'.$apiId;
            }
            $seen[$slug] = true;
            $records[$apiId]['slug'] = $slug;
        }

        // Keep admin overrides: locked columns retain their current value.
        $existing = Ipo::query()->whereIn('api_id', array_keys($records))->whereNotNull('locked_fields')->get();
        foreach ($existing as $ipo) {
            foreach ($ipo->locked_fields ?? [] as $field) {
                if (in_array($field, Ipo::LOCKABLE, true) && array_key_exists($field, $records[$ipo->api_id])) {
                    $records[$ipo->api_id][$field] = $ipo->getRawOriginal($field);
                }
            }
        }

        $columns = array_keys(reset($records));
        Ipo::query()->upsert(array_values($records), ['api_id'], array_diff($columns, ['api_id']));

        IpoGmpHistory::record(Ipo::query()->whereIn('api_id', array_keys($records))->get());
    }

    /** Map one API row to our table columns. */
    public static function normalize(array $row): ?array
    {
        $apiId = (int) ($row['id'] ?? 0);
        $title = trim((string) ($row['title'] ?? ''));
        if (! $apiId || $title === '') {
            return null;
        }

        $description = trim(html_entity_decode((string) ($row['description'] ?? ''), ENT_QUOTES));
        $name = self::extractName($title);
        $band = self::parsePriceBand($description);
        $price = self::toNumber($row['price'] ?? null) ?? $band['max'];
        $priceMin = $band['min'] !== null && $price !== null && $band['min'] < $price ? $band['min'] : null;

        $slug = Str::slug((string) ($row['external_link'] ?? $row['tags'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($name.'-ipo');
        }

        return [
            'api_id' => $apiId,
            'slug' => $slug,
            'name' => $name,
            'title' => Str::limit(trim(preg_replace('/\s*\|\s*Finowings\s*$/i', '', $title)), 250, ''),
            'description' => $description ?: null,
            'type' => stripos((string) ($row['type'] ?? ''), 'sme') !== false ? 'sme' : 'mainboard',
            'exchange' => self::parseExchange($description),
            'image_url' => ($row['image_url'] ?? null) ?: null,
            'price' => $price,
            'price_min' => $priceMin,
            'gmp' => self::toNumber($row['gmp'] ?? null),
            'issue_size' => self::toNumber($row['size'] ?? null),
            'lot_size' => self::parseLotSize($description),
            'open_date' => self::toDate($row['open_date_iso'] ?? null),
            'close_date' => self::toDate($row['close_date_iso'] ?? null),
            'listing_date' => self::toDate($row['listing_date_iso'] ?? null),
            'is_published' => strcasecmp((string) ($row['is_published'] ?? ''), 'yes') === 0,
            'source_created_at' => self::toDateTime($row['created_date'] ?? null),
            'source_updated_at' => self::toDateTime($row['updated_date'] ?? null),
        ];
    }

    public static function extractName(string $title): string
    {
        $title = preg_replace('/\s*\|\s*Finowings\s*$/i', '', $title);
        if (preg_match('/^(.*?)\s+(?:SME\s+)?IPO\b/i', $title, $m) && trim($m[1]) !== '') {
            return trim($m[1]);
        }

        return trim(preg_split('/\s*[:\-–|]\s+/', $title)[0]);
    }

    /** @return array{min:?float, max:?float} */
    public static function parsePriceBand(string $text): array
    {
        if (preg_match('/₹\s*([\d,]+(?:\.\d+)?)\s*(?:-|–|to)\s*₹?\s*([\d,]+(?:\.\d+)?)\s*(?:\/|per)\s*share/iu', $text, $m)) {
            return ['min' => self::toNumber($m[1]), 'max' => self::toNumber($m[2])];
        }
        if (preg_match('/₹\s*([\d,]+(?:\.\d+)?)\s*(?:\/|per)\s*share/iu', $text, $m)) {
            return ['min' => null, 'max' => self::toNumber($m[1])];
        }

        return ['min' => null, 'max' => null];
    }

    public static function parseLotSize(string $text): ?int
    {
        if (preg_match('/lot\s*size\s*(?:of|:|\()?\s*([\d,]+)\s*shares/i', $text, $m)) {
            $n = (int) str_replace(',', '', $m[1]);

            return $n > 0 ? $n : null;
        }

        return null;
    }

    public static function parseExchange(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/NSE\s*(?:Emerge|SME)/i', $text) => 'NSE SME',
            (bool) preg_match('/BSE\s*SME/i', $text) => 'BSE SME',
            (bool) preg_match('/(NSE\s*(?:&|and|,)\s*BSE|BSE\s*(?:&|and|,)\s*NSE)/i', $text) => 'BSE, NSE',
            default => null,
        };
    }

    public static function toNumber(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $clean = str_replace([',', ' ', '₹'], '', trim((string) $value));

        return is_numeric($clean) ? (float) $clean : null;
    }

    public static function toDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    public static function toDateTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }
}
