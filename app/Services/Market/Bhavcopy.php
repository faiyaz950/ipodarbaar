<?php

namespace App\Services\Market;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;
use ZipArchive;

/**
 * The end-of-day price files NSE and BSE publish for every listed share. On a share's first
 * trading day its "open" is the listing price; BSE marks new listings with a previous close of 0.
 */
class Bhavcopy
{
    private const URLS = [
        'NSE' => 'https://nsearchives.nseindia.com/content/cm/BhavCopy_NSE_CM_0_0_0_%s_F_0000.csv.zip',
        'BSE' => 'https://www.bseindia.com/download/BhavCopy/Equity/BhavCopy_BSE_CM_0_0_0_%s_F_0000.CSV',
    ];

    /** @var array<string, Collection<int, array<string, mixed>>> */
    private array $loaded = [];

    /**
     * Equity rows for one exchange and day; empty when the file isn't published (holiday, or too early).
     *
     * @return Collection<int, array{exchange: string, symbol: string, code: string, isin: string, name: string, series: string, open: float, high: float, low: float, close: float, prev_close: float}>
     */
    public function rows(string $exchange, Carbon $date): Collection
    {
        $key = $exchange.$date->format('Ymd');

        return $this->loaded[$key] ??= $this->parse($exchange, $this->download($exchange, $date));
    }

    /** Drops loaded files, so long backfills don't keep every day in memory. */
    public function forget(): void
    {
        $this->loaded = [];
    }

    private function download(string $exchange, Carbon $date): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36',
                'Referer' => $exchange === 'BSE' ? 'https://www.bseindia.com/' : 'https://www.nseindia.com/',
            ])->connectTimeout(10)->timeout(60)->retry(1, 1000, throw: false)->get(sprintf(self::URLS[$exchange], $date->format('Ymd')));
        } catch (Throwable $e) {
            report($e);

            return null;
        }
        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();
        if ($exchange === 'NSE') {
            return $this->unzip($body);
        }

        return str_starts_with($body, 'TradDt') ? $body : null;
    }

    private function unzip(string $bytes): ?string
    {
        if (! str_starts_with($bytes, 'PK') || ! class_exists(ZipArchive::class)) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'bhav');
        file_put_contents($tmp, $bytes);
        $zip = new ZipArchive;
        $csv = null;
        if ($zip->open($tmp) === true) {
            $csv = $zip->numFiles > 0 ? ($zip->getFromIndex(0) ?: null) : null;
            $zip->close();
        }
        @unlink($tmp);

        return $csv;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function parse(string $exchange, ?string $csv): Collection
    {
        if (! $csv) {
            return collect();
        }

        $lines = preg_split('/\R/', trim($csv)) ?: [];
        $head = str_getcsv((string) array_shift($lines));
        $col = array_flip($head);
        foreach (['TckrSymb', 'FinInstrmNm', 'OpnPric', 'ClsPric', 'PrvsClsgPric'] as $needed) {
            if (! isset($col[$needed])) {
                return collect();
            }
        }

        $rows = [];
        foreach ($lines as $line) {
            $cells = str_getcsv($line);
            if (($cells[$col['FinInstrmTp'] ?? -1] ?? 'STK') !== 'STK') {
                continue;
            }
            $rows[] = [
                'exchange' => $exchange,
                'symbol' => trim((string) ($cells[$col['TckrSymb']] ?? '')),
                'code' => trim((string) ($cells[$col['FinInstrmId'] ?? -1] ?? '')),
                'isin' => trim((string) ($cells[$col['ISIN'] ?? -1] ?? '')),
                'name' => trim((string) ($cells[$col['FinInstrmNm']] ?? '')),
                'series' => trim((string) ($cells[$col['SctySrs'] ?? -1] ?? '')),
                'open' => (float) ($cells[$col['OpnPric']] ?? 0),
                'high' => (float) ($cells[$col['HghPric'] ?? -1] ?? 0),
                'low' => (float) ($cells[$col['LwPric'] ?? -1] ?? 0),
                'close' => (float) ($cells[$col['ClsPric']] ?? 0),
                'prev_close' => (float) ($cells[$col['PrvsClsgPric']] ?? 0),
            ];
        }

        return collect($rows);
    }
}
