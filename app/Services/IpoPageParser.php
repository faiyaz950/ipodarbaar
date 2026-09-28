<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the details the IPO API doesn't provide from a source IPO page: the issue
 * details table, company overview, objects, strengths/weaknesses, promoters, lead
 * managers, KPIs, valuation and restated financials (converted to ₹ crore).
 */
class IpoPageParser
{
    private const FINANCIAL_ROWS = [
        'total_assets' => '/^(total\s+)?assets\b/i',
        'revenue' => '/^(total\s+income|total\s+revenue|revenue(\s+from\s+operations)?)\b/i',
        'pat' => '/^(profit\s+after\s+tax|pat|net\s+profit)\b/i',
        'net_worth' => '/^net\s*worth\b/i',
        'borrowings' => '/^(total\s+)?(borrowings?|debt)\b/i',
    ];

    public function __construct(private RegistrarExtractor $registrars) {}

    /**
     * @return array{
     *     registrar: ?string,
     *     lot_size: ?int,
     *     about: ?string,
     *     detail: array<string, float|string>,
     *     financials: list<array<string, float|string|null>>
     * }
     */
    public function parse(string $html): array
    {
        $html = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $sections = $this->sections($html);
        $pairs = $this->pairs($html);

        $lot = $this->number($pairs['lot size'] ?? null);

        $detail = [
            'face_value' => $this->number($pairs['face value'] ?? null),
            'fresh_issue_cr' => $this->crore($pairs['fresh issue'] ?? null),
            'ofs_cr' => $this->crore($pairs['offer for sale'] ?? null),
            'promoter_holding_pre' => $this->percent($this->pairLike($pairs, '/promoter.*\bpre\b|\bpre\b.*promoter/')),
            'promoter_holding_post' => $this->percent($this->pairLike($pairs, '/promoter.*\bpost\b|\bpost\b.*promoter/')),
            'roe' => $this->number($pairs['roe'] ?? null),
            'roce' => $this->number($pairs['roce'] ?? null),
            'ronw' => $this->number($pairs['ronw'] ?? null),
            'debt_equity' => $this->number($this->pairLike($pairs, '/^debt\s*\/\s*equity/')),
            'objects' => $this->joinLines($this->items($this->section($sections, '/objective|objects? of the issue/i')), 3000),
            'strengths' => $this->joinLines($this->items($this->section($sections, '/strength/i')), 3000),
            'weaknesses' => $this->joinLines($this->items($this->section($sections, '/weakness|risks?\b/i')), 3000),
            'promoters' => $this->promoters($this->section($sections, '/promoters?\b/i')),
            'lead_managers' => $this->joinLines($this->leadManagers($this->section($sections, '/lead\s+managers?/i')), 1000),
        ] + $this->valuation($html);

        return [
            'registrar' => $this->registrars->fromHtml($html),
            'lot_size' => $lot !== null && $lot >= 1 && $lot < 10_000_000 ? (int) $lot : null,
            'about' => $this->about($this->section($sections, '/company analysis|complete overview|company overview|about the company/i')),
            'detail' => array_filter($detail, fn ($value) => $value !== null && $value !== ''),
            'financials' => $this->firstFinancials($sections),
        ];
    }

    /**
     * Some pages split financials over several headings, some only as images: use the first table found.
     *
     * @param  list<array{0: string, 1: string}>  $sections
     * @return list<array<string, float|string|null>>
     */
    private function firstFinancials(array $sections): array
    {
        foreach ($sections as [$heading, $body]) {
            if (preg_match('/financial|profit\s*&\s*loss|balance sheet/i', $heading) && ! preg_match('/cash/i', $heading)) {
                if ($rows = $this->financials($body)) {
                    return $rows;
                }
            }
        }

        return [];
    }

    /** @return list<array{0: string, 1: string}> heading text and the HTML up to the next heading */
    private function sections(string $html): array
    {
        $parts = preg_split('#(<h[1-4]\b[^>]*>.*?</h[1-4]>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $sections = [];
        for ($i = 1; $i < count($parts) - 1; $i += 2) {
            $sections[] = [$this->text($parts[$i]), $parts[$i + 1]];
        }

        return $sections;
    }

    /** @param list<array{0: string, 1: string}> $sections */
    private function section(array $sections, string $pattern, ?string $exclude = null): ?string
    {
        foreach ($sections as [$heading, $body]) {
            if (preg_match($pattern, $heading) && ! ($exclude && preg_match($exclude, $heading))) {
                return $body;
            }
        }

        return null;
    }

    /** @return array<string, string> label => value of every two-cell table row (first occurrence wins) */
    private function pairs(string $html): array
    {
        $pairs = [];
        foreach ($this->rows($html) as $cells) {
            if (count($cells) === 2) {
                $label = Str::lower(trim($cells[0], ' :'));
                $pairs[$label] ??= $cells[1];
            }
        }

        return $pairs;
    }

    /** @param array<string, string> $pairs */
    private function pairLike(array $pairs, string $pattern): ?string
    {
        foreach ($pairs as $label => $value) {
            if (preg_match($pattern.'i', $label)) {
                return $value;
            }
        }

        return null;
    }

    /** @return list<list<string>> */
    private function rows(string $html): array
    {
        $rows = [];
        preg_match_all('#<tr\b[^>]*>(.*?)</tr>#is', $html, $trs);
        foreach ($trs[1] as $tr) {
            preg_match_all('#<t[dh]\b[^>]*>(.*?)</t[dh]>#is', $tr, $cells);
            $rows[] = array_map(fn (string $cell) => $this->text($cell), $cells[1]);
        }

        return $rows;
    }

    /** @return array<string, float> */
    private function valuation(string $html): array
    {
        $values = [];
        foreach ($this->rows($html) as $cells) {
            if (count($cells) !== 3) {
                continue;
            }
            $label = Str::lower($cells[0]);
            if (str_starts_with($label, 'eps')) {
                $values['eps'] ??= $this->number($cells[1]);
            } elseif (str_starts_with($label, 'p/e')) {
                $values['pe_pre'] ??= $this->number($cells[1]);
                $values['pe_post'] ??= $this->number($cells[2]);
            }
        }

        return array_filter($values, fn ($value) => $value !== null);
    }

    /** @return list<array<string, float|string|null>> */
    private function financials(?string $body): array
    {
        if ($body === null) {
            return [];
        }

        $rows = $this->rows($body);
        if (count($rows) < 2) {
            return [];
        }

        $unit = $this->text(preg_replace('#<table\b.*?</table>#is', ' ', $body).' '.implode(' ', $rows[0]));
        $factor = match (true) {
            (bool) preg_match('/\blakhs?\b|\blacs?\b/i', $unit) => 0.01,
            (bool) preg_match('/\bmillions?\b|\bmn\b/i', $unit) => 0.1,
            default => 1.0,
        };

        $periods = [];
        foreach (array_slice($rows[0], 1, 4, true) as $col => $heading) {
            if ($end = $this->periodEnd($heading)) {
                $periods[$col] = [
                    'period' => $end->month === 3 ? 'FY'.$end->format('y') : $end->format('M Y'),
                    'period_end' => $end->toDateString(),
                ];
            }
        }

        foreach (array_slice($rows, 1) as $cells) {
            $metric = collect(self::FINANCIAL_ROWS)->search(fn (string $pattern) => preg_match($pattern, $cells[0] ?? ''));
            if ($metric === false) {
                continue;
            }
            foreach ($periods as $col => $period) {
                $value = $this->number($cells[$col] ?? null);
                $periods[$col][$metric] ??= $value === null ? null : round($value * $factor, 2);
            }
        }

        return array_values(array_filter($periods, fn (array $period) => ($period['revenue'] ?? $period['pat'] ?? null) !== null));
    }

    private function periodEnd(string $heading): ?Carbon
    {
        if (preg_match('/^FY\s*\'?(\d{2}|\d{4})$/i', trim($heading), $m)) {
            return Carbon::create((int) (strlen($m[1]) === 2 ? '20'.$m[1] : $m[1]), 3, 31);
        }
        if (! preg_match('/\d{4}/', $heading)) {
            return null;
        }

        try {
            return Carbon::parse($heading)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function about(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        preg_match_all('#<p\b[^>]*>(.*?)</p>#is', preg_replace('#<table\b.*?</table>#is', ' ', $body), $m);
        $paragraphs = array_values(array_filter(array_map(fn (string $p) => $this->text($p), $m[1]), fn (string $p) => mb_strlen($p) > 40));

        return $paragraphs === [] ? null : Str::limit(implode("\n\n", $paragraphs), 4000, '');
    }

    /** @return list<string> */
    private function items(?string $body): array
    {
        if ($body === null) {
            return [];
        }

        preg_match_all('#<li\b[^>]*>(.*?)</li>#is', $body, $m);

        return array_values(array_filter(array_map(fn (string $li) => $this->text($li), $m[1]), fn (string $item) => mb_strlen($item) > 2));
    }

    private function promoters(?string $body): ?string
    {
        $names = array_map(fn (string $name) => rtrim($name, ' .'), $this->items(preg_replace('#<table\b.*?</table>#is', ' ', (string) $body)));
        $names = array_filter($names, fn (string $name) => mb_strlen($name) <= 80);

        return $names === [] ? null : Str::limit(implode(', ', $names), 1000, '');
    }

    /** @return list<string> */
    private function leadManagers(?string $body): array
    {
        $items = $this->items($body);
        if ($items === [] && $body !== null) {
            preg_match_all('#<p\b[^>]*>(.*?)</p>#is', $body, $m);
            $items = array_values(array_filter(array_map(fn (string $p) => $this->text($p), $m[1])));
        }

        return array_values(array_filter($items, fn (string $item) => mb_strlen($item) <= 120));
    }

    /** @param list<string> $lines */
    private function joinLines(array $lines, int $max): ?string
    {
        return $lines === [] ? null : Str::limit(implode("\n", $lines), $max, '');
    }

    private function text(string $html): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', ' ', $html)), ENT_QUOTES | ENT_HTML5);

        return Str::squish(str_replace(["\u{00A0}", "\u{200B}"], ' ', $text));
    }

    private function number(?string $value): ?float
    {
        if ($value === null || ! preg_match('/(\()?\s*(-)?\s*(\d[\d,]*(?:\.\d+)?|\.\d+)/', $value, $m)) {
            return null;
        }

        $number = (float) str_replace(',', '', $m[3]);

        return ($m[1] !== '' || $m[2] !== '') ? -$number : $number;
    }

    private function percent(?string $value): ?float
    {
        $number = $this->number($value);

        return $number !== null && $number >= 0 && $number <= 100 ? $number : null;
    }

    private function crore(?string $value): ?float
    {
        if ($value === null || ! preg_match('/(?:Rs\.?|₹|INR)\s*([\d,]*\.?\d+)\s*(crores?|cr|lakhs?|lacs?)\b/i', $value, $m)) {
            return null;
        }

        $amount = (float) str_replace(',', '', $m[1]);

        return round(preg_match('/^la/i', $m[2]) ? $amount / 100 : $amount, 2);
    }
}
