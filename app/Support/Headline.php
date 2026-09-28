<?php

namespace App\Support;

/**
 * Turns SHOUTING news headlines from the feed into title case ("NSE FINALLY GOES PUBLIC!"
 * → "NSE Finally Goes Public!"). All-caps titles look spammy in search results and get
 * fewer clicks. Headlines that are already in normal case are returned untouched.
 */
class Headline
{
    /** Share of upper-case letters from which a headline counts as shouting. */
    private const SHOUTING_RATIO = 0.5;

    /** Abbreviations that stay upper case; the value is the spelling when it isn't all caps. */
    private const ACRONYMS = [
        'IPO', 'FPO', 'NSE', 'BSE', 'SEBI', 'RBI', 'UPI', 'GMP', 'SME', 'MSME', 'NII', 'HNI', 'RII', 'QIB', 'QIP', 'OFS',
        'DRHP', 'RHP', 'FII', 'DII', 'FPI', 'FDI', 'ETF', 'NFO', 'SIP', 'SWP', 'EMI', 'GST', 'GDP', 'CPI', 'WPI', 'IIP', 'PMI',
        'EPS', 'PAT', 'EBITDA', 'ROE', 'ROCE', 'NAV', 'AUM', 'AMC', 'AMFI', 'NBFC', 'PSU', 'PSB', 'NPA', 'MPC', 'CRR', 'SLR',
        'US', 'USA', 'UK', 'EU', 'UAE', 'UN', 'UNSC', 'IMF', 'WTO', 'OPEC', 'NATO', 'G20', 'AI', 'EV', 'ESG', 'FMCG', 'B2B', 'D2C',
        'LIC', 'SBI', 'HDFC', 'ICICI', 'IDFC', 'ITC', 'ONGC', 'NTPC', 'BPCL', 'HPCL', 'IOC', 'IOCL', 'BHEL', 'TCS', 'HCL', 'HUL',
        'MRF', 'IRCTC', 'IRFC', 'RVNL', 'BEL', 'HAL', 'SAIL', 'GAIL', 'NMDC', 'NHAI', 'NPCI', 'ONDC', 'CBDC', 'MCX', 'NCDEX',
        'MSCI', 'FTSE', 'NASDAQ', 'NRI', 'KYC', 'PAN', 'ATM', 'OTP', 'FD', 'PPF', 'NPS', 'EPF', 'EPFO', 'ITR', 'TDS', 'SGB',
        'REIT', 'NCD', 'NCLT', 'CCI', 'CBI', 'ED', 'CAG', 'IRDAI', 'PFRDA', 'ISRO', 'DRDO', 'BJP', 'CEO', 'CFO', 'CMD', 'MD',
        'AGM', 'EGM', 'ESOP', 'SPAC', 'BTC', 'ETH', 'USD', 'INR', 'FY', 'F&O', 'L&T', 'M&M', 'S&P',
        'YOY' => 'YoY', 'QOQ' => 'QoQ', 'INVIT' => 'InvIT',
    ];

    /** Short words kept lower case inside a title (AP style), plus common Hinglish particles. */
    private const SMALL_WORDS = [
        'a', 'an', 'the', 'and', 'but', 'or', 'nor', 'for', 'of', 'in', 'on', 'at', 'to', 'by', 'as', 'via', 'vs', 'per',
        'ka', 'ke', 'ki', 'ko', 'se', 'ne', 'par', 'mein', 'me',
    ];

    /** "IT" is the sector (not the pronoun) when followed by one of these words. */
    private const IT_SECTOR_WORDS = ['STOCKS', 'SHARES', 'SECTOR', 'COMPANIES', 'FIRMS', 'MAJORS', 'INDEX', 'SERVICES', 'EXPORTS', 'HIRING', 'JOBS', 'SPENDING', 'PACK', 'RAID', 'RAIDS', 'DEPARTMENT'];

    public static function normalizeCase(string $headline): string
    {
        if (! self::isShouting($headline)) {
            return $headline;
        }

        $tokens = preg_split('/(\s+)/u', $headline, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $words = array_values(array_filter($tokens, fn (string $token): bool => trim($token) !== ''));
        $startsSentence = true;
        $wordIndex = 0;

        foreach ($tokens as $i => $token) {
            if (trim($token) === '') {
                continue;
            }

            $next = $words[$wordIndex + 1] ?? '';
            $wordIndex++;

            if (preg_match('/^([^\p{L}\p{N}]*)(.*?)([^\p{L}\p{N}]*)$/u', $token, $m) && $m[2] !== '') {
                $tokens[$i] = $m[1].self::word($m[2], $startsSentence, $next).$m[3];
            }

            // A new clause starts after ":", "!", "?", "|" or a dash.
            $startsSentence = (bool) preg_match('/[:!?|—–]$/u', $token);
        }

        return implode('', $tokens);
    }

    public static function isShouting(string $headline): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $headline) ?? '';
        $length = mb_strlen($letters);
        if ($length < 6) {
            return false;
        }

        return mb_strlen(preg_replace('/[^\p{Lu}]/u', '', $letters) ?? '') / $length >= self::SHOUTING_RATIO;
    }

    private static function word(string $word, bool $startsSentence, string $next): string
    {
        // Hyphenated words are cased part by part: "5-DAY" → "5-Day", "PRE-IPO" → "Pre-IPO".
        if (str_contains($word, '-')) {
            $parts = explode('-', $word);

            return implode('-', array_map(
                fn (string $part, int $i): string => self::part($part, $startsSentence || $i > 0, $next),
                $parts,
                array_keys($parts)
            ));
        }

        return self::part($word, $startsSentence, $next);
    }

    private static function part(string $part, bool $capitalize, string $next): string
    {
        // Already mixed case on purpose ("PhonePe", "IPOs", "Sensex"): leave it alone.
        if ($part === '' || preg_match('/\p{Ll}/u', $part)) {
            return $part;
        }

        // Numbers with letters: "Q2", "FY27" and "5G" stay; multiples read "12.68x".
        if (preg_match('/\p{N}/u', $part)) {
            return preg_match('/^[\d.,]+X$/u', $part) ? mb_strtolower($part) : $part;
        }

        // Apostrophes: "INDIA'S" → "India's", "D’STREET" → "D’Street", "UPI’S" → "UPI’s".
        if (preg_match('/^(.+?)([\'’])(.+)$/u', $part, $m)) {
            $suffix = in_array(mb_strtolower($m[3]), ['s', 't', 'll', 're', 've', 'd', 'm'], true)
                ? mb_strtolower($m[3])
                : self::part($m[3], true, $next);

            return self::part($m[1], $capitalize, $next).$m[2].$suffix;
        }

        if ($acronym = self::acronym($part, $next)) {
            return $acronym;
        }

        $lower = mb_strtolower($part);
        if (! $capitalize && in_array($lower, self::SMALL_WORDS, true)) {
            return $lower;
        }

        return mb_strtoupper(mb_substr($lower, 0, 1)).mb_substr($lower, 1);
    }

    private static function acronym(string $part, string $next): ?string
    {
        if ($part === 'IT') {
            return in_array(preg_replace('/[^\p{L}]/u', '', mb_strtoupper($next)), self::IT_SECTOR_WORDS, true) ? 'IT' : null;
        }

        foreach (self::ACRONYMS as $key => $value) {
            $acronym = is_string($key) ? $key : $value;
            if ($part === $acronym) {
                return $value;
            }
            // Plurals of longer abbreviations: "IPOS" → "IPOs", "QIBS" → "QIBs".
            if (mb_strlen($acronym) >= 3 && $part === $acronym.'S') {
                return $value.'s';
            }
        }

        return null;
    }
}
