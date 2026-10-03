<?php

namespace App\Services\Market;

use Illuminate\Support\Str;

/**
 * Compares company names across sources that spell them differently:
 * "Orient Cables (India) Limited", "ORIENT CABLES INDIA LTD" and "Orient Cables India".
 */
class CompanyName
{
    private const NOISE = ['limited', 'ltd', 'l', 'pvt', 'private', 'and', 'the', 'co', 'company', 'corporation', 'corp', 'inc'];

    public static function normalize(string $name): string
    {
        $name = Str::lower(Str::ascii($name));
        $name = preg_replace("/['’`]/", '', $name) ?? $name;
        $words = preg_split('/[^a-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_values(array_diff($words, self::NOISE)));
    }

    /**
     * Same company: identical once normalised, or one name is an abbreviation of the other
     * (exchange files shorten long names), as long as at least two words line up.
     */
    public static function matches(string $a, string $b): bool
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        $wa = explode(' ', $a);
        $wb = explode(' ', $b);
        [$short, $long] = count($wa) <= count($wb) ? [$wa, $wb] : [$wb, $wa];
        if (count($short) < 2) {
            return false;
        }
        // Exchange files abbreviate words ("CONSULT SER"), so after the first word a prefix is enough.
        foreach ($short as $i => $word) {
            $other = $long[$i] ?? '';
            if ($i === 0 ? $other !== $word : ! str_starts_with($other, $word)) {
                return false;
            }
        }

        return true;
    }
}
