<?php

namespace App\Services\Market;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads NSE's public IPO data: issues open for bidding, category-wise subscription
 * (consolidated across exchanges), past issues with their symbols and listing dates,
 * and the special pre-open session where new listings discover their opening price.
 */
class NseClient
{
    private const BASE = 'https://www.nseindia.com/api/';

    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    /** NSE's edge sets cookies on the first call that later calls are expected to send back. */
    private CookieJar $cookies;

    public function __construct()
    {
        $this->cookies = new CookieJar;
    }

    /**
     * @return list<array{symbol: string, name: string, series: string, start: ?Carbon, end: ?Carbon}>
     */
    public function currentIssues(): array
    {
        return array_values(array_filter(array_map(fn (array $row): ?array => isset($row['symbol']) ? [
            'symbol' => (string) $row['symbol'],
            'name' => (string) ($row['companyName'] ?? ''),
            'series' => (string) ($row['series'] ?? ''),
            'start' => $this->date($row['issueStartDate'] ?? null),
            'end' => $this->date($row['issueEndDate'] ?? null),
        ] : null, (array) $this->get('ipo-current-issue'))));
    }

    /**
     * @return list<array{symbol: string, name: string, series: string, start: ?Carbon, end: ?Carbon, listing: ?Carbon, issue_price: ?float}>
     */
    public function pastIssues(): array
    {
        return array_values(array_filter(array_map(fn (array $row): ?array => isset($row['symbol']) ? [
            'symbol' => (string) $row['symbol'],
            'name' => (string) ($row['companyName'] ?? $row['company'] ?? ''),
            'series' => (string) ($row['securityType'] ?? ''),
            'start' => $this->date($row['ipoStartDate'] ?? null),
            'end' => $this->date($row['ipoEndDate'] ?? null),
            'listing' => $this->date($row['listingDate'] ?? null),
            'issue_price' => is_numeric(trim((string) ($row['issuePrice'] ?? ''))) ? (float) trim((string) $row['issuePrice']) : null,
        ] : null, (array) $this->get('public-past-issues'))));
    }

    /**
     * Times subscribed per category, or null when NSE has no figures for the symbol.
     *
     * @return array{qib: ?float, nii: ?float, bnii: ?float, snii: ?float, retail: ?float, employee: ?float, total: ?float, as_of: ?Carbon}|null
     */
    public function subscription(string $symbol): ?array
    {
        $data = $this->get('ipo-active-category?symbol='.rawurlencode($symbol));
        $rows = $data['dataList'] ?? null;
        if (! is_array($rows)) {
            return null;
        }

        $out = array_fill_keys(['qib', 'nii', 'bnii', 'snii', 'retail', 'employee', 'total'], null);
        foreach ($rows as $row) {
            $category = trim((string) ($row['category'] ?? ''));
            $key = match (true) {
                (bool) preg_match('/^Qualified Institutional/i', $category) => 'qib',
                (bool) preg_match('/^Non Institutional Investors$/i', $category) => 'nii',
                (bool) preg_match('/^Non Institutional.*more than Ten Lakh/i', $category) => 'bnii',
                (bool) preg_match('/^Non Institutional.*more than Two Lakh/i', $category) => 'snii',
                (bool) preg_match('/^Retail Individual/i', $category) => 'retail',
                (bool) preg_match('/^Employee/i', $category) => 'employee',
                (bool) preg_match('/^Total$/i', $category) => 'total',
                default => null,
            };
            // A category with no shares reserved for it has no meaningful "times subscribed".
            if ($key && $out[$key] === null && is_numeric($row['noOfTotalMeant'] ?? null) && (float) ($row['noOfShareOffered'] ?? 0) > 0) {
                $out[$key] = round((float) $row['noOfTotalMeant'], 2);
            }
        }

        if ($out['total'] === null) {
            return null;
        }

        $asOf = preg_match('/(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2})/', (string) ($data['updateTime'] ?? ''), $m)
            ? Carbon::createFromFormat('d-M-Y H:i:s', $m[1], 'Asia/Kolkata')
            : null;

        return $out + ['as_of' => $asOf ?: null];
    }

    /**
     * New listings in today's special pre-open session: the equilibrium price is the listing price.
     *
     * @return list<array{symbol: string, isin: string, price: float, prev_close: ?float}>
     */
    public function preOpenListings(): array
    {
        $data = $this->get('special-preopen-listing');

        return array_values(array_filter(array_map(fn (array $row): ?array => isset($row['symbol']) && is_numeric($row['iep'] ?? null) && (float) $row['iep'] > 0 ? [
            'symbol' => (string) $row['symbol'],
            'isin' => (string) ($row['isin'] ?? ''),
            'price' => (float) $row['iep'],
            'prev_close' => is_numeric($row['prevClose'] ?? null) ? (float) $row['prevClose'] : null,
        ] : null, (array) ($data['data'] ?? []))));
    }

    private function get(string $path): mixed
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/json',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Referer' => 'https://www.nseindia.com/',
            ])->withOptions(['cookies' => $this->cookies])->connectTimeout(10)->timeout(25)->retry(2, 800, throw: false)->get(self::BASE.$path);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return $response->successful() ? $response->json() : null;
    }

    private function date(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        try {
            return Carbon::createFromFormat('!d-M-Y', ucfirst(strtolower($value)), 'Asia/Kolkata') ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
